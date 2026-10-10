<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('local_businesses', function (Blueprint $table) {
            $table->foreignId('restaurant_id')->nullable()->unique()->after('id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('local_businesses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('restaurant_id');
        });
    }
};
