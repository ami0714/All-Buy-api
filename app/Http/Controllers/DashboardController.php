<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class DashboardController extends Controller
{
    public function getAdminState(Request $request): JsonResponse
    {
        if ($request->user()?->role !== 'admin') {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        try {
            $revenueStatuses = ['paid', 'prepare order', 'shipped', 'completed'];

            $summary = DB::selectOne(
                'SELECT
                    COALESCE(SUM(CASE WHEN status IN (?, ?, ?, ?) THEN total_amount ELSE 0 END), 0) AS total_revenue,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS paid_orders,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS preparing_orders,
                    COUNT(*) AS total_orders,
                    (SELECT COUNT(*) FROM users WHERE role = ?) AS registered_users,
                    (SELECT COUNT(*) FROM products WHERE is_active = 1) AS active_products,
                    (SELECT COUNT(*) FROM products WHERE is_active = 0) AS inactive_products
                FROM orders',
                [...$revenueStatuses, 'paid', 'prepare order', 'user']
            );

            return response()->json([
                'status' => true,
                'data' => $summary
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => false,
                'message' => 'Unable to retrieve dashboard data.',
            ], 500);
        }
    }

    public function getChartData(Request $request): JsonResponse
    {
        if ($request->user()?->role !== 'admin') {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        $period = $request->query('period');
        if (! in_array($period, ['weekly', 'monthly', 'yearly'], true)) {
            return response()->json([
                'status' => false,
                'message' => 'Period must be weekly, monthly, or yearly.',
            ], 422);
        }

        try {
            if ($period === 'weekly') {
                $rows = DB::select(
                    "SELECT DAYOFWEEK(order_date) AS chart_index, SUM(total_amount) AS sales
                    FROM orders
                    WHERE status IN ('paid', 'prepare order', 'shipped', 'completed')
                      AND order_date >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY) 
                      AND order_date < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 7 DAY)
                    GROUP BY DAYOFWEEK(order_date)"
                );//sub tolak ,add tambah
                $labels = ['Isn', 'Sel', 'Rab', 'Kha', 'Jum', 'Sab', 'Ahd'];
                $sales = [];
                foreach ($rows as $row) {
                    //untuk tentukan index untuk chart_index mengikut hari di label
                    $sales[((int) $row->chart_index + 5) % 7] = (float) $row->sales;
                }
            } elseif ($period === 'monthly') {
                $rows = DB::select(
                    "SELECT DAY(order_date) AS chart_index, SUM(total_amount) AS sales
                    FROM orders
                    WHERE status IN ('paid', 'prepare order', 'shipped', 'completed')
                      AND order_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                      AND order_date < DATE_FORMAT(CURDATE(), '%Y-%m-01') + INTERVAL 1 MONTH
                    GROUP BY DAY(order_date)"
                );
                $daysInMonth = CarbonImmutable::now()->daysInMonth;
                $labels = range(1, $daysInMonth);
                $sales = [];
                foreach ($rows as $row) {
                    $sales[(int) $row->chart_index - 1] = (float) $row->sales;
                }
            } else {
                $rows = DB::select(
                    "SELECT MONTH(order_date) AS chart_index, SUM(total_amount) AS sales
                    FROM orders
                    WHERE status IN ('paid', 'prepare order', 'shipped', 'completed')
                      AND order_date >= DATE_FORMAT(CURDATE(), '%Y-01-01')
                      AND order_date < DATE_FORMAT(CURDATE(), '%Y-01-01') + INTERVAL 1 YEAR
                    GROUP BY MONTH(order_date)"
                );
                $labels = ['Jan', 'Feb', 'Mac', 'Apr', 'Mei', 'Jun', 'Jul', 'Ogo', 'Sep', 'Okt', 'Nov', 'Dis'];
                $sales = [];
                foreach ($rows as $row) {
                    //masukkan data row ke dalam sales
                    $sales[(int) $row->chart_index - 1] = (float) $row->sales;
                }
            }

            $chartData = [];
            //masukkan data kedalam cahrtData
            foreach ($labels as $index => $label) {
                $chartData[] = [
                    'name' => $label,
                    //sales dah ade data dengan index dan masa yang betul supaya sesuai dengan label
                    'sales' => round($sales[$index] ?? 0, 2),
                ];
            }

            return response()->json([
                'status' => true,
                'data' => $chartData,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => false,
                'message' => 'Unable to retrieve chart data.',
            ], 500);
        }
    }


    public function getOrderStatus(Request $request) {
        
      if ($request->user()?->role !== 'admin') {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        try {
               
        $rowsOrderStatus = DB::select('SELECT 
     status,
     COUNT(*) AS value   
    FROM orders
    GROUP BY status;');
      
       $orderStatusColors = [
'#cbd5e1', // Kelabu cerah (Slate)
      '#a78bfa', // Ungu cair (Light Violet)
    '#f59e0b', // Kuning/Oren (Amber)
     '#3b82f6', // Biru (Blue)
     '#5b3cc4' // Ungu pekat (Deep Purple)
];
    foreach ($rowsOrderStatus as $index => $status) {
        $status->color = $orderStatusColors[$index];
    }

    return response()->json([
                'status' => true,
                'data' => $rowsOrderStatus,
            ]);





        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => false,
                'message' => 'Unable to retrieve chart data.',
            ], 500);
        }


    }
}
