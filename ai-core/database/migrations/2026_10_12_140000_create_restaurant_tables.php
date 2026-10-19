<?php

use App\Support\Deployment\SchemaSync;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        // Idempotent guards: survive an earlier partially-recorded run where
        // the tables/columns exist without a migration record.
        SchemaSync::createTable('restaurant_tables', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('name', 80);
            $table->string('slug', 80)->nullable()->unique();
            $table->integer('capacity')->default(4);
            $table->text('qr_token_encrypted')->nullable();
            $table->char('qr_token_hash', 64)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['restaurant_id', 'is_active']);
            $table->unique(['restaurant_id', 'name']);
        });

        SchemaSync::alterTable('orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('restaurant_table_id')->nullable()->after('menu_id');
            $table->string('restaurant_table_name', 80)->nullable()->after('restaurant_table_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('restaurant_table_name');
            $table->dropColumn('restaurant_table_id');
        });

        Schema::dropIfExists('restaurant_tables');
    }
};
