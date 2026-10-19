<?php

use App\Support\Deployment\SchemaSync;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        // Idempotent guard: survive an earlier partially-recorded run.
        SchemaSync::createTable('local_business_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('local_business_id')->constrained('local_businesses')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 24);
            $table->string('outcome', 32)->nullable();
            $table->string('channel', 24)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();
            $table->index(['local_business_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('local_business_activities');
    }
};
