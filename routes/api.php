<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\HitPayController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ShippingController;

require __DIR__.'/auth.php';

Route::get('/products', [ProductController::class, 'getAllProduct']);
Route::get('/products/{slug}', [ProductController::class, 'getDetailProduct']);
Route::get('/shipping', [ShippingController::class, 'getAllCouriers']);
Route::post('/hitpay/webhook', [HitPayController::class, 'webhook']);

Route::middleware(['auth:sanctum'])->group(function () {

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

});
