<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_requires_an_admin_user(): void
    {
        $user = $this->createUser('user');

        $this->actingAs($user)
            ->getJson('/api/admin/dashboard')
            ->assertForbidden()
            ->assertJson([
                'status' => false,
                'message' => 'Forbidden.',
            ]);
    }

    public function test_admin_dashboard_returns_summary_charts_recent_orders_and_at_most_ten_products(): void
    {
        $admin = $this->createUser('admin');
        $customer = $this->createUser('user');

        DB::table('orders')->insert([
            'order_number' => 'ORD-PAID-001',
            'user_id' => $customer->id,
            'address_snapshot' => '{}',
            'subtotal' => 20,
            'total_amount' => 20,
            'status' => 'paid',
            'order_date' => now(),
        ]);
        DB::table('orders')->insert([
            'order_number' => 'ORD-PENDING-001',
            'user_id' => $customer->id,
            'address_snapshot' => '{}',
            'subtotal' => 15,
            'total_amount' => 15,
            'status' => 'pending',
            'order_date' => now(),
        ]);

        for ($index = 1; $index <= 11; $index++) {
            DB::table('products')->insert([
                'name' => "Product {$index}",
                'slug' => "product-{$index}",
                'default_price' => 10,
                'is_active' => $index <= 9,
                'created_at' => now()->addSeconds($index),
            ]);
        }

        $this->actingAs($admin)
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.summary.total_revenue', 20)
            ->assertJsonPath('data.summary.orders_needing_action', 1)
            ->assertJsonPath('data.summary.registered_users', 1)
            ->assertJsonPath('data.summary.active_products', 9)
            ->assertJsonPath('data.summary.inactive_products', 2)
            ->assertJsonPath('data.summary.total_orders', 2)
            ->assertJsonPath('data.order_status.0.value', 1)
            ->assertJsonPath('data.order_status.1.value', 1)
            ->assertJsonPath('data.recent_orders.0.id', 'ORD-PENDING-001')
            ->assertJsonCount(7, 'data.sales_performance')
            ->assertJsonCount(10, 'data.products');
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->getJson('/api/admin/dashboard')->assertUnauthorized();
    }

    private function createUser(string $role): User
    {
        static $counter = 0;
        $counter++;
        $uid = str_pad((string) $counter, 8, '0', STR_PAD_LEFT);
        $email = "dashboard-user-{$counter}@example.com";

        DB::insert(
            'INSERT INTO users (uid, username, email, password, role) VALUES (?, ?, ?, ?, ?)',
            [$uid, "dashboard-user-{$counter}", $email, 'password', $role]
        );

        return User::query()->where('email', $email)->firstOrFail();
    }
}
