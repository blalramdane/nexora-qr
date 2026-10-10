<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('delivery_riders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('phone', 30);
            $table->string('vehicle_type', 30)->default('motorcycle');
            $table->json('coverage_areas');
            $table->string('status', 20)->default('pending')->index();
            $table->timestamps();
        });

        Schema::create('restaurant_delivery_riders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_rider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamps();
            $table->unique(['restaurant_id', 'delivery_rider_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('delivery_rider_id')->nullable()->after('delivery_address')->constrained('delivery_riders')->nullOnDelete();
            $table->string('delivery_status', 24)->default('unassigned')->index()->after('delivery_rider_id');
            $table->text('delivery_notes')->nullable()->after('delivery_status');
            $table->timestamp('delivery_assigned_at')->nullable()->after('delivery_notes');
            $table->timestamp('delivery_picked_up_at')->nullable()->after('delivery_assigned_at');
            $table->timestamp('delivered_at')->nullable()->after('delivery_picked_up_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_rider_id');
            $table->dropColumn(['delivery_status', 'delivery_notes', 'delivery_assigned_at', 'delivery_picked_up_at', 'delivered_at']);
        });
        Schema::dropIfExists('restaurant_delivery_riders');
        Schema::dropIfExists('delivery_riders');
    }
};
