<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function getAllProduct(): JsonResponse
    {
        try {
            $products = DB::select(
                'SELECT
                    p.id,
                    p.category_id,
                    p.name,
                    p.slug,
                    p.description,
                    p.default_price,
                    p.image_url,
                    p.is_active,
                    MIN(pv.price) AS max_price,
                    MAX(pv.price) AS min_price
                FROM products p
                LEFT JOIN product_variants pv ON p.id = pv.product_id
                 WHERE is_active = 1 GROUP BY 
                 p.id'
            );

            return response()->json([
                'status' => true,
                'data' => $products,
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => false,
                'message' => 'Unable to retrieve products.',
            ], 500);
        }
    }    

    public function getDetailProduct(string $slug): JsonResponse
    {
        try {
            $product = DB::selectOne(
                'SELECT * FROM products WHERE slug = ? AND is_active = 1 LIMIT 1',
                [$slug]
            );

            if ($product === null) {
                return response()->json([
                    'status' => false,
                    'message' => 'Product not found.',
                ], 404);
            }

            $product->variants = DB::select(
                'SELECT * FROM product_variants WHERE product_id = ? ORDER BY id',
                [$product->id]
            );

            return response()->json([
                'status' => true,
                'data' => $product,
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => false,
                'message' => 'Unable to retrieve product.',
            ], 500);
        }
    }
}
