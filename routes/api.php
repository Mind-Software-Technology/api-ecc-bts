<?php

use App\Http\Controllers\Api\AboutStatsController;
use App\Http\Controllers\Api\Admin\AdvantageController as AdminAdvantageController;
use App\Http\Controllers\Api\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\Admin\ContactMessageController as AdminContactMessageController;
use App\Http\Controllers\Api\Admin\EventController as AdminEventController;
use App\Http\Controllers\Api\Admin\FaqController as AdminFaqController;
use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Api\Admin\ProcessStepController as AdminProcessStepController;
use App\Http\Controllers\Api\Admin\ServiceController as AdminServiceController;
use App\Http\Controllers\Api\Admin\SiteConfigController as AdminSiteConfigController;
use App\Http\Controllers\Api\Admin\TestimonialController as AdminTestimonialController;
use App\Http\Controllers\Api\AdvantageController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CartItemController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\FaqController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PaymentNotificationController;
use App\Http\Controllers\Api\ProcessStepController;
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
Route::get('orders', [OrderController::class, 'index'])->middleware('throttle:20,1');
Route::get('orders/{order_no}/items/{item}/attachment', [OrderController::class, 'downloadAttachment']);
Route::get('orders/{order_no}/items/{item}/result', [OrderController::class, 'downloadResult']);

// Payments (Midtrans Core API)
Route::post('payments', [PaymentController::class, 'store'])->middleware('throttle:10,1');
Route::get('payments/{order_no}/status', [PaymentController::class, 'status']);
Route::post('payments/{order_no}/cancel', [PaymentController::class, 'cancel']);
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
});

// Admin — butuh login + role admin
Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::apiResource('categories', AdminCategoryController::class)->except('show');
    Route::apiResource('services', AdminServiceController::class)->except('show');
    Route::apiResource('testimonials', AdminTestimonialController::class)->except('show');
    Route::apiResource('faqs', AdminFaqController::class)->except('show');
    Route::apiResource('advantages', AdminAdvantageController::class)->except('show');
    Route::apiResource('process-steps', AdminProcessStepController::class)->except('show');
    Route::apiResource('events', AdminEventController::class)->except('show');
    Route::put('site-config', [AdminSiteConfigController::class, 'update']);

    Route::get('orders', [AdminOrderController::class, 'index']);
    Route::get('orders/{order_no}', [AdminOrderController::class, 'show']);
    Route::post('orders/{order_no}/items/{item}/result', [AdminOrderController::class, 'uploadResult']);
    Route::get('payments', [AdminPaymentController::class, 'index']);
    Route::get('payments/{payment}', [AdminPaymentController::class, 'show']);
    Route::get('contact-messages', [AdminContactMessageController::class, 'index']);
    Route::delete('contact-messages/{contact_message}', [AdminContactMessageController::class, 'destroy']);
});
