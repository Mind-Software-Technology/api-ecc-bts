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
        Schema::create('order_item_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->timestamps();
        });

        // Carry over the one attachment each order item already had (single
        // attachment_path/attachment_original_name columns) into the new
        // one-to-many table, so existing orders keep their uploaded file.
        DB::table('order_items')
            ->whereNotNull('attachment_path')
            ->select('id', 'attachment_path', 'attachment_original_name', 'updated_at', 'created_at')
            ->orderBy('id')
            ->each(function ($item) {
                DB::table('order_item_attachments')->insert([
                    'order_item_id' => $item->id,
                    'path' => $item->attachment_path,
                    'original_name' => $item->attachment_original_name ?? basename($item->attachment_path),
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at,
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_item_attachments');
    }
};
