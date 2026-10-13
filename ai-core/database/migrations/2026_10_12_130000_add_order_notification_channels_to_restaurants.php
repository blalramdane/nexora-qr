<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table): void {
            $table->boolean('dashboard_order_notifications_enabled')->default(true);
            $table->boolean('whatsapp_order_notifications_enabled')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table): void {
            $table->dropColumn(['dashboard_order_notifications_enabled', 'whatsapp_order_notifications_enabled']);
        });
    }
};
