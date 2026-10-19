<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('host_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('share_token', 64)->unique();
            $table->string('status', 20)->default('open'); // open, locked, converted, cancelled
            $table->unsignedInteger('max_participants')->default(10);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['restaurant_id', 'status']);
        });

        Schema::create('group_order_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_order_id')->constrained()->cascadeOnDelete();
            $table->string('participant_name', 80)->nullable();
            $table->string('participant_phone', 30)->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_order_participants');
        Schema::dropIfExists('group_orders');
    }
};
