<?php

use App\Support\Deployment\SchemaSync;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        // Idempotent guard: survive an earlier partially-recorded run.
        SchemaSync::createTable('delivery_shift_closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_rider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('shift_date');
            $table->string('shift_name', 40);
            $table->decimal('expected_amount', 10, 2)->default(0);
            $table->decimal('collected_amount', 10, 2)->default(0);
            $table->decimal('handed_over_amount', 10, 2)->default(0);
            $table->decimal('collection_difference', 10, 2)->default(0);
            $table->decimal('handover_difference', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('closed_at');
            $table->timestamps();
            $table->unique(['restaurant_id', 'delivery_rider_id', 'shift_date', 'shift_name'], 'delivery_shift_closing_unique');
            $table->index(['restaurant_id', 'shift_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_shift_closings');
    }
};
