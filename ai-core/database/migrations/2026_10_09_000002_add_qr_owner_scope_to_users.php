<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('qr_role', 24)->default('branch_manager')->index();
            $table->foreignId('qr_branch_id')->nullable()->constrained('qr_branches')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('qr_branch_id');
            $table->dropColumn('qr_role');
        });
    }
};
