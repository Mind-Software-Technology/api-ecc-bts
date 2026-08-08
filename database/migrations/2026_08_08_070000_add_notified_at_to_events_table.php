<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->timestamp('notified_at')->nullable()->after('is_active');
        });

        // Event yang sudah ada sebelum fitur ini dianggap sudah diumumkan —
        // tanpa ini, migrasi di production akan membanjiri semua pelanggan
        // dengan notifikasi untuk kegiatan lama.
        DB::table('events')->update(['notified_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('notified_at');
        });
    }
};
