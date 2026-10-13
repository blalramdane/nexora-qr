<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Idempotent guard: survive an earlier partially-recorded run.
        if (! Schema::hasTable('restaurant_expenses')) {
            Schema::create('restaurant_expenses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('category', 60);
                $table->string('title', 160);
                $table->decimal('amount', 12, 2);
                $table->string('payment_method', 30)->default('cash');
                $table->date('expense_date')->index();
                $table->string('reference', 100)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['restaurant_id', 'expense_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_expenses');
    }
};
