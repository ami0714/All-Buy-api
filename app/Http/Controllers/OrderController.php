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


    public function getUserOrderDetail(Request $request, $order_id): JsonResponse
    {
        try {
            $order = DB::selectOne(
                'SELECT
                    o.id AS order_id,
                    o.address_snapshot,
                    o.order_number,
                    o.subtotal,
                    o.total_amount,
                    o.status AS order_status,
                    p.status AS payment_status,
                    sh.status AS shipping_status,
                    courier.name AS courier_name,
                    sm.price AS shipping_fee,
                    coalesce(sh.tracking_number, "") AS tracking_number,
                    JSON_UNQUOTE(JSON_EXTRACT(p.raw_response, "$.url")) AS checkout_url,
                    JSON_ARRAYAGG(
                        JSON_OBJECT(
                            "variant_id", oi.variant_id,
                            "product_name", oi.product_name_at_purchase,
                            "variant_info", oi.variant_info_at_purchase,
                            "quantity", oi.quantity,
                            "unit_price", oi.price,
                            "subtotal", oi.subtotal,
                            "image_url", pr.image_url
                        )
                    ) AS product_item
                FROM orders o
                INNER JOIN order_items oi ON o.id = oi.order_id
                LEFT JOIN payments p ON p.order_id = o.id AND p.gateway = ?
                LEFT JOIN shipments sh ON o.id = sh.order_id
                LEFT JOIN shipping_methods sm ON o.shipping_method = sm.id
                LEFT JOIN couriers courier ON sm.courier_id = courier.id
                LEFT JOIN product_variants pv ON oi.variant_id = pv.id
                LEFT JOIN products pr ON pv.product_id = pr.id
                WHERE o.user_id = ? AND o.id = ?
                GROUP BY
                    o.id,
                    o.address_snapshot,
                    o.order_number,
                    o.subtotal,
                    o.total_amount,
                    o.status,
                    p.status,
                    p.raw_response,
                    sh.status,
                    sh.tracking_number,
                    courier.name,
                    sm.price',
                ['hitpay', $request->user()->id, $order_id]
            );

            if (!$order) {
                return response()->json([
                    'status' => false,
                    'message' => 'Order not found.',
                ], 404);
            }
            $order->address_snapshot = json_decode($order->address_snapshot, true, 512, JSON_THROW_ON_ERROR);
            $order->shipping_fee = (float) $order->shipping_fee;
            $order->total_amaunt = (float) $order->total_amount;
            $order->product_item = json_decode($order->product_item, true, 512, JSON_THROW_ON_ERROR);

            return response()->json([
                'status' => true,
                'data' => $order,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => false,
                'message' => 'Unable to retrieve order details.'. $exception->getMessage(),
            ], 500);
        }
    }
}
