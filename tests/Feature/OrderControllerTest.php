<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_receives_their_orders_with_product_items_decoded(): void
    {
        DB::insert(
            'INSERT INTO users (uid, username, email, password, role) VALUES (?, ?, ?, ?, ?)',
            ['ORDER001', 'order-user', 'orders@example.com', 'password', 'user']
        );
        $user = User::query()->where('email', 'orders@example.com')->firstOrFail();

        $productItems = [
            [
                'variant_id' => 4,
                'product_name' => 'Test Product',
                'variant_info' => 'M / Black',
                'quantity' => 2,
                'subtotal' => 25.00,
            ],
        ];

        DB::shouldReceive('select')
            ->once()
            ->withArgs(function (string $query, array $bindings) use ($user): bool {
                return str_contains($query, 'JSON_ARRAYAGG')
                    && str_contains($query, 'JSON_EXTRACT')
                    && str_contains($query, 'WHERE o.user_id = ?')
                    && $bindings === ['hitpay', $user->id];
            })
            ->andReturn([
                (object) [
                    'order_id' => 10,
                    'order_number' => 'ORD-TEST-001',
                    'subtotal' => '25.00',
                    'total_amount' => '30.50',
                    'order_status' => 'paid',
                    'payment_status' => 'paid',
                    'shipping_status' => 'pending',
                    'tracking_number' => null,
                    'checkout_url' => 'https://securecheckout.sandbox.hit-pay.com/checkout',
                    'product_item' => json_encode($productItems, JSON_THROW_ON_ERROR),
                ],
            ]);

        $this->actingAs($user)
            ->getJson('/api/user/orders')
            ->assertOk()
            ->assertExactJson([
                'status' => true,
                'data' => [[
                    'order_id' => 10,
                    'order_number' => 'ORD-TEST-001',
                    'subtotal' => '25.00',
                    'total_amount' => '30.50',
                    'order_status' => 'paid',
                    'payment_status' => 'paid',
                    'shipping_status' => 'pending',
                    'tracking_number' => null,
                    'checkout_url' => 'https://securecheckout.sandbox.hit-pay.com/checkout',
                    'product_item' => $productItems,
                ]],
            ]);
    }

    public function test_order_list_requires_authentication(): void
    {
        $this->getJson('/api/user/orders')->assertUnauthorized();
    }

    public function test_order_detail_uses_the_selected_shipping_method_for_courier_details(): void
    {
        DB::insert(
            'INSERT INTO users (uid, username, email, password, role) VALUES (?, ?, ?, ?, ?)',
            ['ORDER002', 'detail-user', 'order-detail@example.com', 'password', 'user']
        );
        $user = User::query()->where('email', 'order-detail@example.com')->firstOrFail();

        DB::shouldReceive('selectOne')
            ->once()
            ->withArgs(function (string $query, array $bindings) use ($user): bool {
                return str_contains($query, 'LEFT JOIN couriers courier ON sm.courier_id = courier.id')
                    && str_contains($query, 'courier.name,')
                    && str_contains($query, 'sm.price')
                    && str_contains($query, 'WHERE o.user_id = ? AND o.id = ?')
                    && $bindings === ['hitpay', $user->id, '10'];
            })
            ->andReturn((object) [
                'order_id' => 10,
                'address_snapshot' => '{"recipient_name":"Test User"}',
                'order_number' => 'ORD-TEST-002',
                'subtotal' => '25.00',
                'total_amount' => '30.50',
                'order_status' => 'paid',
                'payment_status' => 'paid',
                'shipping_status' => null,
                'courier_name' => 'Test Courier',
                'shipping_fee' => '5.50',
                'tracking_number' => '',
                'checkout_url' => null,
                'product_item' => '[]',
            ]);

        $this->actingAs($user)
            ->getJson('/api/user/orders/10')
            ->assertOk()
            ->assertJsonPath('data.courier_name', 'Test Courier')
            ->assertJsonPath('data.shipping_fee', 5.5);
    }
}
