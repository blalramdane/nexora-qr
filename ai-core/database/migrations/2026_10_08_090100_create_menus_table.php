<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('templates')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->json('theme')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->unique(['restaurant_id', 'slug']);
            $table->index(['restaurant_id', 'is_published']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
