<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ShippingController extends Controller
{
    public function getAllCouriers(): JsonResponse
    {
        try {
            $couriers = DB::select(
                'SELECT
                    c.id,
                    c.code,
                    c.name,
                    c.tracking_url_template,
                    JSON_ARRAYAGG(
                        JSON_OBJECT(
                            "id", sm.id,
                            "service_name", sm.service_name,
                            "price", sm.price,
                            "estimated_days", sm.estimated_days
                        )
                    ) AS shipping_methods
                FROM couriers c
                JOIN shipping_methods sm ON c.id = sm.courier_id
                WHERE c.is_active = 1 AND sm.is_active = 1
                GROUP BY c.id, c.code, c.name, c.tracking_url_template'
            );

            foreach ($couriers as $courier) {
                $courier->shipping_methods = json_decode(
                    $courier->shipping_methods,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
            }

            return response()->json([
                'status' => true,
                'data' => $couriers,
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => false,
                'message' => 'Unable to retrieve shipping options.',
            ], 500);
        }
    }
}
