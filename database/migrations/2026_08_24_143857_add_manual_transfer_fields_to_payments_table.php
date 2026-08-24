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
        Schema::table('payments', function (Blueprint $table) {
            $table->string('method', 20)->default('midtrans')->after('payment_type');
            $table->string('proof_path')->nullable()->after('raw_response');
            $table->string('proof_original_name')->nullable()->after('proof_path');
            $table->foreignId('verified_by')->nullable()->after('proof_original_name')->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable()->after('verified_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn(['method', 'proof_path', 'proof_original_name', 'verified_at']);
        });
    }
};
