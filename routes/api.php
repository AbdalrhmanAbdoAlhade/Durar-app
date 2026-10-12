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
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\Admin\TicketController as AdminTicketController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\TicketBookingController;
use Illuminate\Support\Facades\Route;




/*
|--------------------------------------------------------------------------
| Auth
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->middleware('throttle:auth')->group(function () {

    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::delete('account', [AuthController::class, 'deleteAccount']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::put('profile', [AuthController::class, 'updateProfile']);
        Route::put('password', [AuthController::class, 'changePassword']);
    });
});

/*
|--------------------------------------------------------------------------
| Public storefront (guests + authenticated customers)
|--------------------------------------------------------------------------
*/
// صورة الباركود + التحقق
Route::get('/tickets/barcode/{token}', [TicketBookingController::class, 'generateBarcode']);
Route::post('/tickets/verify', [TicketBookingController::class, 'verify']);
Route::post('/tickets/lookup', [TicketBookingController::class, 'lookup'])
    ->middleware('throttle:10,1');
// ========== تذاكر (عام) ==========
Route::get('tickets', [TicketController::class, 'index']);
Route::get('tickets/{ticket}', [TicketController::class, 'show']);
Route::post('tickets/book', [TicketBookingController::class, 'book']);

Route::get('articles', [ArticleController::class, 'index']);
Route::get('articles/{slug}', [ArticleController::class, 'show']);

Route::get('categories', [CategoryController::class, 'index']);
Route::get('categories/{category}', [CategoryController::class, 'show']);

Route::get('products', [ProductController::class, 'index']);
Route::get('products/museum', [ProductController::class, 'museumProducts']);   // لازم قبل {product}
Route::get('products/store', [ProductController::class, 'storeProducts']);     // لازم قبل {product}
Route::get('products/{product}', [ProductController::class, 'show']);
Route::get('products/{product}/reviews', [ReviewController::class, 'index']);

Route::get('banners', [BannerController::class, 'index']);

Route::get('auctions', [AuctionController::class, 'index']);
Route::get('auctions/{auction}', [AuctionController::class, 'show']);
Route::get('auctions/{auction}/bids', [AuctionController::class, 'bids']);

// Cart
Route::prefix('cart')->group(function () {
    Route::get('/', [CartController::class, 'show']);
    Route::post('items', [CartController::class, 'addItem']);
    Route::put('items/{item}', [CartController::class, 'updateItem']);
    Route::delete('items/{item}', [CartController::class, 'removeItem']);
    Route::delete('/', [CartController::class, 'clear']);
});

Route::post('coupons/check', [CouponController::class, 'check']);



// EdfaPay Webhook
Route::post('payments/edfapay/webhook', [PaymentController::class, 'edfapayWebhook']);

/*
|--------------------------------------------------------------------------
| Authenticated customer
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('my-tickets/{id}/cancel', [TicketBookingController::class, 'cancel']);
    Route::post('products/{product}/reviews', [ReviewController::class, 'store']);
  		
    Route::post('orders/{order}/cancel', [OrderController::class, 'cancel']);
	Route::get('/my-tickets', [TicketBookingController::class, 'myBookings']);
    Route::get('/my-tickets/{id}', [TicketBookingController::class, 'showMyBooking']);
  
    Route::post('auctions/{auction}/bids', [AuctionController::class, 'placeBid']);
    Route::post('auctions/{auction}/checkout', [OrderController::class, 'checkoutAuction']);

    Route::get('orders', [OrderController::class, 'index']);
    Route::get('orders/{order}', [OrderController::class, 'show']);
    Route::post('orders/checkout', [OrderController::class, 'checkout']);
    Route::get('orders/{order}/payment', [PaymentController::class, 'show']);
    Route::post('orders/{order}/retry-payment', [OrderController::class, 'retryPayment']);

    Route::post('fcm-tokens', [FcmTokenController::class, 'store']);
    Route::delete('fcm-tokens', [FcmTokenController::class, 'destroy']);
});

/*
|--------------------------------------------------------------------------
| Admin (auth + admin role)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
Route::get('bookings', [App\Http\Controllers\Api\Admin\TicketBookingController::class, 'index']);
    // Categories
    Route::get('categories', [CategoryController::class, 'adminIndex']);
    Route::post('categories', [CategoryController::class, 'store']);
    Route::post('categories/{category}', [CategoryController::class, 'update']);
    Route::delete('categories/{category}', [CategoryController::class, 'destroy']);

    // ========== تذاكر (Admin) ==========
    Route::apiResource('tickets', AdminTicketController::class);
Route::post('tickets/{ticket}/toggle-active', [AdminTicketController::class, 'toggleActive']);
  

    // Products
    Route::patch('products/{product}/rare', [ProductController::class, 'toggleRare']);
    Route::get('products', [ProductController::class, 'adminIndex']);
    Route::post('products', [ProductController::class, 'store']);
    Route::post('products/{product}', [ProductController::class, 'update']);
    Route::delete('products/{product}', [ProductController::class, 'destroy']);
    Route::delete('products/{product}/images/{image}', [ProductController::class, 'destroyImage']);

    // Coupons
    Route::get('coupons', [CouponController::class, 'index']);
    Route::get('coupons/{coupon}', [CouponController::class, 'show']);
    Route::post('coupons', [CouponController::class, 'store']);
    Route::put('coupons/{coupon}', [CouponController::class, 'update']);
    Route::delete('coupons/{coupon}', [CouponController::class, 'destroy']);

    // Banners
    Route::get('banners', [BannerController::class, 'adminIndex']);
    Route::post('banners', [BannerController::class, 'store']);
    Route::post('banners/{banner}', [BannerController::class, 'update']);
    Route::delete('banners/{banner}', [BannerController::class, 'destroy']);

    // Reviews
    Route::get('reviews/pending', [ReviewController::class, 'pending']);
    Route::post('reviews/{review}/approve', [ReviewController::class, 'approve']);
    Route::delete('reviews/{review}', [ReviewController::class, 'destroy']);

    // Articles
    Route::get('articles', [ArticleController::class, 'adminIndex']);
    Route::post('articles', [ArticleController::class, 'store']);
    Route::post('articles/{article}', [ArticleController::class, 'update']);
    Route::delete('articles/{article}', [ArticleController::class, 'destroy']);
    Route::delete('articles/{article}/images/{image}', [ArticleController::class, 'destroyImage']);

    // Auctions
    Route::get('auctions', [AuctionController::class, 'adminIndex']);
    Route::post('auctions', [AuctionController::class, 'store']);
    Route::post('auctions/{auction}', [AuctionController::class, 'update']);
    Route::delete('auctions/{auction}', [AuctionController::class, 'destroy']);
    Route::post('auctions/{auction}/close', [AuctionController::class, 'close']);

    // Orders
    Route::get('orders', [OrderController::class, 'adminIndex']);
    Route::put('orders/{order}/status', [OrderController::class, 'updateStatus']);
});