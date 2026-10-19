<?php

use App\Support\Deployment\SchemaSync;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Idempotent guards: survive an earlier partially-recorded run.
        SchemaSync::alterTable('restaurants', function (Blueprint $table) {
            $table->json('delivery_areas')->nullable()->after('delivery_fee');
        });
        SchemaSync::alterTable('orders', function (Blueprint $table) {
            $table->string('delivery_area', 120)->nullable()->after('delivery_address');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('delivery_area'));
        Schema::table('restaurants', fn (Blueprint $table) => $table->dropColumn('delivery_areas'));
    }
};
