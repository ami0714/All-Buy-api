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
}
