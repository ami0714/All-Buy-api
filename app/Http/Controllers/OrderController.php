<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class OrderController extends Controller
{
    public function getAllUserOrder(Request $request): JsonResponse
    {
        try {
            $orders = DB::select(
                'SELECT
                    o.id AS order_id,
                    o.order_number,
                    o.subtotal,
                    o.total_amount,
                    o.status AS order_status,
                    p.status AS payment_status,
                    sh.status AS shipping_status,
                    sh.tracking_number AS tracking_number,
                    JSON_UNQUOTE(JSON_EXTRACT(p.raw_response, "$.url")) AS checkout_url,
                    JSON_ARRAYAGG(
                        JSON_OBJECT(
                            "variant_id", oi.variant_id,
                            "product_name", oi.product_name_at_purchase,
                            "variant_info", oi.variant_info_at_purchase,
                            "quantity", oi.quantity,
                            "subtotal", oi.subtotal
                        )
                    ) AS product_item
                FROM orders o
                INNER JOIN order_items oi ON o.id = oi.order_id
                LEFT JOIN payments p ON p.order_id = o.id AND p.gateway = ?
                LEFT JOIN shipments sh ON o.id = sh.order_id
                WHERE o.user_id = ?
                GROUP BY
                    o.id,
                    o.order_number,
                    o.subtotal,
                    o.total_amount,
                    o.status,
                    p.status,
                    p.raw_response,
                    sh.status,
                    sh.tracking_number
                ORDER BY o.order_date DESC, o.id DESC',
                ['hitpay', $request->user()->id]
            );

            foreach ($orders as $order) {
                $order->product_item = json_decode($order->product_item, true, 512, JSON_THROW_ON_ERROR);
            }

            return response()->json([
                'status' => true,
                'data' => $orders,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => false,
                'message' => 'Unable to retrieve orders.',
            ], 500);
        }
    }
}
