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
            $table->string('facebook_url', 500)->nullable();
            $table->string('instagram_url', 500)->nullable();
            $table->string('tiktok_url', 500)->nullable();
            $table->string('website_url', 500)->nullable();
        });
        SchemaSync::alterTable('orders', function (Blueprint $table) {
            $table->string('source', 32)->default('qr_menu')->index();
            $table->string('source_reference', 160)->nullable();
            $table->string('payment_method', 32)->default('cash_on_delivery');
            $table->string('payment_status', 24)->default('unpaid')->index();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropIndex(['payment_status']);
            $table->dropColumn(['source', 'source_reference', 'payment_method', 'payment_status']);
        });
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn(['facebook_url', 'instagram_url', 'tiktok_url', 'website_url']);
        });
    }
};
