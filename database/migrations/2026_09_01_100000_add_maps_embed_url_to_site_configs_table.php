<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_configs', function (Blueprint $table) {
            $table->string('maps_embed_url', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('site_configs', function (Blueprint $table) {
            $table->dropColumn('maps_embed_url');
        });
    }
};
