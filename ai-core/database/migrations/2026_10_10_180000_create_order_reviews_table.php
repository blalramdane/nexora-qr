<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('comment', 1000)->nullable();
            $table->timestamps();
            $table->index(['restaurant_id', 'rating']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_reviews');
    }
};
