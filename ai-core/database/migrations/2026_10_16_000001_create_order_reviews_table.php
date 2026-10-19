<?php

use App\Support\Deployment\SchemaSync;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_reviews')) {
            SchemaSync::createTable('order_reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('menu_id')->nullable()->constrained('menus')->nullOnDelete();
                $table->unsignedTinyInteger('rating'); // 1-5
                $table->text('comment')->nullable();
                $table->json('rating_breakdown')->nullable(); // food, delivery, service, packaging
                $table->boolean('is_approved')->default(false);
                $table->boolean('is_published')->default(false);
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
            });
        }

        // Upgrade the legacy 2026-10-10 review table one column at a time.
        // A duplicate early column must not prevent later missing columns from
        // being added or make a partial migration look complete.
        if (! Schema::hasColumn('order_reviews', 'customer_id')) {
            Schema::table('order_reviews', fn (Blueprint $table) => $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete());
        }
        if (! Schema::hasColumn('order_reviews', 'menu_id')) {
            Schema::table('order_reviews', fn (Blueprint $table) => $table->foreignId('menu_id')->nullable()->constrained('menus')->nullOnDelete());
        }
        if (! Schema::hasColumn('order_reviews', 'rating_breakdown')) {
            Schema::table('order_reviews', fn (Blueprint $table) => $table->json('rating_breakdown')->nullable());
        }
        if (! Schema::hasColumn('order_reviews', 'is_approved')) {
            Schema::table('order_reviews', fn (Blueprint $table) => $table->boolean('is_approved')->default(false));
        }
        if (! Schema::hasColumn('order_reviews', 'is_published')) {
            Schema::table('order_reviews', fn (Blueprint $table) => $table->boolean('is_published')->default(false));
        }
        if (! Schema::hasColumn('order_reviews', 'published_at')) {
            Schema::table('order_reviews', fn (Blueprint $table) => $table->timestamp('published_at')->nullable());
        }

        foreach ([['rating'], ['is_approved'], ['is_published'], ['restaurant_id', 'rating'], ['restaurant_id', 'is_published', 'created_at']] as $columns) {
            if (! Schema::hasIndex('order_reviews', $columns)) {
                Schema::table('order_reviews', fn (Blueprint $table) => $table->index($columns));
            }
        }

        if (! Schema::hasIndex('order_reviews', ['order_id', 'customer_id'], 'unique')) {
            Schema::table('order_reviews', fn (Blueprint $table) => $table->unique(['order_id', 'customer_id']));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_reviews');
    }
};
