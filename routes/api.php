<?php

use App\Http\Controllers\Api\AuctionController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\FcmTokenController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReviewController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

/*
|--------------------------------------------------------------------------
| Public storefront (guests + authenticated customers)
|--------------------------------------------------------------------------
*/
Route::get('categories', [CategoryController::class, 'index']);
Route::get('categories/{category}', [CategoryController::class, 'show']);

Route::get('products', [ProductController::class, 'index']);
Route::get('products/{product}', [ProductController::class, 'show']);
Route::get('products/{product}/reviews', [ReviewController::class, 'index']);

Route::get('banners', [BannerController::class, 'index']);

Route::get('auctions', [AuctionController::class, 'index']);
Route::get('auctions/{auction}', [AuctionController::class, 'show']);
Route::get('auctions/{auction}/bids', [AuctionController::class, 'bids']);

// Cart works for guests too (session-based) and for authenticated users
Route::prefix('cart')->group(function () {
    Route::get('/', [CartController::class, 'show']);
    Route::post('items', [CartController::class, 'addItem']);
    Route::put('items/{item}', [CartController::class, 'updateItem']);
    Route::delete('items/{item}', [CartController::class, 'removeItem']);
    Route::delete('/', [CartController::class, 'clear']);
});

Route::post('coupons/check', [CouponController::class, 'check']);

// EdfaPay calls this directly — must stay outside auth middleware
Route::post('payments/edfapay/webhook', [PaymentController::class, 'edfapayWebhook']);

/*
|--------------------------------------------------------------------------
| Authenticated customer
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('products/{product}/reviews', [ReviewController::class, 'store']);

    Route::post('auctions/{auction}/bids', [AuctionController::class, 'placeBid']);
    Route::post('auctions/{auction}/checkout', [OrderController::class, 'checkoutAuction']);

    Route::get('orders', [OrderController::class, 'index']);
    Route::get('orders/{order}', [OrderController::class, 'show']);
    Route::post('orders/checkout', [OrderController::class, 'checkout']);
    Route::get('orders/{order}/payment', [PaymentController::class, 'show']);

    Route::post('fcm-tokens', [FcmTokenController::class, 'store']);
    Route::delete('fcm-tokens', [FcmTokenController::class, 'destroy']);
});

/*
|--------------------------------------------------------------------------
| Admin (auth + admin role)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('categories', [CategoryController::class, 'adminIndex']);
    Route::post('categories', [CategoryController::class, 'store']);
    Route::put('categories/{category}', [CategoryController::class, 'update']);
    Route::delete('categories/{category}', [CategoryController::class, 'destroy']);

    Route::get('products', [ProductController::class, 'adminIndex']);
    Route::post('products', [ProductController::class, 'store']);
    Route::put('products/{product}', [ProductController::class, 'update']);
    Route::delete('products/{product}', [ProductController::class, 'destroy']);
    Route::delete('products/{product}/images/{image}', [ProductController::class, 'destroyImage']);

    Route::get('coupons', [CouponController::class, 'index']);
    Route::get('coupons/{coupon}', [CouponController::class, 'show']);
    Route::post('coupons', [CouponController::class, 'store']);
    Route::put('coupons/{coupon}', [CouponController::class, 'update']);
    Route::delete('coupons/{coupon}', [CouponController::class, 'destroy']);

    Route::get('banners', [BannerController::class, 'adminIndex']);
    Route::post('banners', [BannerController::class, 'store']);
    Route::put('banners/{banner}', [BannerController::class, 'update']);
    Route::delete('banners/{banner}', [BannerController::class, 'destroy']);

    Route::get('reviews/pending', [ReviewController::class, 'pending']);
    Route::post('reviews/{review}/approve', [ReviewController::class, 'approve']);
    Route::delete('reviews/{review}', [ReviewController::class, 'destroy']);

    Route::get('auctions', [AuctionController::class, 'adminIndex']);
    Route::post('auctions', [AuctionController::class, 'store']);
    Route::put('auctions/{auction}', [AuctionController::class, 'update']);
    Route::delete('auctions/{auction}', [AuctionController::class, 'destroy']);
    Route::post('auctions/{auction}/close', [AuctionController::class, 'close']);

    Route::get('orders', [OrderController::class, 'adminIndex']);
    Route::put('orders/{order}/status', [OrderController::class, 'updateStatus']);
});
