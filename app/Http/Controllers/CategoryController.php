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


    public function getCategoryForAdmin(Request $request){
        if ($request->user()?->role !== 'admin') {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        try {
            $categories = DB::select('SELECT id,name,slug,description,is_active FROM categories');
        
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


    public function addCategory(Request $request){
        if ($request->user()?->role !== 'admin') {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:categories,slug'],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
        ]);

        try {
            DB::insert('INSERT INTO categories (name, slug, description, is_active) VALUES (?, ?, ?, ?)', [
                $validated['name'],
                $validated['slug'],
                $validated['description'] ?? null,
                $validated['is_active'],
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Category added successfully.',
            ]);
        } catch (\Throwable $th) {
            report($th);

            return response()->json([
                'status' => false,
                'message' => 'Unable to add category.',
            ], 500);
        }
    }

    public function updateCategory(Request $request, $categoryId){
        if ($request->user()?->role !== 'admin') {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['sometimes','required', 'string', 'max:255'],
            'slug' => ['sometimes','required', 'string', 'max:255', "unique:categories,slug,$categoryId"],
            'description' => ['sometimes','nullable', 'string'],
            'is_active' => ['sometimes','required', 'boolean'],
        ]);

        try {
         
            $setClause = [];
            $bindings = [];

            $fields = ['name', 'slug', 'description', 'is_active'];
            foreach ($fields as $field) {
                if (array_key_exists($field, $validated)) {
                    $setClause[] = "$field = ?";
                    $bindings[] = $validated[$field];
                }
            }
                  
            DB::update('UPDATE categories SET ' . implode(', ', $setClause) . ' WHERE id = ?', array_merge($bindings, [$categoryId]));
            

            return response()->json([
                'status' => true,
                'message' => 'Category updated successfully.',
            ]);
        } catch (\Throwable $th) {
            report($th);

            return response()->json([
                'status' => false,
                'message' => 'Unable to update category.',
            ], 500);
        }
    }
}
