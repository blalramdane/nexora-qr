<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('idempotency_key', 100)->nullable();
            $table->string('idempotency_request_hash', 64)->nullable();
            $table->text('tracking_token_encrypted')->nullable();
            $table->unique(['restaurant_id', 'idempotency_key'], 'orders_restaurant_idempotency_unique');
        });

        Schema::create('order_integration_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 100);
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at')->nullable()->index();
            $table->timestamp('processed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('payload');
            $table->timestamps();
            $table->unique(['order_id', 'event_type']);
            $table->index(['restaurant_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_integration_events');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_restaurant_idempotency_unique');
            $table->dropColumn(['idempotency_key', 'idempotency_request_hash', 'tracking_token_encrypted']);
        });
    }
};
