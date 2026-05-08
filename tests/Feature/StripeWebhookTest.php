<?php

namespace Tests\Feature;

use App\Services\OrderConfirmationMailer;
use App\Services\ProductRecommender;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $webhookSecret = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['stripe.webhook_secret' => $this->webhookSecret]);
        config(['stripe.secret_key' => 'sk_test_fake']);
    }

    private function stripeSignature(string $payload, ?string $secret = null, ?int $timestamp = null): string
    {
        $secret ??= $this->webhookSecret;
        $timestamp ??= time();
        $signedPayload = $timestamp.'.'.$payload;
        $signature = hash_hmac('sha256', $signedPayload, $secret);

        return "t={$timestamp},v1={$signature}";
    }

    private function checkoutSessionPayload(string $eventId = 'evt_test_123', string $sessionId = 'cs_test_456'): string
    {
        return json_encode([
            'id' => $eventId,
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => $sessionId,
                    'object' => 'checkout.session',
                    'amount_total' => 4999,
                    'currency' => 'usd',
                    'customer_details' => ['email' => 'buyer@example.com'],
                ],
            ],
        ]);
    }

    private function nonCheckoutPayload(string $eventId = 'evt_test_789'): string
    {
        return json_encode([
            'id' => $eventId,
            'object' => 'event',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_123',
                    'object' => 'payment_intent',
                ],
            ],
        ]);
    }

    private function mockStripeSessionRetrieve(): void
    {
        $sessionResponse = json_encode([
            'id' => 'cs_test_456',
            'object' => 'checkout.session',
            'amount_total' => 4999,
            'currency' => 'usd',
            'customer_details' => ['email' => 'buyer@example.com'],
            'shipping_details' => null,
            'line_items' => [
                'object' => 'list',
                'data' => [
                    [
                        'id' => 'li_1',
                        'object' => 'item',
                        'description' => 'Ethiopian Yirgacheffe, 250g',
                        'quantity' => 1,
                        'amount_total' => 1800,
                        'currency' => 'usd',
                        'price' => [
                            'id' => 'price_1',
                            'product' => [
                                'id' => 'prod_1',
                                'name' => 'Ethiopian Yirgacheffe, 250g',
                                'metadata' => ['sku' => 'BEAN-ETH-250'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $mockClient = $this->createMock(ClientInterface::class);
        $mockClient->method('request')->willReturnOnConsecutiveCalls(
            [$sessionResponse, 200, []],
        );

        ApiRequestor::setHttpClient($mockClient);
    }

    public function test_missing_signature_returns_400(): void
    {
        $response = $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $this->checkoutSessionPayload());

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Invalid signature']);
    }

    public function test_invalid_signature_returns_400(): void
    {
        $response = $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => 't=123,v1=invalid_signature_here',
            'CONTENT_TYPE' => 'application/json',
        ], $this->checkoutSessionPayload());

        $response->assertStatus(400);
    }

    public function test_valid_signature_for_non_checkout_returns_200(): void
    {
        $payload = $this->nonCheckoutPayload();
        $signature = $this->stripeSignature($payload);

        $response = $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok']);
    }

    public function test_duplicate_event_returns_already_processed(): void
    {
        $eventId = 'evt_dup_test';
        Cache::put("stripe_event_{$eventId}", true, now()->addHours(24));

        $payload = $this->nonCheckoutPayload($eventId);
        $signature = $this->stripeSignature($payload);

        $response = $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'already processed']);
    }

    public function test_checkout_session_dispatches_mailer_with_recommendations(): void
    {
        $this->seed(ProductSeeder::class);
        $this->mockStripeSessionRetrieve();

        $recommender = $this->mock(ProductRecommender::class);
        $recommender->shouldReceive('recommend')
            ->once()
            ->andReturn([
                ['sku' => 'BEAN-COL-500', 'name' => 'Colombian Supremo, 500g', 'description' => 'd', 'price' => 'USD 22.00', 'reason' => 'Try a different roast'],
            ]);

        $mailer = $this->mock(OrderConfirmationMailer::class);
        $mailer->shouldReceive('send')
            ->once()
            ->withArgs(function (string $email, array $orderData) {
                return $email === 'buyer@example.com'
                    && $orderData['order_id'] === 'cs_test_456'
                    && count($orderData['items']) === 1
                    && count($orderData['recommendations']) === 1;
            });

        $payload = $this->checkoutSessionPayload();
        $signature = $this->stripeSignature($payload);

        $response = $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertStatus(200);
    }

    public function test_recommender_failure_still_sends_email_without_recommendations(): void
    {
        $this->seed(ProductSeeder::class);
        $this->mockStripeSessionRetrieve();

        $recommender = $this->mock(ProductRecommender::class);
        $recommender->shouldReceive('recommend')
            ->once()
            ->andThrow(new \RuntimeException('OpenAI down'));

        $mailer = $this->mock(OrderConfirmationMailer::class);
        $mailer->shouldReceive('send')
            ->once()
            ->withArgs(function (string $email, array $orderData) {
                return $orderData['recommendations'] === [];
            });

        $payload = $this->checkoutSessionPayload();
        $signature = $this->stripeSignature($payload);

        $response = $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertStatus(200);
    }

    public function test_mailer_failure_returns_500_with_marker_already_set(): void
    {
        $this->seed(ProductSeeder::class);
        $this->mockStripeSessionRetrieve();

        $recommender = $this->mock(ProductRecommender::class);
        $recommender->shouldReceive('recommend')->once()->andReturn([]);

        $mailer = $this->mock(OrderConfirmationMailer::class);
        $mailer->shouldReceive('send')
            ->once()
            ->andThrow(new \RuntimeException('Mail service down'));

        $eventId = 'evt_mail_fail';
        $payload = $this->checkoutSessionPayload($eventId);
        $signature = $this->stripeSignature($payload);

        $response = $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        // Returns 500 but marker IS set (set-before-process: deliberately blocks
        // automatic retries to avoid double-sending on mid-processing failure).
        $response->assertStatus(500);
        $this->assertTrue(Cache::has("stripe_event_{$eventId}"));
    }
}
