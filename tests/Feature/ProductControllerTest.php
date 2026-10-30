<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_get_product_detail_by_id_with_variant_and_stock_totals(): void
    {
        $admin = $this->createUser('admin');
        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Kemeja',
            'slug' => 'kemeja',
        ]);
        $productId = DB::table('products')->insertGetId([
            'category_id' => $categoryId,
            'name' => 'Kemeja Lelaki Biru',
            'slug' => 'kemeja-lelaki-biru',
            'description' => 'Kemeja untuk pemakaian harian.',
            'default_price' => 20,
            'image_url' => 'https://example.com/kemeja.jpg',
            'is_active' => true,
            'created_at' => '2026-10-10 00:00:00',
            'updated_at' => '2026-10-11 00:00:00',
        ]);
        DB::table('product_variants')->insert([
            [
                'product_id' => $productId,
                'sku' => 'KEMEJA-S',
                'size' => 'S',
                'color' => 'Blue',
                'stock' => 34,
            ],
            [
                'product_id' => $productId,
                'sku' => 'KEMEJA-M',
                'size' => 'M',
                'color' => 'Blue',
                'stock' => 50,
            ],
        ]);

        $this->actingAs($admin)
            ->getJson("/api/admin/products/{$productId}")
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.id', $productId)
            ->assertJsonPath('data.name', 'Kemeja Lelaki Biru')
            ->assertJsonPath('data.slug', 'kemeja-lelaki-biru')
            ->assertJsonPath('data.description', 'Kemeja untuk pemakaian harian.')
            ->assertJsonPath('data.default_price', 20)
            ->assertJsonPath('data.category', 'Kemeja')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.image_url', 'https://example.com/kemeja.jpg')
            ->assertJsonPath('data.variants_count', 2)
            ->assertJsonPath('data.total_stock', 84)
            ->assertJsonPath('data.last_updated', '11 Okt 2026');
    }

    public function test_admin_product_detail_returns_not_found_for_unknown_id(): void
    {
        $admin = $this->createUser('admin');

        $this->actingAs($admin)
            ->getJson('/api/admin/products/999')
            ->assertNotFound()
            ->assertJson([
                'status' => false,
                'message' => 'Product not found.',
            ]);
    }

    public function test_admin_product_detail_requires_an_admin_user(): void
    {
        $user = $this->createUser('user');

        $this->actingAs($user)
            ->getJson('/api/admin/products/1')
            ->assertForbidden();

        $this->getJson('/api/admin/products/1')->assertUnauthorized();
    }

    public function test_admin_can_update_product_fields_with_raw_sql(): void
    {
        $admin = $this->createUser('admin');
        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'T-Shirt',
            'slug' => 't-shirt',
        ]);
        $productId = DB::table('products')->insertGetId([
            'name' => 'Old Product',
            'slug' => 'old-product',
            'default_price' => 10,
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->patchJson("/api/admin/products/{$productId}", [
                'name' => 'Classic Cotton T-Shirt',
                'slug' => 'classic-cotton-t-shirt',
                'description' => 'Classic Cotton T-Shirt - quality checked and ready to ship.',
                'default_price' => 39.9,
                'category' => $categoryId,
                'is_active' => true,
                'image_url' => 'https://placehold.co/600x600?text=Classic%20Cotton%20T-Shirt',
            ])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.name', 'Classic Cotton T-Shirt')
            ->assertJsonPath('data.slug', 'classic-cotton-t-shirt')
            ->assertJsonPath('data.description', 'Classic Cotton T-Shirt - quality checked and ready to ship.')
            ->assertJsonPath('data.default_price', 39.9)
            ->assertJsonPath('data.category', $categoryId)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.image_url', 'https://placehold.co/600x600?text=Classic%20Cotton%20T-Shirt');

        $this->assertDatabaseHas('products', [
            'id' => $productId,
            'name' => 'Classic Cotton T-Shirt',
            'slug' => 'classic-cotton-t-shirt',
            'category_id' => $categoryId,
        ]);
    }

    public function test_admin_product_update_rejects_empty_body_and_unknown_product(): void
    {
        $admin = $this->createUser('admin');

        $this->actingAs($admin)
            ->patchJson('/api/admin/products/999', [])
            ->assertNotFound()
            ->assertJsonPath('message', 'Product not found.');

        $productId = DB::table('products')->insertGetId([
            'name' => 'Existing Product',
            'slug' => 'existing-product',
            'default_price' => 10,
            'is_active' => true,
        ]);

        $this->patchJson("/api/admin/products/{$productId}", [])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'At least one product field must be provided.');
    }

    private function createUser(string $role): User
    {
        static $counter = 0;
        $counter++;
        $uid = str_pad((string) $counter, 8, '0', STR_PAD_LEFT);
        $email = "product-user-{$counter}@example.com";

        DB::insert(
            'INSERT INTO users (uid, username, email, password, role) VALUES (?, ?, ?, ?, ?)',
            [$uid, "product-user-{$counter}", $email, 'password', $role]
        );

        return User::query()->where('email', $email)->firstOrFail();
    }
}
