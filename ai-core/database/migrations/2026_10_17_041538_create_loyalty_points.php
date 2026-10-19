<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The schema uses phone as the stable identifier for public QR
        // customers. An optional customer_id, when present, refers to the
        // existing users table; this project has no separate customers table.
        if (! Schema::hasTable('loyalty_points')) {
            Schema::create('loyalty_points', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('restaurant_id');
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->string('phone', 30)->nullable();
                $table->integer('points_balance')->default(0);
                $table->integer('lifetime_earned')->default(0);
                $table->integer('lifetime_redeemed')->default(0);
                $table->timestamps();
            });
        }

        foreach ([
            [['restaurant_id', 'phone'], 'loyalty_points_restaurant_phone_unique', true],
            [['restaurant_id', 'customer_id'], 'loyalty_points_restaurant_customer_idx', false],
        ] as [$columns, $name, $unique]) {
            if (! Schema::hasIndex('loyalty_points', $columns, $unique ? 'unique' : null)) {
                Schema::table('loyalty_points', fn (Blueprint $table) => $unique
                    ? $table->unique($columns, $name)
                    : $table->index($columns, $name));
            }
        }

        $ensureForeignKey = static function (string $table, string $column, string $referencedTable, string $onDelete, string $name): void {
            $exists = collect(Schema::getForeignKeys($table))->contains(fn (array $key): bool => ($key['columns'] ?? []) === [$column]
                && ($key['foreign_table'] ?? null) === $referencedTable
                && ($key['foreign_columns'] ?? []) === ['id']);

            if ($exists) {
                return;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($column, $referencedTable, $onDelete, $name): void {
                $foreignKey = $blueprint->foreign($column, $name)->references('id')->on($referencedTable);
                if ($onDelete === 'cascade') {
                    $foreignKey->cascadeOnDelete();
                } else {
                    $foreignKey->nullOnDelete();
                }
            });
        };

        $ensureForeignKey('loyalty_points', 'restaurant_id', 'restaurants', 'cascade', 'loyalty_points_restaurant_fk');
        $ensureForeignKey('loyalty_points', 'customer_id', 'users', 'null', 'loyalty_points_customer_fk');

        if (! Schema::hasTable('loyalty_transactions')) {
            Schema::create('loyalty_transactions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('restaurant_id');
                $table->unsignedBigInteger('loyalty_point_id');
                $table->unsignedBigInteger('order_id')->nullable();
                $table->string('type', 20); // earn, redeem, expire, adjust
                $table->integer('points');
                $table->string('description', 255)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasIndex('loyalty_transactions', ['restaurant_id', 'created_at'])) {
            Schema::table('loyalty_transactions', fn (Blueprint $table) => $table->index(['restaurant_id', 'created_at'], 'loyalty_tx_restaurant_created_idx'));
        }

        $ensureForeignKey('loyalty_transactions', 'restaurant_id', 'restaurants', 'cascade', 'loyalty_tx_restaurant_fk');
        $ensureForeignKey('loyalty_transactions', 'loyalty_point_id', 'loyalty_points', 'cascade', 'loyalty_tx_point_fk');
        $ensureForeignKey('loyalty_transactions', 'order_id', 'orders', 'null', 'loyalty_tx_order_fk');
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_transactions');
        Schema::dropIfExists('loyalty_points');
    }
};
