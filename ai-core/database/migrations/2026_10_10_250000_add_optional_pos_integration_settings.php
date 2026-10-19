<?php

use App\Support\Deployment\SchemaSync;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        // Idempotent guard: survive an earlier partially-recorded run.
        SchemaSync::alterTable('restaurants', function (Blueprint $table) {
            $table->boolean('pos_integration_enabled')->default(false);
            $table->string('pos_provider')->nullable();
            $table->string('pos_endpoint_url', 500)->nullable();
            $table->text('pos_integration_notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn(['pos_integration_enabled', 'pos_provider', 'pos_endpoint_url', 'pos_integration_notes']);
        });
    }
};
