<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('allergens')->nullable()->after('description');
            $table->json('dietary_tags')->nullable()->after('allergens');
            $table->unsignedSmallInteger('calories')->nullable()->after('dietary_tags');
            $table->unsignedSmallInteger('prep_time_minutes')->nullable()->after('calories');
            $table->string('spice_level', 10)->nullable()->after('prep_time_minutes');
            $table->boolean('is_signature')->default(false)->after('is_featured');
            $table->index(['restaurant_id', 'is_signature']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['restaurant_id', 'is_signature']);
            $table->dropColumn(['allergens', 'dietary_tags', 'calories', 'prep_time_minutes', 'spice_level', 'is_signature']);
        });
    }
};
