<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HitPayControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_a_hitpay_checkout_for_their_cart(): void
    {
        $fixture = $this->createCheckoutFixture();
        $this->configureHitPay();
        Http::fake([
            'https://api.sandbox.hit-pay.com/v1/payment-requests' => Http::response([
                'id' => 'hitpay-request-1',
                'url' => 'https://securecheckout.sandbox.hit-pay.com/payment-request/checkout',
                'status' => 'pending',
            ], 201),
        ]);

        $response = $this->actingAs($fixture['user'])->postJson('/api/user/payment/hitpay', [
            'courier_id' => $fixture['courier_id'],
            'shipping_method_id' => $fixture['shipping_method_id'],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.payment_request_id', 'hitpay-request-1')
            ->assertJsonPath('data.checkout_url', 'https://securecheckout.sandbox.hit-pay.com/payment-request/checkout')
            ->assertJsonPath('data.amount', '30.50')
            ->assertJsonPath('data.currency', 'MYR');
        $this->assertMatchesRegularExpression(
            '/^ORD-\d{8}-[A-F0-9]{16}$/',
            $response->json('data.order_number')
        );

        Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://api.sandbox.hit-pay.com/v1/payment-requests'
            && $request->hasHeader('X-BUSINESS-API-KEY', 'test-hitpay-key')
            && $request->hasHeader('X-Requested-With', 'XMLHttpRequest')
            && $request->hasHeader('Accept', 'application/json')
            && $request->hasHeader('Content-Type', 'application/x-www-form-urlencoded')
            && $request['amount'] === '30.50'
            && $request['currency'] === 'MYR'
            && $request['reference_number'] === $response->json('data.order_number'));

        $order = DB::selectOne(
            'SELECT id, status, total_amount, shipping_method FROM orders WHERE order_number = ?',
            [$response->json('data.order_number')]
        );
        $payment = DB::selectOne(
            'SELECT gateway, billcode, status, amount FROM payments WHERE order_id = ?',
            [$order->id]
        );

        $this->assertSame('pending', $order->status);
        $this->assertSame(30.5, (float) $order->total_amount);
        $this->assertSame((int) $fixture['shipping_method_id'], (int) $order->shipping_method);
        $this->assertSame('hitpay', $payment->gateway);
        $this->assertSame('hitpay-request-1', $payment->billcode);
        $this->assertSame('pending', $payment->status);
        $this->assertSame(30.5, (float) $payment->amount);
        $this->assertSame(1, (int) DB::selectOne('SELECT COUNT(*) AS total FROM cart_items')->total);
    }

    public function test_checkout_rejects_a_shipping_method_that_does_not_belong_to_the_selected_courier(): void
    {
        $fixture = $this->createCheckoutFixture();
        $this->configureHitPay();
        Http::fake();

        $response = $this->actingAs($fixture['user'])->postJson('/api/user/payment/hitpay', [
            'courier_id' => $fixture['courier_id'],
            'shipping_method_id' => $fixture['shipping_method_id'] + 1,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('shipping_method_id');
        $this->assertSame(0, (int) DB::selectOne('SELECT COUNT(*) AS total FROM orders')->total);
        Http::assertNothingSent();
    }

    public function test_hitpay_api_failure_marks_the_payment_failed_and_keeps_cart_items(): void
    {
        $fixture = $this->createCheckoutFixture();
        $this->configureHitPay();
        Http::fake([
            'https://api.sandbox.hit-pay.com/v1/payment-requests' => Http::response([
                'message' => 'Invalid API key',
            ], 401),
        ]);

        $response = $this->actingAs($fixture['user'])->postJson('/api/user/payment/hitpay', [
            'courier_id' => $fixture['courier_id'],
            'shipping_method_id' => $fixture['shipping_method_id'],
        ]);

        $response->assertStatus(502)->assertJsonPath('status', false);
        $payment = DB::selectOne('SELECT status FROM payments');
        $this->assertSame('failed', $payment->status);
        $this->assertSame(1, (int) DB::selectOne('SELECT COUNT(*) AS total FROM cart_items')->total);
    }

    public function test_valid_completed_webhook_marks_payment_paid_decrements_stock_and_clears_purchased_cart_quantity(): void
    {
        $fixture = $this->startCheckout();
        $payload = $this->webhookPayload($fixture, 'completed');

        $this->sendSignedWebhook($payload)
            ->assertOk()
            ->assertJsonPath('message', 'Payment confirmed.');

        $payment = DB::selectOne('SELECT status FROM payments WHERE billcode = ?', ['hitpay-request-1']);
        $order = DB::selectOne('SELECT status FROM orders WHERE order_number = ?', [$fixture['order_number']]);
        $variant = DB::selectOne('SELECT stock FROM product_variants WHERE id = ?', [$fixture['variant_id']]);
        $cartItem = DB::selectOne(
            'SELECT quantity FROM cart_items WHERE user_id = ? AND variant_id = ?',
            [$fixture['user']->id, $fixture['variant_id']]
        );

        $this->assertSame('paid', $payment->status);
        $this->assertSame('paid', $order->status);
        $this->assertSame(3, (int) $variant->stock);
        $this->assertNull($cartItem);

        $this->sendSignedWebhook($payload)->assertOk();
        $variant = DB::selectOne('SELECT stock FROM product_variants WHERE id = ?', [$fixture['variant_id']]);
        $this->assertSame(3, (int) $variant->stock);
    }

    public function test_valid_failed_webhook_marks_payment_failed_without_clearing_cart_or_changing_stock(): void
    {
        $fixture = $this->startCheckout();

        $this->sendSignedWebhook($this->webhookPayload($fixture, 'failed'))
            ->assertOk()
            ->assertJsonPath('message', 'Payment failure recorded.');

        $payment = DB::selectOne('SELECT status FROM payments WHERE billcode = ?', ['hitpay-request-1']);
        $order = DB::selectOne('SELECT status FROM orders WHERE order_number = ?', [$fixture['order_number']]);
        $variant = DB::selectOne('SELECT stock FROM product_variants WHERE id = ?', [$fixture['variant_id']]);
        $cartItem = DB::selectOne(
            'SELECT quantity FROM cart_items WHERE user_id = ? AND variant_id = ?',
            [$fixture['user']->id, $fixture['variant_id']]
        );

        $this->assertSame('failed', $payment->status);
        $this->assertSame('pending', $order->status);
        $this->assertSame(5, (int) $variant->stock);
        $this->assertSame(2, (int) $cartItem->quantity);
    }

    public function test_webhook_with_an_invalid_signature_is_rejected_without_updating_the_payment(): void
    {
        $fixture = $this->startCheckout();
        $payload = $this->webhookPayload($fixture, 'completed');
        $rawPayload = json_encode($payload, JSON_THROW_ON_ERROR);

        $response = $this->call(
            'POST',
            '/api/hitpay/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_HITPAY_SIGNATURE' => 'invalid-signature',
                'HTTP_HITPAY_EVENT_OBJECT' => 'payment_request',
            ],
            $rawPayload
        );

        $response->assertUnauthorized();
        $payment = DB::selectOne('SELECT status FROM payments WHERE billcode = ?', ['hitpay-request-1']);
        $this->assertSame('pending', $payment->status);
    }

    public function test_webhook_with_an_unmatched_amount_is_rejected_without_updating_order_or_payment(): void
    {
        $fixture = $this->startCheckout();
        $payload = $this->webhookPayload($fixture, 'completed');
        $payload['amount'] = '31.50';

        $this->sendSignedWebhook($payload)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Payment details do not match the order.');

        $payment = DB::selectOne('SELECT status FROM payments WHERE billcode = ?', ['hitpay-request-1']);
        $order = DB::selectOne('SELECT status FROM orders WHERE order_number = ?', [$fixture['order_number']]);
        $this->assertSame('pending', $payment->status);
        $this->assertSame('pending', $order->status);
    }

    private function startCheckout(): array
    {
        $fixture = $this->createCheckoutFixture();
        $this->configureHitPay();
        Http::fake([
            'https://api.sandbox.hit-pay.com/v1/payment-requests' => Http::response([
                'id' => 'hitpay-request-1',
                'url' => 'https://securecheckout.sandbox.hit-pay.com/payment-request/checkout',
                'status' => 'pending',
            ], 201),
        ]);

        $response = $this->actingAs($fixture['user'])->postJson('/api/user/payment/hitpay', [
            'courier_id' => $fixture['courier_id'],
            'shipping_method_id' => $fixture['shipping_method_id'],
        ]);
        $response->assertCreated();

        $fixture['order_number'] = $response->json('data.order_number');

        return $fixture;
    }

    private function createCheckoutFixture(): array
    {
        DB::insert(
            'INSERT INTO users (uid, username, email, password, phone, role)
            VALUES (?, ?, ?, ?, ?, ?)',
            ['TEST0001', 'checkout-user', 'checkout@example.com', 'password', '+60123456789', 'user']
        );
        $user = User::query()->where('email', 'checkout@example.com')->firstOrFail();

        DB::insert(
            'INSERT INTO addresses (
                user_id, label, recipient_name, phone, address_line1, city, state, postcode, is_default
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$user->id, 'Home', 'Checkout User', '+60123456789', '1 Jalan Test', 'Kuala Lumpur', 'Kuala Lumpur', '50000', true]
        );
        DB::insert('INSERT INTO categories (name, slug, is_active) VALUES (?, ?, ?)', ['Test', 'test', true]);
        $category = DB::selectOne('SELECT id FROM categories WHERE slug = ?', ['test']);

        DB::insert(
            'INSERT INTO products (category_id, name, slug, default_price, is_active)
            VALUES (?, ?, ?, ?, ?)',
            [$category->id, 'Test Product', 'test-product', '25.00', true]
        );
        $product = DB::selectOne('SELECT id FROM products WHERE slug = ?', ['test-product']);

        DB::insert(
            'INSERT INTO product_variants (product_id, sku, size, color, price, stock)
            VALUES (?, ?, ?, ?, ?, ?)',
            [$product->id, 'TEST-SKU', 'M', 'Black', '12.50', 5]
        );
        $variant = DB::selectOne('SELECT id FROM product_variants WHERE sku = ?', ['TEST-SKU']);

        DB::insert(
            'INSERT INTO cart_items (user_id, variant_id, quantity) VALUES (?, ?, ?)',
            [$user->id, $variant->id, 2]
        );
        DB::insert('INSERT INTO couriers (code, name, is_active) VALUES (?, ?, ?)', ['test-courier', 'Test Courier', true]);
        $courier = DB::selectOne('SELECT id FROM couriers WHERE code = ?', ['test-courier']);

        DB::insert(
            'INSERT INTO shipping_methods (courier_id, service_name, price, estimated_days, is_active)
            VALUES (?, ?, ?, ?, ?)',
            [$courier->id, 'Standard', '5.50', '2-3 days', true]
        );
        $shippingMethod = DB::selectOne(
            'SELECT id FROM shipping_methods WHERE courier_id = ?',
            [$courier->id]
        );

        return [
            'user' => $user,
            'courier_id' => (int) $courier->id,
            'shipping_method_id' => (int) $shippingMethod->id,
            'variant_id' => (int) $variant->id,
        ];
    }

    private function configureHitPay(): void
    {
        Config::set('services.hitpay.api_key', 'test-hitpay-key');
        Config::set('services.hitpay.salt', 'test-hitpay-salt');
        Config::set('services.hitpay.api_url', 'https://api.sandbox.hit-pay.com');
        Config::set('services.hitpay.redirect_url', null);
    }

    private function webhookPayload(array $fixture, string $status): array
    {
        return [
            'id' => 'hitpay-request-1',
            'amount' => '30.50',
            'currency' => 'MYR',
            'status' => $status,
            'reference_number' => $fixture['order_number'],
        ];
    }

    private function sendSignedWebhook(array $payload)
    {
        $rawPayload = json_encode($payload, JSON_THROW_ON_ERROR);

        return $this->call(
            'POST',
            '/api/hitpay/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_HITPAY_SIGNATURE' => hash_hmac('sha256', $rawPayload, 'test-hitpay-salt'),
                'HTTP_HITPAY_EVENT_OBJECT' => 'payment_request',
            ],
            $rawPayload
        );
    }
}
