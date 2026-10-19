<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('theme_ab_test_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('theme_ab_test_id')->constrained('theme_ab_tests')->cascadeOnDelete();
            $table->char('visitor_token_hash', 64);
            $table->string('variant', 1); // a or b
            $table->timestamp('converted_at')->nullable();
            $table->foreignId('order_id')->nullable()->unique()->constrained('orders')->nullOnDelete();
            $table->timestamps();

            $table->unique(['theme_ab_test_id', 'visitor_token_hash'], 'theme_ab_participant_identity_unique');
            $table->index(['theme_ab_test_id', 'variant', 'converted_at'], 'theme_ab_participant_metrics_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_ab_test_participants');
    }
};
