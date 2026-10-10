<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('delivery_rider_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_rider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('settlement_id')->nullable()->constrained('delivery_cash_settlements')->nullOnDelete();
            $table->string('type', 30)->index();
            $table->decimal('amount', 10, 2);
            $table->string('reference', 100)->nullable();
            $table->text('description');
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
            $table->index(['restaurant_id', 'delivery_rider_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_rider_ledger_entries');
    }
};
