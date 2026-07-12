<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained();
            $table->string('midtrans_order_id', 50)->unique();
            $table->string('transaction_id', 50)->unique()->nullable();
            $table->string('payment_type', 30);
            $table->string('channel_detail', 30)->nullable();
            $table->unsignedInteger('gross_amount');
            $table->string('transaction_status', 20);
            $table->string('fraud_status', 20)->nullable();
            $table->string('va_number', 255)->nullable();
            $table->string('qr_url', 255)->nullable();
            $table->string('deeplink_url', 255)->nullable();
            $table->string('payment_code', 255)->nullable();
            $table->timestamp('expiry_time')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
