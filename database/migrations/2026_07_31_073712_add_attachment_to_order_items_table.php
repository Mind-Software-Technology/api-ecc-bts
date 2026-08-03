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
            if (! Schema::hasColumn('order_items', 'attachment_path')) {
                $table->string('attachment_path')->nullable()->after('line_total');
            }
            if (! Schema::hasColumn('order_items', 'attachment_name')) {
                $table->string('attachment_name')->nullable()->after('attachment_path');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_items', 'attachment_name')) {
                $table->dropColumn('attachment_name');
            }
            if (Schema::hasColumn('order_items', 'attachment_path') && ! Schema::hasColumn('order_items', 'attachment_original_name')) {
                $table->dropColumn('attachment_path');
            }
        });
    }
};
