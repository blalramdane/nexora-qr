<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('local_business_id')->constrained('local_businesses')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('demo_workspace_id')->nullable()->constrained('demo_workspaces')->nullOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('requested_mode', 32)->nullable();
            $table->string('style', 20)->nullable();
            $table->text('note')->nullable();
            $table->json('requested_features')->nullable();
            $table->string('status', 20)->default('invited')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->index(['local_business_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_invitations');
    }
};
