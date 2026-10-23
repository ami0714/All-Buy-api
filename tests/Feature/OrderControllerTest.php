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
}
