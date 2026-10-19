<?php

use App\Support\Deployment\SchemaSync;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        // Idempotent guard: survive an earlier partially-recorded run.
        SchemaSync::alterTable('users', function (Blueprint $table): void {
            $table->boolean('is_platform_owner')->default(false)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_platform_owner');
        });
    }
};
