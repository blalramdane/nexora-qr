<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Idempotent guards: earlier deployment runs may have created some of
        // these tables/columns without recording the migration. Re-running
        // must never crash on "table already exists" in production.
        if (! Schema::hasTable('delivery_riders')) {
            Schema::create('delivery_riders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $table->string('phone', 30);
                $table->string('vehicle_type', 30)->default('motorcycle');
                $table->json('coverage_areas');
                $table->string('status', 20)->default('pending')->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('restaurant_delivery_riders')) {
            Schema::create('restaurant_delivery_riders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('delivery_rider_id')->constrained()->cascadeOnDelete();
                $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 20)->default('pending')->index();
                $table->timestamps();
                $table->unique(['restaurant_id', 'delivery_rider_id']);
            });
        }

        if (! Schema::hasColumn('orders', 'delivery_rider_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreignId('delivery_rider_id')->nullable()->after('delivery_address')->constrained('delivery_riders')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('orders', 'delivery_status')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('delivery_status', 24)->default('unassigned')->index()->after('delivery_rider_id');
            });
        }

        if (! Schema::hasColumn('orders', 'delivery_notes')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->text('delivery_notes')->nullable()->after('delivery_status');
            });
        }

        if (! Schema::hasColumn('orders', 'delivery_assigned_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->timestamp('delivery_assigned_at')->nullable()->after('delivery_notes');
            });
        }

        if (! Schema::hasColumn('orders', 'delivery_picked_up_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->timestamp('delivery_picked_up_at')->nullable()->after('delivery_assigned_at');
            });
        }

        if (! Schema::hasColumn('orders', 'delivered_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->timestamp('delivered_at')->nullable()->after('delivery_picked_up_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'delivered_at') || Schema::hasColumn('orders', 'delivery_rider_id')) {
            Schema::table('orders', function (Blueprint $table) {
                if (Schema::hasColumn('orders', 'delivery_rider_id')) {
                    $table->dropConstrainedForeignId('delivery_rider_id');
                }
                $table->dropColumn(array_filter([
                    Schema::hasColumn('orders', 'delivery_status') ? 'delivery_status' : null,
                    Schema::hasColumn('orders', 'delivery_notes') ? 'delivery_notes' : null,
                    Schema::hasColumn('orders', 'delivery_assigned_at') ? 'delivery_assigned_at' : null,
                    Schema::hasColumn('orders', 'delivery_picked_up_at') ? 'delivery_picked_up_at' : null,
                    Schema::hasColumn('orders', 'delivered_at') ? 'delivered_at' : null,
                ]));
            });
        }

        if (Schema::hasTable('restaurant_delivery_riders')) {
            Schema::drop('restaurant_delivery_riders');
        }

        if (Schema::hasTable('delivery_riders')) {
            Schema::drop('delivery_riders');
        }
    }
};
