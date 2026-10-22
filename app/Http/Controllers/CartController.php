<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    public function getUserCart(Request $request): JsonResponse
    {
        try {
            $userCart = DB::select(
                'SELECT
                    c.id AS cart_id,
                    c.variant_id,
                    c.quantity,
                    c.added_at,
                    p.id AS product_id,
                    p.name AS product_name,
                    p.slug AS product_slug,
                    p.image_url,
                    pv.sku,
                    pv.size,
                    pv.color,
                    pv.price AS unit_price,
                    pv.price * c.quantity AS total_price,
                    pv.stock
                FROM cart_items c
                INNER JOIN product_variants pv ON c.variant_id = pv.id
                INNER JOIN products p ON pv.product_id = p.id
                WHERE c.user_id = ?
                ORDER BY c.added_at DESC',
                [$request->user()->id]
            );

            return response()->json([
                'status' => true,
                'data' => $userCart,
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => false,
                'message' => 'Unable to retrieve cart.',
            ], 500);
        }
    }

     
    public function AddToCart(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required','integer']
        ]);

        try {
            DB::statement(
                'INSERT INTO cart_items (user_id, variant_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + ?',
                [$request->user()->id, $validated['variant_id'],$validated['quantity'],$validated['quantity']]
            );

            return response()->json([
                'status' => true,
                'message' => 'Product added to cart.',
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => false,
                'message' => 'Unable to add to cart',
            ], 500);
        }
    }

    public function changeQuantity(Request $request, int $cartId): JsonResponse 
    {
        try {

          $newQuantity = $request->input('newQuantity');

          $checkCart = DB::selectOne('SELECT id FROM cart_items WHERE id= ? AND user_id = ?',[$cartId,$request->user()->id]);
              
           if (!$checkCart) {
                return response()->json([
                    'status' => false,
                    'message' => 'Cart item not found.',
                ], 404);
            }

            $changeQuantity = DB::update(
                'UPDATE cart_items 
                   SET quantity = ?
                   WHERE id = ? AND user_id = ?
                 ',
                [$newQuantity,$cartId, $request->user()->id]
            );

            if (!$changeQuantity) {
                return response()->json([
                    'status' => false,
                    'message' => 'Cart item cant delete.',
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Cart item quantity changed.',
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => false,
                'message' => 'Unable to change quantity cart item.'.$exception->getMessage() ,
            ], 500);
        }

    }


    public function deleteItemCart(Request $request, int $cartId): JsonResponse
    {
        try {
            $deleted = DB::delete(
                'DELETE FROM cart_items WHERE id = ? AND user_id = ?',
                [$cartId, $request->user()->id]
            );

            if ($deleted === 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'Cart item not found.',
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Cart item deleted.',
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => false,
                'message' => 'Unable to delete cart item.',
            ], 500);
        }
    }
}
