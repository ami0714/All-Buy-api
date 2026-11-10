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
          $setWhereClause = [];
          $bindings = ['hitpay'];
           if ($request->user()?->role === 'admin') {
            $setWhereClause[] = ' o.id = ?';
            $bindings[1] = $order_id;
           }else {

            $setWhereClause[] = ' o.user_id = ? AND o.id = ?';
            $bindings[1] = $request->user()->id;
            $bindings[2] = $order_id;
           }

            $order = DB::selectOne(
                'SELECT
                    o.id AS order_id,
                    o.address_snapshot,
                    o.order_number,
                    o.subtotal,
                    o.total_amount,
                    o.order_date AS order_date,
                    o.status AS order_status,
                    p.status AS payment_status,
                    sh.status AS shipping_status,
                    sm.service_name AS shipping_method,
                    sm.price AS shipping_fee,
                    sm.estimated_days AS estimated_delivery_days,
                    courier.name AS courier_name,
                    courier.id AS courier_id,
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
                            "image_url", pr.image_url,
                            "sku", pv.sku
                        )
                    ) AS product_item,
                    py.gateway AS payment_gateway,
                    py.billcode AS payment_bill,
                    py.reference_no AS payment_reference,
                    py.amount AS payment_amount,
                    py.paid_at AS payment_paid_at,
                    py.raw_response AS payment_raw_response,
                                        (
                                SELECT JSON_ARRAYAGG(
                                    JSON_OBJECT(
                                        "status", ohs.status,
                                        "changed_at", ohs.created_at,
                                        "note", ohs.note
                                    )
                                )
                                FROM order_status_history ohs 
                                WHERE ohs.order_id = o.id 
                                ORDER BY ohs.created_at DESC LIMIT 1
                            ) AS order_history_status
                                FROM orders o
                INNER JOIN order_items oi ON o.id = oi.order_id
                LEFT JOIN order_status_history ohs ON o.id = ohs.order_id
                LEFT JOIN payments p ON p.order_id = o.id AND p.gateway = ?
                LEFT JOIN shipments sh ON o.id = sh.order_id
                LEFT JOIN shipping_methods sm ON o.shipping_method = sm.id
                LEFT JOIN couriers courier ON sm.courier_id = courier.id
                LEFT JOIN product_variants pv ON oi.variant_id = pv.id
                LEFT JOIN products pr ON pv.product_id = pr.id
                LEFT JOIN payments py ON o.id = py.order_id
                WHERE  ' . implode(' AND ', $setWhereClause) . '
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
                    sm.price,
                    sm.service_name,
                    py.raw_response,
                    py.gateway,
                    py.billcode,
                    py.reference_no,
                    py.amount,
                    py.paid_at
                    
                ',
                $bindings
            );

            if (!$order) {
                return response()->json([
                    'status' => false,
                    'message' => 'Order not found.',
                ], 404);
            }
            $order->address_snapshot = json_decode($order->address_snapshot, true, 512, JSON_THROW_ON_ERROR);
            $order->shipping_fee = (float) $order->shipping_fee;
            $order->payment_raw_response = json_decode($order->payment_raw_response, true, 512, JSON_THROW_ON_ERROR);
            $order->total_amount = (float) $order->total_amount;
            $order->product_item = json_decode($order->product_item, true, 512, JSON_THROW_ON_ERROR);
            $order->order_history_status = json_decode($order->order_history_status, true, 512, JSON_THROW_ON_ERROR);
            return response()->json([
                'status' => true,
                'data' => $order,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => false,
                'message' => 'Unable to retrieve order details.'. $exception->getLine(),
            ], 500);
        }
    }

    public function receivedOrder(Request $request, $orderId): JsonResponse
    {
        try {
            $isOrderExists = DB::selectOne('SELECT id FROM orders WHERE id = ?', [$orderId]);
            if (!$isOrderExists) {
                return response()->json([
                    'status' => false,
                    'message' => 'Order not found.',
                ], 404);
            }

            DB::transaction(function () use ($orderId) {
                DB::update('UPDATE orders SET status = ? WHERE id = ?', ['completed', $orderId]);
                DB::insert('INSERT INTO order_status_history (order_id, status, note) VALUES (?, ?, ?)', [$orderId, 'completed', 'Order has been received by the customer.']);
            });

            return response()->json([
                'status' => true,
                'message' => 'Order marked as received.',
            ]);
        } catch (\Throwable $th) {
            report($th);

            return response()->json([
                'status' => false,
                'message' => 'Unable to mark order as received.'. $th->getMessage(),
            ], 500);
        }
    }


    //admin function

    public function getAdminState(Request $request): JsonResponse
    {
        if ($request->user()?->role !== 'admin') {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        try {
            $stats = DB::selectOne('SELECT
                COUNT(*) AS total_orders,
                SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) AS pending_orders,
                SUM(CASE WHEN status = "prepare order" THEN 1 ELSE 0 END) AS prepare_order,
                SUM(CASE WHEN status = "paid" THEN 1 ELSE 0 END) AS paid_orders,
                SUM(CASE WHEN status = "shipped" THEN 1 ELSE 0 END) AS shipped_orders,
                SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) AS completed_orders
            FROM orders');
            $stats->total_orders = (int) $stats->total_orders;
            $stats->pending_orders = (int) $stats->pending_orders;
            $stats->prepare_order = (int) $stats->prepare_order;
            $stats->paid_orders = (int) $stats->paid_orders;
            $stats->shipped_orders = (int) $stats->shipped_orders;
            $stats->completed_orders = (int) $stats->completed_orders;


            return response()->json([
                'status' => true,
                'data' => $stats,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => false,
                'message' => 'Unable to retrieve order statistics.',
            ], 500);
        }
    }

    public function getOrdersAdmin(Request $request) :JsonResponse{
      
    if ($request->user()?->role !== 'admin') {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        $filter = $request->query('status', 'ALL');

        $matchedFilter = match (strtoupper($filter)) {
            'ALL' => '1=1',
            'PENDING' => "o.status = 'pending'",
            'PREPARE ORDER' => "o.status = 'prepare order'",
            'PAID' => "o.status = 'paid'",
            'SHIPPED' => "o.status = 'shipped'",
            'COMPLETED' => "o.status = 'completed'",
            default => null,
        };

        try {
              
          $orders = DB::select('SELECT
                            o.id,
                            o.order_number,
                            u.username,
                            u.email,
                            o.order_date,
                            o.total_amount,
                            o.status
                            FROM orders o
                            JOIN users u ON o.user_id = u.id
                            WHERE ' . $matchedFilter . '
                            ORDER BY o.order_date DESC
                            ');
          
               return response()->json([
                'status' => true,
                'data' => $orders,
            ]);

        } catch (Throwable $exception) {
             report($exception);

            return response()->json([
                'status' => false,
                'message' => 'Unable to retrieve order data.',
            ], 500);
        }
    




    }

    public function updateOrderStatus(Request $request, $orderId): JsonResponse
    {
        if ($request->user()?->role !== 'admin') {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validated = $request->validate([
            'orderStatus' => ['sometimes','required', 'string', 'in:pending,prepare order,paid,shipped,completed'],
            'tracking_number' => ['sometimes','nullable', 'string'],
            'courier_id' => ['sometimes','nullable', 'integer', 'exists:couriers,id'],
        ]);

        $note = match ($validated['orderStatus'] ?? null) {
            'pending' => 'Order is pending.',
            'paid' => 'Order has been paid.',
            'prepare order' => 'Order is being prepared.',
            'shipped' => 'Order has been shipped.',
            'completed' => 'Order has been completed.',
            default => null,
        };

        try {

            $isOrderExists = DB::selectOne('SELECT id FROM orders WHERE id = ?', [$orderId]);
            if (!$isOrderExists) {
                return response()->json([
                    'status' => false,
                    'message' => 'Order not found.',
                ], 404);
            }
                       
          DB::transaction(function () use ($orderId, $validated, $note) {
                if (isset($validated['orderStatus'])) {
                    DB::update('UPDATE orders SET status = ? WHERE id = ?', [$validated['orderStatus'], $orderId]);
                    DB::insert('INSERT INTO order_status_history (order_id, status, note) VALUES (?, ?, ?)', [$orderId, $validated['orderStatus'], $note]);
                }
                if (!DB::selectOne('SELECT id FROM shipments WHERE order_id = ?', [$orderId]) && $validated['orderStatus'] === 'shipped' && $validated['orderStatus'] !== 'prepare order' ) {
                      $shipmentStatus = match ($validated['orderStatus'] ?? null) {
                        'prepare order' => 'pending',
                        'shipped' => 'shipped',
                        default => 'pending',    
                      };
                      
                    DB::insert(
                            'INSERT INTO shipments (order_id, courier_id, status, tracking_number, updated_at)
                            VALUES (?, ?, ?, ?, NOW())',
                            [
                                $orderId,
                                $validated['courier_id'] ?? null,
                                $shipmentStatus,
                                $validated['tracking_number'] ?? null,
                            ]
                        );
                }

                if (isset($validated['tracking_number']) && DB::selectOne('SELECT id FROM shipments WHERE order_id = ?', [$orderId])) {
                    DB::update('UPDATE shipments SET tracking_number = ? WHERE order_id = ?', [$validated['tracking_number'], $orderId]);
                }
            });

            return response()->json([
                'status' => true,
                'message' => 'Order status updated successfully.',
            ]);


        } catch (\Throwable $th) {
            report($th);

            return response()->json([
                'status' => false,
                'message' => 'Unable to update order status.'. $th->getMessage(),
            ], 500);
        }

        
    }
}
