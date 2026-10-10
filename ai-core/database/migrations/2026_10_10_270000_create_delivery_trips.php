<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('delivery_trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_rider_id')->constrained()->restrictOnDelete();
            $table->string('trip_number', 40);
            $table->string('status', 24)->default('planned')->index();
            $table->text('notes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['restaurant_id', 'trip_number']);
            $table->index(['restaurant_id', 'status', 'created_at']);
        });

        Schema::create('delivery_trip_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_trip_id')->constrained('delivery_trips')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete()->unique();
            $table->unsignedSmallInteger('stop_number');
            $table->timestamps();
            $table->unique(['delivery_trip_id', 'stop_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_trip_orders');
        Schema::dropIfExists('delivery_trips');
    }
};
