<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 50)->default('Home');
            $table->string('recipient_name', 100);
            $table->string('phone', 20);
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('city', 100);
            $table->string('state', 100);
            $table->string('postcode', 10);
            $table->boolean('is_default')->default(false);
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->index('user_id', 'idx_addresses_user');
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('slug', 120)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable()->useCurrent();
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug', 300)->unique();
            $table->text('description')->nullable();
            $table->decimal('default_price', 10, 2)->unsigned();
            $table->string('image_url', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
            $table->index('category_id', 'idx_products_category');
            $table->index('is_active', 'idx_products_active');
        });

        Schema::create('product_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 100)->unique();
            $table->string('size', 50)->default('Standard');
            $table->string('color', 50)->default('');
            $table->decimal('price', 10, 2)->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
            $table->unique(['product_id', 'size', 'color'], 'uniq_variant');
            $table->index('product_id', 'idx_variant_product');
        });

        Schema::create('cart_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamp('added_at')->nullable()->useCurrent();
            $table->unique(['user_id', 'variant_id'], 'unique_user_variant');
        });

        Schema::create('couriers', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->string('tracking_url_template')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable()->useCurrent();
        });

        Schema::create('shipping_methods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('courier_id')->constrained()->cascadeOnDelete();
            $table->string('service_name', 100);
            $table->decimal('price', 10, 2)->unsigned()->default(0);
            $table->string('estimated_days', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->index('courier_id', 'idx_shipping_courier');
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number', 50)->unique();
            $table->string('reference_no', 100)->nullable();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('address_id')->nullable()->constrained('addresses')->nullOnDelete();
            $table->text('address_snapshot');
            $table->decimal('subtotal', 10, 2)->unsigned()->default(0);
            $table->decimal('discount', 10, 2)->unsigned()->default(0);
            $table->decimal('tax', 10, 2)->unsigned()->default(0);
            $table->foreignId('shipping_method')->nullable()->constrained('shipping_methods')->nullOnDelete();
            $table->decimal('total_amount', 10, 2)->unsigned();
            $table->char('currency', 3)->default('MYR');
            $table->enum('status', ['pending', 'paid', 'prepare order', 'shipped', 'completed'])->default('pending');
            $table->timestamp('order_date')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
            $table->index('user_id', 'idx_orders_user');
            $table->index('status', 'idx_orders_status');
            $table->index('order_date', 'idx_orders_date');
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('product_name_at_purchase');
            $table->string('variant_info_at_purchase', 100);
            $table->unsignedInteger('quantity');
            $table->decimal('price', 10, 2)->unsigned();
            $table->decimal('subtotal', 10, 2)->unsigned();
            $table->index('order_id', 'idx_order_items_order');
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 50);
            $table->string('billcode', 100)->nullable()->unique();
            $table->string('reference_no', 100)->nullable();
            $table->decimal('amount', 10, 2)->unsigned();
            $table->enum('status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
            $table->index('order_id', 'idx_payments_order');
            $table->index('status', 'idx_payments_status');
        });

        Schema::create('shipments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('courier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tracking_number', 100);
            $table->enum('status', ['pending', 'shipped', 'in_transit', 'delivered', 'failed'])->default('pending');
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
            $table->index('tracking_number', 'idx_shipments_tracking');
        });

        Schema::create('order_status_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('status', 50);
            $table->text('note')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->index('order_id', 'idx_osh_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('shipping_methods');
        Schema::dropIfExists('couriers');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('addresses');
    }
};