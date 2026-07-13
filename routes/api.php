<?php

use App\Http\Controllers\Api\Admin\AdvantageController as AdminAdvantageController;
use App\Http\Controllers\Api\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\Admin\ContactMessageController as AdminContactMessageController;
use App\Http\Controllers\Api\Admin\FaqController as AdminFaqController;
use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Api\Admin\ProcessStepController as AdminProcessStepController;
use App\Http\Controllers\Api\Admin\ServiceController as AdminServiceController;
use App\Http\Controllers\Api\Admin\SiteConfigController as AdminSiteConfigController;
use App\Http\Controllers\Api\Admin\StatController as AdminStatController;
use App\Http\Controllers\Api\Admin\TestimonialController as AdminTestimonialController;
use App\Http\Controllers\Api\AdvantageController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\FaqController;
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
Route::get('advantages', [AdvantageController::class, 'index']);
Route::get('process-steps', [ProcessStepController::class, 'index']);

Route::get('site-config', [SiteConfigController::class, 'show']);

// Auth (shared antara customer & admin — dibedakan lewat users.role)
Route::post('auth/register', [AuthController::class, 'register']);
Route::post('auth/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);
});

// Admin — butuh login + role admin
Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::apiResource('categories', AdminCategoryController::class)->except('show');
    Route::apiResource('services', AdminServiceController::class)->except('show');
    Route::apiResource('testimonials', AdminTestimonialController::class)->except('show');
    Route::apiResource('faqs', AdminFaqController::class)->except('show');
    Route::apiResource('stats', AdminStatController::class)->except('show');
    Route::apiResource('advantages', AdminAdvantageController::class)->except('show');
    Route::apiResource('process-steps', AdminProcessStepController::class)->except('show');
    Route::put('site-config', [AdminSiteConfigController::class, 'update']);

    Route::get('orders', [AdminOrderController::class, 'index']);
    Route::get('orders/{order_no}', [AdminOrderController::class, 'show']);
    Route::get('payments', [AdminPaymentController::class, 'index']);
    Route::get('payments/{payment}', [AdminPaymentController::class, 'show']);
    Route::get('contact-messages', [AdminContactMessageController::class, 'index']);
    Route::delete('contact-messages/{contact_message}', [AdminContactMessageController::class, 'destroy']);
});
