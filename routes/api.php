<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HitPayController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ShippingController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\VariantController;

require __DIR__.'/auth.php';

Route::get('/products', [ProductController::class, 'getAllProduct']);
Route::get('/products/{slug}', [ProductController::class, 'getDetailProduct']);
Route::get('/shipping', [ShippingController::class, 'getAllCouriers']);
Route::post('/hitpay/webhook', [HitPayController::class, 'webhook']);

Route::middleware(['auth:sanctum'])->group(function () {

Route::get('/admin/dashboard/stats', [DashboardController::class, 'getAdminState']);
Route::get('/admin/dashboard/chart', [DashboardController::class, 'getChartData']);
Route::get('/admin/dashboard/status', [DashboardController::class, 'getOrderStatus']);
Route::get('/admin/dashboard/recent-order',[DashboardController::class,'getRecentOrder']);
Route::get('/admin/category',[CategoryController::class,'getCategory']);
Route::get('/admin/products', [ProductController::class, 'getAllProductList']);
Route::get('/admin/products/{product_id}', [ProductController::class, 'getAdminProductDetail']);
Route::post('/admin/products', [ProductController::class, 'addProduct']);
Route::patch('/admin/products/{product_id}', [ProductController::class, 'updateProductDetail']);
Route::patch('/admin/products/{product_id}/status', [ProductController::class, 'changeStatusProduct']);

Route::get('/admin/products/{product_id}/variants', [VariantController::class, 'getVariantsById']);
Route::patch('/admin/products/variants/{variant_id}', [VariantController::class, 'updateVariant']);
Route::post('/admin/products/{product_id}/variants', [VariantController::class, 'addVariant']);

Route::get('/user',[UserController::class, 'getUser']);
Route::patch('/user',[UserController::class, 'editUser']);

Route::get('/user/address',[AddressController::class, 'getAllAddress']);
Route::get('/user/address/{id}',[AddressController::class, 'getAddressById']);
Route::post('/user/address/add',[AddressController::class,'addAddress']);
Route::patch('/user/address/edit/{addressId}',[AddressController::class,'editAddress']);
Route::post('/user/cart/add', [CartController::class, 'AddToCart']);
Route::get('/user/cart', [CartController::class, 'getUserCart']);
Route::delete('/user/cart/{cartId}', [CartController::class, 'deleteItemCart']);
Route::patch('/user/cart/{cartId}', [CartController::class, 'changeQuantity']);
Route::post('/user/payment/hitpay', [HitPayController::class, 'createPayment']);
Route::get('/user/orders', [OrderController::class, 'getAllUserOrder']);
Route::get('/user/orders/{orderId}', [OrderController::class, 'getUserOrderDetail']);
});
