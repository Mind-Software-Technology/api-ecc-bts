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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('title', 160);
            $table->string('tagline', 240);
            $table->text('description');
            $table->json('points');
            $table->string('icon', 60);
            $table->string('accent', 20);
            $table->string('image_url', 255)->nullable();
            $table->string('image_alt', 160)->nullable();
            $table->unsignedInteger('price');
            $table->decimal('rating', 2, 1)->default(0);
            $table->unsignedInteger('reviews_count')->default(0);
            $table->string('badge', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
