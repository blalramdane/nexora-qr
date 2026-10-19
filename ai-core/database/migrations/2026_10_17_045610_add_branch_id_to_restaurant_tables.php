<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('restaurant_tables', 'branch_id')) {
            Schema::table('restaurant_tables', function (Blueprint $table) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('restaurant_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('restaurant_tables', 'branch_id')) {
            Schema::table('restaurant_tables', function (Blueprint $table) {
                $table->dropColumn('branch_id');
            });
        }
    }
};
