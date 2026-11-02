<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
class VariantController extends Controller
{
    public function getVariantsById(Request $request,$product_id):JsonResponse{

         if ($request->user()?->role !== 'admin') {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden.',
            ], 403);
        }


         try {
               
            $variants = DB::select("
                 SELECT 
                 id,
                 sku,
                 size,
                 color,
                 price,
                 stock
                 FROM product_variants
                 WHERE product_id = ?
             ",[$product_id]);

            return response()->json([
                'status' => true,
                'data' => $variants,
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => false,
                'message' => 'Unable to get Varinats.',
            ], 500);
        }


    }

    public function updateVariant(Request $request, $variant_id): JsonResponse
    {
        if ($request->user()?->role !== 'admin') {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validated = $request->validate([
            'sku' => ['sometimes', 'required', 'string', 'max:255'],
            'size' => ['sometimes','required', 'string', 'max:255'],
            'color' => ['sometimes','required', 'string', 'max:255'],
            'price' => ['sometimes','required', 'numeric'],
            'stock' => ['sometimes', 'required', 'integer'],
        ]);

        try {

              $fieldMappings = [
            'sku' => 'sku',
            'size' => 'size',
            'color' => 'color',
            'price' => 'price',
            'stock' => 'stock',
        ];

        $setClauses = [];
        $values = [];

        foreach ($fieldMappings as $requestField => $dbField) {
           if(!array_key_exists($requestField, $validated)) {
                continue; // Skip if the field is not present in the validated data
            }

            if (isset($validated[$requestField])) {
                $setClauses[] = "$dbField = ?";
                $values[] = $validated[$requestField];
            }
        }

        $setClauses[] = 'updated_at = CURRENT_TIMESTAMP';
        $values[] = $variant_id;
               

            DB::update("
                UPDATE product_variants
                SET ".implode(', ', $setClauses)."
                WHERE id = ?
            ",$values );

            return response()->json([
                'status' => true,
                'message' => 'Variant updated successfully.',
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => false,
                'message' => 'Unable to update variant.',
            ], 500);
        }
    }




     public function addVariant(Request $request, $product_id): JsonResponse
    {
        if ($request->user()?->role !== 'admin') {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validated = $request->validate([
            'sku' => [ 'required', 'string', 'max:255'],
            'size' => ['required', 'string', 'max:255'],
            'color' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric'],
            'stock' => [ 'required', 'integer'],
        ]);

        try {



            DB::insert("
                INSERT INTO product_variants (product_id, sku, size, color, price, stock, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ", [$product_id, $validated['sku'], $validated['size'], $validated['color'], $validated['price'], $validated['stock']]);    


        
        

            return response()->json([
                'status' => true,
                'message' => 'Variant added successfully.',
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => false,
                'message' => 'Unable to update variant. Error: '.$exception->getMessage(),
            ], 500);
        }
    }
}
