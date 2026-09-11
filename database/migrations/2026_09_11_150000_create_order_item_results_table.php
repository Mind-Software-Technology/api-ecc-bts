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
        Schema::create('order_item_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->timestamps();
        });

        // Carry over the one result each order item already had (single
        // result_path/result_original_name columns) into the new
        // one-to-many table, so existing orders keep their delivered file.
        DB::table('order_items')
            ->whereNotNull('result_path')
            ->select('id', 'result_path', 'result_original_name', 'result_delivered_at')
            ->orderBy('id')
            ->each(function ($item) {
                $timestamp = $item->result_delivered_at ?? now();
                DB::table('order_item_results')->insert([
                    'order_item_id' => $item->id,
                    'path' => $item->result_path,
                    'original_name' => $item->result_original_name ?? basename($item->result_path),
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_item_results');
    }
};
