<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Idempotent guards: survive an earlier partially-recorded run where
        // the tables/columns exist without a migration record.
        if (! Schema::hasTable('restaurant_tables')) {
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
        }

        if (! Schema::hasColumn('orders', 'restaurant_table_id')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->foreignId('restaurant_table_id')->nullable()->after('menu_id')->constrained('restaurant_tables')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('orders', 'restaurant_table_name')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->string('restaurant_table_name', 80)->nullable()->after('restaurant_table_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (Schema::hasColumn('orders', 'restaurant_table_name')) {
                $table->dropColumn('restaurant_table_name');
            }
            if (Schema::hasColumn('orders', 'restaurant_table_id')) {
                $table->dropConstrainedForeignId('restaurant_table_id');
            }
        });

        Schema::dropIfExists('restaurant_tables');
    }
};
