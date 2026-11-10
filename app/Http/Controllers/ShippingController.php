<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

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

      //admin function
    public function getCourierAdmin(Request $request): JsonResponse
    {
        if ($request->user()?->role !== 'admin') {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden.',
            ], 403);
        }
        try {
            $couriers = DB::select(
                'SELECT
                    id,
                    code,
                    name,
                    tracking_url_template,
                    is_active
                FROM couriers '
            );


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


    public function getAllShippingMethodAdmin(Request $request) : JsonResponse
    {

         if ($request->user()?->role !== 'admin') {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        try {
            $shippingMethods = DB::select(
                'SELECT
                    sm.id,
                    sm.service_name,
                    sm.price,
                    sm.estimated_days,
                    sm.is_active,
                    c.id AS courier_id,
                    c.code AS courier_code,
                    c.name AS courier_name
                FROM shipping_methods sm
                JOIN couriers c ON sm.courier_id = c.id'
            );

            return response()->json([
                'status' => true,
                'data' => $shippingMethods,
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => false,
                'message' => 'Unable to retrieve shipping methods.',
            ], 500);
        }
    }


    public function addNewCourier(Request $request): JsonResponse
    {
            if ($request->user()?->role !== 'admin') {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden.',
            ], 403);
        }
      
        $validated = $request->validate([
            'code' => 'required|string|max:255|unique:couriers,code',
            'name' => 'required|string|max:255',
            'tracking_url_template' => 'nullable|string|max:255',
            'is_active' => 'required|boolean',
        ]);

        try {
            $courierId = DB::table('couriers')->insertGetId($validated);

            return response()->json([
                'status' => true,
                'message' => 'Courier added successfully.',
                'data' => ['id' => $courierId],
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => false,
                'message' => 'Unable to add courier.',
            ], 500);
        }
    }


    public function addNewShippingMethod(Request $request): JsonResponse
    {

    if ($request->user()?->role !== 'admin') {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        $request->mergeIfMissing([
            'is_active'=>1,
        ]);     

        $validated = $request->validate([
            'courier_id' => ['required', 'integer', 'exists:couriers,id'],
            'service_name' => ['required', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'estimated_days' => ['nullable', 'string', 'max:50'],
            'is_active' => ['required', 'boolean'],
        ]);

        try {
            $shippingMethodId = DB::table('shipping_methods')->insertGetId($validated);

            return response()->json([
                'status' => true,
                'message' => 'Shipping method added successfully.',
                'data' => ['id' => $shippingMethodId],
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => false,
                'message' => 'Unable to add shipping method.',
            ], 500);
        }
    }


    public function changeCourierStatus(Request $request,$courier_id):JsonResponse {
       
         if ($request->user()?->role !== 'admin') {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden.',
            ], 403);
        }
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

           try {

           $courier = DB::selectOne('SELECT id FROM couriers WHERE id = ?',[$courier_id]);

           if ($courier == null) {
            return response()->json([
                    'status' => false,
                    'message' => 'courier not found.',
                ], 404);
           }
         
           $newValue = $validated['is_active'] ? 1 : 0;

            DB::update('UPDATE couriers SET is_active = ? WHERE id = ?
                        AND is_active <> ?
            ',[$newValue,$courier_id,$newValue]);

               
              return response()->json([
                'status' => true,
                'message' => 'Product status updated successfully.',
            
            ]);

           } catch (\Throwable $exception) {
             report($exception);

            return response()->json([
                'status' => false,
                'message' => 'Unable to change courier status.',
            ], 500);
           }

    }


    

}
