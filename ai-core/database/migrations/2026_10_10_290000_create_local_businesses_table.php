<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Idempotent guard: survive an earlier partially-recorded run.
        if (! Schema::hasTable('local_businesses')) {
            Schema::create('local_businesses', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('city')->default('دمياط')->index();
                $table->string('area')->nullable()->index();
                $table->string('address')->nullable();
                $table->string('category')->default('مطعم')->index();
                $table->string('phone', 40)->nullable();
                $table->string('website_url')->nullable();
                $table->string('maps_url')->nullable();
                $table->string('source')->default('manual');
                $table->string('source_place_id')->nullable()->unique();
                $table->string('source_url')->nullable();
                $table->string('menu_url')->nullable();
                $table->string('menu_status')->default('unknown')->index();
                $table->string('claim_status')->default('unclaimed')->index();
                $table->string('listing_status')->default('draft')->index();
                $table->text('internal_notes')->nullable();
                $table->timestamp('source_checked_at')->nullable();
                $table->timestamps();
                $table->index(['city', 'area', 'listing_status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('local_businesses');
    }
};
