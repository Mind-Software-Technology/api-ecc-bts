<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('subtotal')->nullable()->change();
            $table->unsignedInteger('total')->nullable()->change();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedInteger('price_snapshot')->nullable()->change();
            $table->unsignedInteger('line_total')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Backfill NULLs (left by awaiting_quote/quoted orders) before
        // re-adding NOT NULL, or the down-migration fails outright.
        DB::table('orders')->whereNull('subtotal')->update(['subtotal' => 0]);
        DB::table('orders')->whereNull('total')->update(['total' => 0]);
        DB::table('order_items')->whereNull('price_snapshot')->update(['price_snapshot' => 0]);
        DB::table('order_items')->whereNull('line_total')->update(['line_total' => 0]);

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('subtotal')->nullable(false)->change();
            $table->unsignedInteger('total')->nullable(false)->change();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedInteger('price_snapshot')->nullable(false)->change();
            $table->unsignedInteger('line_total')->nullable(false)->change();
        });
    }
};
