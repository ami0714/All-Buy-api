<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShippingControllerTest extends TestCase
{
    public function test_it_returns_couriers_and_decodes_the_raw_query_shipping_methods(): void
    {
        DB::shouldReceive('select')
            ->once()
            ->withArgs(function (string $query): bool {
                return preg_replace('/\s+/', ' ', trim($query)) === 'SELECT c.id, c.code, c.name, c.tracking_url_template, JSON_ARRAYAGG( JSON_OBJECT( "id", sm.id, "service_name", sm.service_name, "price", sm.price, "estimated_days", sm.estimated_days ) ) AS shipping_methods FROM couriers c JOIN shipping_methods sm ON c.id = sm.courier_id WHERE c.is_active = 1 AND sm.is_active = 1 GROUP BY c.id, c.code, c.name, c.tracking_url_template';
            })
            ->andReturn([
                (object) [
                    'id' => 1,
                    'code' => 'jnt',
                    'name' => 'J&T Express',
                    'tracking_url_template' => 'https://tracking.example/{tracking_number}',
                    'shipping_methods' => '[{"id":12,"service_name":"Standard","price":5.5,"estimated_days":"3-5 days"}]',
                ],
            ]);

        $response = $this->getJson('/api/shipping');

        $response
            ->assertOk()
            ->assertExactJson([
                'status' => true,
                'data' => [[
                    'id' => 1,
                    'code' => 'jnt',
                    'name' => 'J&T Express',
                    'tracking_url_template' => 'https://tracking.example/{tracking_number}',
                    'shipping_methods' => [[
                        'id' => 12,
                        'service_name' => 'Standard',
                        'price' => 5.5,
                        'estimated_days' => '3-5 days',
                    ]],
                ]],
            ]);
    }
}
