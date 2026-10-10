<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->decimal('delivery_fee', 10, 2)->default(0)->after('whatsapp_phone');
            $table->boolean('delivery_enabled')->default(true)->after('delivery_fee');
            $table->boolean('pickup_enabled')->default(true)->after('delivery_enabled');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('fulfillment_type', 16)->default('pickup')->after('customer_phone');
            $table->text('delivery_address')->nullable()->after('fulfillment_type');
            $table->decimal('delivery_fee', 10, 2)->default(0)->after('subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['fulfillment_type', 'delivery_address', 'delivery_fee']);
        });
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn(['delivery_fee', 'delivery_enabled', 'pickup_enabled']);
        });
    }
};
