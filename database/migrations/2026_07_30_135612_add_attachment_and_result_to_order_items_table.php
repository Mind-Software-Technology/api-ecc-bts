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
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('attachment_path')->nullable()->after('line_total');
            $table->string('attachment_original_name')->nullable()->after('attachment_path');
            $table->string('result_path')->nullable()->after('attachment_original_name');
            $table->string('result_original_name')->nullable()->after('result_path');
            $table->timestamp('result_delivered_at')->nullable()->after('result_original_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn([
                'attachment_path', 'attachment_original_name',
                'result_path', 'result_original_name', 'result_delivered_at',
            ]);
        });
    }
};
