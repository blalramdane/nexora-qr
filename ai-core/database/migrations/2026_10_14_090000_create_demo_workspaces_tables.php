<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_workspaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('local_business_id')->constrained('local_businesses')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('preview_token_hash', 64)->unique();
            $table->string('owner_token_hash', 64)->unique();
            $table->json('menu_snapshot');
            $table->json('preview_request')->nullable();
            $table->string('status', 16)->default('active')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['local_business_id', 'status']);
        });

        Schema::create('demo_sandbox_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demo_workspace_id')->constrained('demo_workspaces')->cascadeOnDelete();
            $table->string('order_number', 40)->unique();
            $table->string('customer_name', 120);
            $table->string('customer_phone', 30);
            $table->json('items');
            $table->decimal('total', 10, 2);
            $table->string('status', 24)->default('new')->index();
            $table->timestamps();
            $table->index(['demo_workspace_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_sandbox_orders');
        Schema::dropIfExists('demo_workspaces');
    }
};
