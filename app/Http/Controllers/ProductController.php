<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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


    //untuk admin
    public function getAllProductList(Request $request): JsonResponse
    {
         
        if ($request->user()?->role !== 'admin') {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden.',
            ], 403);
        }      

        $filters = $request->validate([
            'status' => ['sometimes', 'required', 'string', 'in:all,active,inactive'],
        ]);
        $status = $filters['status'] ?? 'all';
        $statusFilter = match ($status) {
            'active' => 'WHERE p.is_active = 1',
            'inactive' => 'WHERE p.is_active = 0',
            default => '',
        };

        try {
            $products = DB::select(
                "SELECT
                    p.id,
                    p.name,
                    p.image_url,
                    COUNT(pv.id) AS variant_count,
                    c.name AS category,
                    p.default_price,
                    p.is_active,
                    CASE WHEN p.is_active = 1 THEN 'active' ELSE 'inactive' END AS status,
                    p.slug
                FROM products p
                LEFT JOIN product_variants pv ON pv.product_id = p.id
                LEFT JOIN categories c ON c.id = p.category_id
                $statusFilter
                GROUP BY p.id, p.name, p.image_url, c.name, p.default_price, p.is_active, p.slug, p.created_at
                ORDER BY p.created_at DESC"
            );

            return response()->json([
                'status' => true,
                'data' => $products,
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => false,
                'message' => 'Unable to retrieve product list.',
            ], 500);
        }
    }

    public function getAdminProductDetail(Request $request, int $product_id): JsonResponse
    {
        if ($request->user()?->role !== 'admin') {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        try {
            $product = DB::selectOne(
                'SELECT
                    p.id,
                    p.name,
                    p.slug,
                    p.description,
                    p.default_price,
                    c.id AS category_id,
                    c.name AS category,
                    p.is_active,
                    p.image_url,
                    (
                        SELECT COUNT(*)
                        FROM product_variants pv
                        WHERE pv.product_id = p.id
                    ) AS variants_count,
                    (
                        SELECT COALESCE(SUM(pv.stock), 0)
                        FROM product_variants pv
                        WHERE pv.product_id = p.id
                    ) AS total_stock,
                    p.updated_at
                FROM products p
                LEFT JOIN categories c ON c.id = p.category_id
                WHERE p.id = ?
                LIMIT 1',
                [$product_id]
            );

            if ($product === null) {
                return response()->json([
                    'status' => false,
                    'message' => 'Product not found.',
                ], 404);
            }

            $product->default_price = (float) $product->default_price;
            $product->is_active = (bool) $product->is_active;
            $product->variants_count = (int) $product->variants_count;
            $product->total_stock = (int) $product->total_stock;
            $product->last_updated = $product->updated_at === null
                ? null
                : Carbon::parse($product->updated_at) // tukarkan ke format  hari bulan(huruf) tahun
                    ->locale('id')
                    ->translatedFormat('d M Y');
            unset($product->updated_at);

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

    //handle tukar status
    public function changeStatusProduct(Request $request, int $product_id): JsonResponse
    {
        if ($request->user()?->role !== 'admin') {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        try {
           
                $product = DB::selectOne(
                        'SELECT id, is_active FROM products WHERE id = ?',
                        [$product_id]
                    );

            if ($product === null) {
                return response()->json([
                    'status' => false,
                    'message' => 'Product not found.',
                ], 404);
            }

            $newValue = $validated['is_active'] ? 1 : 0;

            DB::update(
                'UPDATE products
                SET is_active = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ? AND is_active <> ?',
                [$newValue, $product_id, $newValue]
            );

            

            return response()->json([
                'status' => true,
                'message' => 'Product status updated successfully.',
                'data' => $product,
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => false,
                'message' => 'Unable to update product status.',
            ], 500);
        }
    }

    public function updateProductDetail(Request $request, int $product_id): JsonResponse
    {
        if ($request->user()?->role !== 'admin') {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:300',
                Rule::unique('products', 'slug')->ignore($product_id),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'default_price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:99999999.99'],
            'category' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'is_active' => ['sometimes', 'required', 'boolean'],
            'image_url' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        try {
            $product = DB::selectOne(
                'SELECT id FROM products WHERE id = ? LIMIT 1',
                [$product_id]
            );

            if ($product === null) {
                return response()->json([
                    'status' => false,
                    'message' => 'Product not found.',
                ], 404);
            }

            if ($validated === []) {
                return response()->json([
                    'status' => false,
                    'message' => 'At least one product field must be provided.',
                ], 422);
            }

            $fieldMap = [
                'name' => 'name',
                'slug' => 'slug',
                'description' => 'description',
                'default_price' => 'default_price',
                'category_id' => 'category_id',
                'is_active' => 'is_active',
                'image_url' => 'image_url',
            ];
            $setClauses = []; //bernila collum = ? ,dan di letak dalam setiap loop
            $bindings = [];

            foreach ($fieldMap as $input => $column) {
                if (! array_key_exists($input, $validated)) {
                    continue;
                }

                $setClauses[] = "{$column} = ?"; //tambah dalam setClause collum = ?
                $bindings[] = $input === 'is_active'
                    ? (int) $validated[$input]
                    : $validated[$input]; //bindingkan nilai ?
            }

            $setClauses[] = 'updated_at = CURRENT_TIMESTAMP'; //dilast array letak ini 
            $bindings[] = $product_id; //last binding letak product id
                  //implode akan letak , sebelum nilai seterusnya
            DB::update(
                'UPDATE products SET '.implode(', ', $setClauses).' WHERE id = ?',
                $bindings
            );

            $updatedProduct = DB::selectOne(
                'SELECT
                    id,
                    name,
                    slug,
                    description,
                    default_price,
                    category_id AS category,
                    is_active,
                    image_url
                FROM products
                WHERE id = ?',
                [$product_id]
            );
            $updatedProduct->default_price = (float) $updatedProduct->default_price;
            $updatedProduct->category = $updatedProduct->category === null
                ? null
                : (int) $updatedProduct->category;
            $updatedProduct->is_active = (bool) $updatedProduct->is_active;

            return response()->json([
                'status' => true,
                'message' => 'Product updated successfully.',
                'data' => $updatedProduct,
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => false,
                'message' => 'Unable to update product.',
            ], 500);
        }
    }

    public function addProduct(Request $request): JsonResponse
    {
        if ($request->user()?->role !== 'admin') {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => [ 'required', 'string', 'max:255'],
            'slug' => [
                
                'required',
                'string',
                'max:300'
            ],
            'description' => [ 'nullable', 'string'],
            'default_price' => [ 'required', 'numeric', 'min:0', 'max:99999999.99'],
            'category_id' => [ 'nullable', 'integer', 'exists:categories,id'],
            'is_active' => [ 'required', 'boolean'],
            'image_url' => [ 'nullable', 'string', 'max:500'],
        ]);

        try {
            
             DB::insert("INSERT INTO products(category_id,name,slug,description,default_price,is_active,image_url,created_at,updated_at)
                      VALUES(?,?,?,?,?,?,?,NOW(),NOW())
             ",  
             [
                $validated['category_id'],
                $validated['name'],
                $validated['slug'],
                $validated['description'],
                $validated['default_price'],
                $validated['is_active'],
                $validated['image_url']
             ]
             );

            return response()->json([
                'status' => true,
                'message' => 'Product add successfully.'
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => false,
                'message' => 'Unable to add product.',
            ], 500);
        }
    }



}
