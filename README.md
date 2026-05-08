# Laravel E-commerce: Order Email with AI Recommendations

A Laravel application that receives Stripe `checkout.session.completed` webhooks and sends order confirmation emails via [Mailtrap](https://mailtrap.io) - with **AI-generated product recommendations** pulled from a local product catalog using OpenAI.

## How It Works

```
Stripe Checkout completes
        |
        v
  Webhook received
  POST /api/stripe/webhook
        |
        v
  Verify Stripe signature -- Invalid? → 400 reject
        |
        v
  Check for duplicate -- Already processed? → 200 skip
        |
        v
  Retrieve session with expanded line_items
        |
        v
  Match line items to local catalog (by SKU metadata or name)
        |
        v
  Ask OpenAI for N complementary products -- Failed? → Skip recommendations
  from the remaining catalog
        |
        v
  Render HTML email (order + recommendations)
        |
        v
  Send via Mailtrap -- Failed? → 500 (Stripe retries)
  with X-MT-Category header
        |
        v
  Cache event ID (24h dedup window) → 200 OK
```

## Features

- **Stripe Webhook Verification** - Validates webhook signatures using `Stripe\Webhook::constructEvent()` to reject tampered payloads
- **Local Product Catalog** - Seeded SQLite catalog. Stripe line items are matched to catalog products via `metadata.sku` (recommended) or by product name as a fallback
- **AI Recommendations** - OpenAI picks complementary products from the remaining catalog. Each recommendation includes a short reason tied to the customer's purchase
- **HTML + Text Email** - Inline Blade templates render both the order details and the recommendations section
- **Mailtrap Category Header** - Every outgoing email carries the configured `X-MT-Category` value so you can filter analytics in the Mailtrap dashboard
- **Graceful Degradation** - If OpenAI fails, the order confirmation is still sent without the recommendations section. If mail delivery fails, the webhook returns 500 so Stripe retries
- **Duplicate Handling** - Caches processed event IDs for 24 hours to handle Stripe webhook retries

## Prerequisites

- PHP 8.3+
- [Composer](https://getcomposer.org/)
- [Mailtrap account](https://mailtrap.io) with a verified sending domain
- [OpenAI API key](https://platform.openai.com/api-keys)
- [Stripe account](https://dashboard.stripe.com) in test mode
- [Stripe CLI](https://stripe.com/docs/stripe-cli) for local webhook forwarding

## Setup

1. **Clone and install**

```bash
git clone https://github.com/gaalferov/laravel-order-recommendations-ai.git
cd laravel-order-recommendations-ai
composer install
```

2. **Configure environment**

```bash
cp .env.example .env
php artisan key:generate
php artisan vendor:publish --provider="OpenAI\Laravel\ServiceProvider"
```

3. **Set your API keys** in `.env`

```
MAILTRAP_API_KEY=your_mailtrap_api_key
MAIL_FROM_ADDRESS=orders@yourdomain.com
MAIL_FROM_NAME="Order Recommendations"
# Mailtrap category used to tag outgoing emails for analytics
MAILTRAP_CATEGORY="Order Confirmation"
# Fallback recipient when the Stripe session has no customer email
MAIL_ORDER_RECIPIENT=customer@example.com

STRIPE_SECRET_KEY=sk_test_your_stripe_secret_key
STRIPE_WEBHOOK_SECRET=whsec_your_webhook_signing_secret

OPENAI_API_KEY=your_openai_api_key
OPENAI_MODEL=gpt-4o-mini
RECOMMENDATIONS_COUNT=3

# Laravel cache store for the webhook event dedup cache (file avoids a DB migration)
CACHE_STORE=file
```

4. **Create the database and seed the catalog**

```bash
touch database/database.sqlite
php artisan migrate --seed
```

This seeds a sample catalog of 12 coffee-themed products (beans, brewing equipment, accessories) across three categories.

5. **Run the app**

```bash
php artisan serve
```

## Stripe Test Mode Setup

### 1. Create test products that match the catalog

The AI recommender only works when Stripe line items can be matched to a local catalog product. The recommended approach is to set the catalog SKU on each Stripe Product's `metadata`:

```bash
stripe products create \
  --name="Ethiopian Yirgacheffe, 250g" \
  --metadata[sku]=BEAN-ETH-250
```

Then attach a Price to that Product and use it in a Checkout Session. If `metadata.sku` is not set, the app falls back to matching by product name.

### 2. Install and configure Stripe CLI

```bash
# Install (macOS)
brew install stripe/stripe-cli/stripe

# Login to your Stripe account
stripe login
```

### 3. Forward webhooks to your local app

```bash
stripe listen --forward-to localhost:8000/api/stripe/webhook
```

Copy the printed webhook signing secret (`whsec_...`) to your `.env` as `STRIPE_WEBHOOK_SECRET`.

### 4. Trigger a test event

```bash
stripe trigger checkout.session.completed
```

Or create a real Checkout Session via the Stripe Dashboard or API and complete it with [test card `4242 4242 4242 4242`](https://stripe.com/docs/testing#cards).

## Product Catalog

The seeded catalog lives in [`database/seeders/ProductSeeder.php`](database/seeders/ProductSeeder.php) - edit or replace it to match your own store. Each product has:

| Field | Description |
|---|---|
| `sku` | Unique catalog identifier (also used to match Stripe line items) |
| `name` | Display name |
| `description` | One-sentence description used by the AI for context |
| `price_cents` | Integer price in minor units |
| `currency` | ISO 4217 code (defaults to USD) |
| `category` | Free-form category string for grouping |
| `tags` | JSON array of tags the AI uses when picking complementary items |

To re-seed after changes:

```bash
php artisan migrate:fresh --seed
```

## Project Structure

```
app/
  Http/Controllers/
    StripeWebhookController.php    # Webhook handler: verify, dedup, assemble order
  Models/
    Product.php                    # Catalog model
  Services/
    ProductRecommender.php         # OpenAI-backed recommendations
    OrderConfirmationMailer.php    # Sends HTML + text via Mailtrap SDK
config/
  mail.php                         # Mailtrap mailer config
  order.php                        # Fallback recipient
  recommendations.php              # AI model, count
  services.php                     # Mailtrap API key and category
  stripe.php                       # Stripe API keys
  database.php                     # SQLite connection
database/
  migrations/                      # products table
  seeders/                         # sample catalog
resources/views/emails/
  order_confirmation.blade.php     # HTML email template
  order_confirmation_text.blade.php # Plain-text version
routes/
  api.php                          # POST /api/stripe/webhook
```

## Key Integration Points

### Matching Stripe Line Items to Catalog Products

The controller expands `line_items.data.price.product` when retrieving the session, then resolves each item to a local `Product`:

```php
$stripeProduct = $lineItem->price->product ?? null;

if (is_object($stripeProduct)) {
    $sku = $stripeProduct->metadata->sku ?? null;

    if ($sku) {
        $product = Product::where('sku', $sku)->first();
        if ($product) {
            return $product;
        }
    }

    if (! empty($stripeProduct->name)) {
        return Product::where('name', $stripeProduct->name)->first();
    }
}

return null;
```

Only resolved products are sent to the AI - unknown items still appear in the email (by Stripe description), they just don't inform the recommendations.

### AI Product Recommendations

The `ProductRecommender` service sends OpenAI two things:
- The purchased items (sku, name, category, tags)
- The remaining catalog (everything the customer didn't buy)

It asks for N recommendations and validates that each returned SKU exists in the catalog - the AI can't invent products:

```php
foreach ($decoded['recommendations'] as $rec) {
    if (! isset($rec['sku']) || ! $catalogBySku->has($rec['sku'])) {
        continue;  // Silently drop anything the AI made up
    }
    $recommendations[] = [...];
}
```

### Mailtrap Category Header

Because this repo needs dynamic AI content inside the email body, it uses inline HTML (Blade) instead of the Mailtrap template API. That lets us set a category via the SDK:

```php
$email = (new MailtrapEmail())
    ->from(new Address(config('mail.from.address'), config('mail.from.name')))
    ->to(new Address($recipientEmail))
    ->subject($subject)
    ->html($html)
    ->text($text)
    ->category(config('services.mailtrap.category', 'Order Confirmation'));
```

The category appears as an `X-MT-Category` header on the outgoing email and shows up in Mailtrap's analytics dashboard.

### Error Handling

- **Invalid signature** - returns `400`, logged as warning
- **Duplicate event** - returns `200` with `"already processed"` status
- **Stripe API failure** - returns `500` so Stripe retries; event NOT cached
- **OpenAI failure** - logged at warning; email is sent without the recommendations section
- **Mail delivery failure** - returns `500` so Stripe retries; event NOT cached

## Links

- [Mailtrap Email API docs](https://mailtrap.io/email-api)
- [Mailtrap PHP SDK](https://github.com/railsware/mailtrap-php)
- [OpenAI PHP for Laravel](https://github.com/openai-php/laravel)
- [Stripe Webhooks guide](https://stripe.com/docs/webhooks)
- [Stripe Checkout](https://stripe.com/docs/payments/checkout)
- [Stripe CLI](https://stripe.com/docs/stripe-cli)

## License

MIT
