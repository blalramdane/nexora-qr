<?php

use App\Support\Deployment\SchemaSync;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        // Idempotent guard: survive an earlier partially-recorded run.
        SchemaSync::alterTable('delivery_riders', function (Blueprint $table) {
            $table->boolean('open_to_area_orders')->default(false)->after('coverage_areas');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_riders', fn (Blueprint $table) => $table->dropColumn('open_to_area_orders'));
    }
};
