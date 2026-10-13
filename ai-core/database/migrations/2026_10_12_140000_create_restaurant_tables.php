<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('restaurant_tables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->char('qr_token_hash', 64)->unique();
            $table->text('qr_token_encrypted');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['restaurant_id', 'is_active']);
            $table->unique(['restaurant_id', 'name']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('restaurant_table_id')->nullable()->after('menu_id')->constrained('restaurant_tables')->nullOnDelete();
            $table->string('restaurant_table_name', 80)->nullable()->after('restaurant_table_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('restaurant_table_name');
            $table->dropConstrainedForeignId('restaurant_table_id');
        });

        Schema::dropIfExists('restaurant_tables');
    }
};
