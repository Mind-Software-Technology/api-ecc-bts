<?php

use App\Http\Controllers\Filament\OrderItemFileController;
use App\Http\Controllers\Filament\PaymentFileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Guard 'admin' — sama dengan yang dipakai panel Filament, bukan guard 'web'
// milik sesi pelanggan.
Route::middleware(['web', 'auth:admin', 'admin:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/order-items/{orderItem}/attachment', [OrderItemFileController::class, 'attachment'])
        ->name('order-items.attachment');
    Route::get('/order-items/{orderItem}/result', [OrderItemFileController::class, 'result'])
        ->name('order-items.result');
    Route::get('/payments/{payment}/proof', [PaymentFileController::class, 'proof'])
        ->name('payments.proof');
});
