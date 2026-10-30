<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function getCategory (Request $request){
         if ($request->user()?->role !== 'admin') {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        try {
            $categories = DB::select('SELECT id,name FROM categories WHERE is_active = 1');
        
            return response()->json([
                  'status' => true,
                   'data' => $categories
            ]);

        } catch (\Throwable $th) {
            report($th);

            return response()->json([
                'status' => false,
                'message' => 'Unable to retrieve category.',
            ], 500);
        }

    }
}
