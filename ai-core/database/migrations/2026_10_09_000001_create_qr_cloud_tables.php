<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_branches', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 100)->unique();
            $table->string('code', 32)->unique();
            $table->string('currency', 8)->default('EGP');
            $table->unsignedInteger('menu_version')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('qr_branch_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('qr_branches')->cascadeOnDelete();
            $table->string('name', 120)->default('Primary POS');
            $table->char('token_hash', 64)->unique();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'revoked_at']);
        });

        Schema::create('qr_menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('qr_branches')->cascadeOnDelete();
            $table->string('source_product_id', 100);
            $table->string('name', 180);
            $table->string('category_name', 120)->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price_minor');
            $table->string('currency', 8)->default('EGP');
            $table->boolean('is_available')->default(true);
            $table->json('options')->nullable();
            $table->timestamps();
            $table->unique(['branch_id', 'source_product_id']);
            $table->index(['branch_id', 'is_available']);
        });

        Schema::create('qr_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('qr_branches')->restrictOnDelete();
            $table->uuid('source_order_uuid');
            $table->string('status', 32)->default('pending_delivery');
            $table->string('customer_name', 120)->nullable();
            $table->string('customer_phone', 40)->nullable();
            $table->text('customer_address')->nullable();
            $table->text('notes')->nullable();
            $table->string('fulfillment_type', 24)->default('takeaway');
            $table->unsignedBigInteger('subtotal_minor');
            $table->string('currency', 8)->default('EGP');
            $table->unsignedInteger('menu_version');
            $table->json('items_snapshot');
            $table->char('payload_hash', 64);
            $table->uuid('delivery_lease_token')->nullable();
            $table->timestamp('delivery_lease_expires_at')->nullable();
            $table->unsignedInteger('delivery_attempts')->default(0);
            $table->string('local_order_id', 100)->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
            $table->unique(['branch_id', 'source_order_uuid']);
            $table->index(['branch_id', 'status', 'id']);
            $table->index(['status', 'delivery_lease_expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_orders');
        Schema::dropIfExists('qr_menu_items');
        Schema::dropIfExists('qr_branch_agents');
        Schema::dropIfExists('qr_branches');
    }
};
