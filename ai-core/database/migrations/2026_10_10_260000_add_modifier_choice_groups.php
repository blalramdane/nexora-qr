<?php

use App\Support\Deployment\SchemaSync;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        // Idempotent guard: survive an earlier partially-recorded run.
        SchemaSync::alterTable('modifiers', function (Blueprint $table) {
            $table->string('group_name', 120)->nullable()->after('name');
            $table->unsignedTinyInteger('group_min_choices')->default(0)->after('group_name');
            $table->unsignedTinyInteger('group_max_choices')->default(1)->after('group_min_choices');
            $table->index(['restaurant_id', 'product_id', 'group_name']);
        });
    }

    public function down(): void
    {
        Schema::table('modifiers', function (Blueprint $table) {
            $table->dropIndex(['restaurant_id', 'product_id', 'group_name']);
            $table->dropColumn(['group_name', 'group_min_choices', 'group_max_choices']);
        });
    }
};
