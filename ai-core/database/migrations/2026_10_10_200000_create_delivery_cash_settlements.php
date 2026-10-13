<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Idempotent guards: survive an earlier partially-recorded run.
        if (! Schema::hasTable('delivery_cash_settlements')) {
            Schema::create('delivery_cash_settlements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('delivery_rider_id')->constrained()->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->decimal('expected_amount', 10, 2)->default(0);
                $table->decimal('collected_amount', 10, 2)->default(0);
                $table->decimal('handed_over_amount', 10, 2)->default(0);
                $table->string('status', 20)->default('pending')->index();
                $table->text('notes')->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->timestamps();
                $table->index(['restaurant_id', 'delivery_rider_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('delivery_cash_settlement_orders')) {
            Schema::create('delivery_cash_settlement_orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('delivery_cash_settlement_id')->constrained('delivery_cash_settlements')->cascadeOnDelete();
                $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
                $table->decimal('expected_amount', 10, 2);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_cash_settlement_orders');
        Schema::dropIfExists('delivery_cash_settlements');
    }
};
