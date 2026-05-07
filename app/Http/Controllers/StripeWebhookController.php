<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\OrderConfirmationMailer;
use App\Services\ProductRecommender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookController
{
    private const ZERO_DECIMAL_CURRENCIES = [
        'bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw',
        'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf',
    ];

    public function __construct(
        private readonly OrderConfirmationMailer $mailer,
        private readonly ProductRecommender $recommender,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                $request->header('Stripe-Signature', ''),
                config('stripe.webhook_secret'),
            );
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Invalid signature'], 400);
        } catch (\UnexpectedValueException $e) {
            Log::warning('Stripe webhook payload invalid', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Invalid payload'], 400);
        }

        $cacheKey = 'stripe_event_'.$event->id;

        if (Cache::has($cacheKey)) {
            Log::info('Stripe webhook duplicate event skipped', ['event_id' => $event->id]);

            return response()->json(['status' => 'already processed']);
        }

        if ($event->type === 'checkout.session.completed') {
            try {
                $this->handleCheckoutSessionCompleted($event);
            } catch (\Throwable $e) {
                Log::error('Failed to process checkout session', [
                    'event_id' => $event->id,
                    'error' => $e->getMessage(),
                ]);

                return response()->json(['error' => 'Processing failed'], 500);
            }
        }

        Cache::put($cacheKey, true, now()->addHours(24));

        return response()->json(['status' => 'ok']);
    }

    private function handleCheckoutSessionCompleted(Event $event): void
    {
        $session = Session::retrieve([
            'id' => $event->data->object->id,
            'expand' => ['line_items.data.price.product'],
        ]);

        $customerEmail = $session->customer_details?->email
            ?? config('order.recipient_email');

        if (! $customerEmail) {
            Log::warning('Checkout session has no customer email', [
                'session_id' => $session->id,
            ]);

            return;
        }

        $items = [];
        $purchasedForAi = [];

        foreach ($session->line_items?->data ?? [] as $lineItem) {
            $items[] = [
                'name' => $lineItem->description,
                'quantity' => $lineItem->quantity,
                'price' => $this->formatAmount($lineItem->amount_total, $lineItem->currency),
            ];

            $product = $this->resolveCatalogProduct($lineItem);

            if ($product) {
                $purchasedForAi[] = [
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'category' => $product->category,
                    'tags' => $product->tags ?? [],
                ];
            }
        }

        $shipping = $session->shipping_details;

        $orderData = [
            'order_id' => $session->id,
            'items' => $items,
            'total' => $this->formatAmount($session->amount_total, $session->currency),
            'shipping_address' => $shipping ? [
                'name' => $shipping->name ?? '',
                'line1' => $shipping->address->line1 ?? '',
                'line2' => $shipping->address->line2 ?? '',
                'city' => $shipping->address->city ?? '',
                'state' => $shipping->address->state ?? '',
                'postal_code' => $shipping->address->postal_code ?? '',
                'country' => $shipping->address->country ?? '',
            ] : null,
            'recommendations' => [],
        ];

        $count = (int) config('recommendations.count', 3);
        if ($count > 0 && $purchasedForAi) {
            try {
                $orderData['recommendations'] = $this->recommender->recommend($purchasedForAi, $count);
            } catch (\Exception $e) {
                Log::warning('AI recommendations unavailable, sending confirmation without them', [
                    'session_id' => $session->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Sending order confirmation email', [
            'session_id' => $session->id,
            'customer_email' => $customerEmail,
            'items_count' => count($items),
            'recommendations_count' => count($orderData['recommendations']),
        ]);

        $this->mailer->send($customerEmail, $orderData);
    }

    /**
     * Resolve a Stripe line item to a local catalog Product.
     *
     * Uses Stripe Product metadata `sku` if set (recommended), otherwise
     * falls back to matching by the product name.
     */
    private function resolveCatalogProduct(object $lineItem): ?Product
    {
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
    }

    private function formatAmount(int $amount, string $currency): string
    {
        $upper = strtoupper($currency);

        if (in_array(strtolower($currency), self::ZERO_DECIMAL_CURRENCIES, true)) {
            return $upper.' '.number_format($amount, 0);
        }

        return $upper.' '.number_format($amount / 100, 2);
    }
}
