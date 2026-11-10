<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShippingMethodCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_shipping_method(): void
    {
        $user = User::factory()->create();
        $courierId = DB::table('couriers')->insertGetId([
            'code' => 'jnt',
            'name' => 'J&T Express',
        ]);

        $response = $this->actingAs($user)->postJson('/api/admin/shipping/method', [
            'courier_id' => $courierId,
            'service_name' => 'Standard',
            'price' => 5.5,
            'estimated_days' => '3-5 days',
            'is_active' => true,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'Shipping method added successfully.');

        $this->assertDatabaseHas('shipping_methods', [
            'courier_id' => $courierId,
            'service_name' => 'Standard',
            'price' => 5.5,
            'estimated_days' => '3-5 days',
            'is_active' => true,
        ]);
    }

    public function test_it_validates_shipping_method_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/admin/shipping/method', [
                'courier_id' => 999,
                'service_name' => str_repeat('a', 101),
                'price' => -1,
                'estimated_days' => str_repeat('a', 51),
                'is_active' => 'invalid',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'courier_id',
                'service_name',
                'price',
                'estimated_days',
                'is_active',
            ]);
    }
}
