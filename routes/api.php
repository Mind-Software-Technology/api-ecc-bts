<?php

use App\Http\Controllers\Api\AboutStatsController;
use App\Http\Controllers\Api\AdvantageController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CartItemController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\FaqController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PaymentNotificationController;
use App\Http\Controllers\Api\ProcessStepController;
use App\Http\Controllers\Api\PushSubscriptionController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\SiteConfigController;
use App\Http\Controllers\Api\StatController;
use App\Http\Controllers\Api\TestimonialController;
use Illuminate\Support\Facades\Route;

Route::get('categories', [CategoryController::class, 'index']);
Route::get('categories/{slug}', [CategoryController::class, 'show']);

Route::get('services', [ServiceController::class, 'index']);
Route::get('services/{slug}', [ServiceController::class, 'show']);

Route::get('testimonials', [TestimonialController::class, 'index']);
Route::get('faqs', [FaqController::class, 'index']);
Route::get('stats', [StatController::class, 'index']);
Route::get('about-stats', [AboutStatsController::class, 'index']);
Route::get('advantages', [AdvantageController::class, 'index']);
Route::get('process-steps', [ProcessStepController::class, 'index']);

Route::get('site-config', [SiteConfigController::class, 'show']);

Route::get('events', [EventController::class, 'index']);
Route::get('events/{id}', [EventController::class, 'show'])->whereNumber('id');

Route::post('contact', [ContactController::class, 'store'])->middleware('throttle:10,1');

// Cart (guest via X-Session-Id header, atau user login lewat Sanctum session)
Route::get('cart', [CartController::class, 'show']);
Route::delete('cart', [CartController::class, 'clear']);
Route::post('cart/items', [CartItemController::class, 'store']);
Route::patch('cart/items/{id}', [CartItemController::class, 'update']);
Route::delete('cart/items/{id}', [CartItemController::class, 'destroy']);

// Checkout / Order
Route::post('orders', [OrderController::class, 'store']);
Route::get('orders/{order_no}', [OrderController::class, 'show']);
Route::patch('orders/{order_no}', [OrderController::class, 'update']);
Route::get('orders', [OrderController::class, 'index'])->middleware('throttle:20,1');
Route::get('orders/{order_no}/items/{item}/attachment', [OrderController::class, 'downloadAttachment']);
Route::post('orders/{order_no}/items/{item}/attachment', [OrderController::class, 'uploadAttachment']);
Route::get('orders/{order_no}/items/{item}/result', [OrderController::class, 'downloadResult']);
Route::post('orders/{order_no}/testimonial', [OrderController::class, 'submitTestimonial']);
Route::post('orders/{order_no}/accept-quote', [OrderController::class, 'acceptQuote']);
Route::post('orders/{order_no}/decline', [OrderController::class, 'decline']);

// Payments (Midtrans Core API)
Route::post('payments', [PaymentController::class, 'store'])->middleware('throttle:10,1');
Route::get('payments/{order_no}/status', [PaymentController::class, 'status']);
Route::post('payments/{order_no}/cancel', [PaymentController::class, 'cancel']);
Route::post('payments/{order_no}/change-method', [PaymentController::class, 'changeMethod']);
Route::post('payments/notification', [PaymentNotificationController::class, 'handle']);

// Auth (shared antara customer & admin — dibedakan lewat users.role)
Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('auth/google', [AuthController::class, 'loginWithGoogle'])->middleware('throttle:5,1');
Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::patch('auth/me', [AuthController::class, 'updateProfile']);
    Route::post('auth/email/verification-notification', [AuthController::class, 'resendVerification'])->middleware('throttle:6,1');
    Route::get('auth/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    // Cart, checkout & pembayaran kini wajib login — tidak ada lagi keranjang
    // guest (harus pesan lewat akun; keranjang guest dulu tidak pernah
    // digabung ke keranjang user saat login, jadi checkout-tanpa-login
    // sengaja dihilangkan sepenuhnya, bukan cuma dikunci di halaman terakhir).
    Route::get('cart', [CartController::class, 'show']);
    Route::delete('cart', [CartController::class, 'clear']);
    Route::post('cart/items', [CartItemController::class, 'store']);
    Route::patch('cart/items/{id}', [CartItemController::class, 'update']);
    Route::delete('cart/items/{id}', [CartItemController::class, 'destroy']);

    Route::post('orders', [OrderController::class, 'store']);
    Route::get('orders/{order_no}', [OrderController::class, 'show']);
    // throttle:40 — frontend mem-polling daftar ini tiap 2 detik di halaman
    // riwayat pembayaran (30 request/menit), jadi batas 20 yang lama membuat
    // sepertiga polling kena 429.
    Route::get('orders', [OrderController::class, 'index'])->middleware('throttle:40,1');
    Route::get('orders/{order_no}/items/{item_id}/attachment', [OrderController::class, 'downloadAttachment']);

    // Lonceng notifikasi — di-polling tiap 2 detik sebagai cadangan untuk
    // pelanggan yang menolak izin notifikasi browser.
    Route::get('notifications', [NotificationController::class, 'index'])->middleware('throttle:40,1');
    Route::post('notifications/read', [NotificationController::class, 'markAllRead']);

    Route::post('push-subscriptions', [PushSubscriptionController::class, 'store']);
    Route::delete('push-subscriptions', [PushSubscriptionController::class, 'destroy']);

    Route::post('payments', [PaymentController::class, 'store'])->middleware('throttle:10,1');
    Route::get('payments/{order_no}/status', [PaymentController::class, 'status']);
    Route::post('payments/{order_no}/cancel', [PaymentController::class, 'cancel']);
});
