<?php

use App\Http\Controllers\Filament\OrderItemFileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['web', 'auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/order-items/{orderItem}/attachment', [OrderItemFileController::class, 'attachment'])
        ->name('order-items.attachment');
    Route::get('/order-items/{orderItem}/result', [OrderItemFileController::class, 'result'])
        ->name('order-items.result');
});
