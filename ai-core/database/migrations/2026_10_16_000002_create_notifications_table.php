<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table): void {
                $table->id();
                // Define the FK columns and constraints separately. Calling
                // ->index() after ->constrained() mutates Laravel's foreign-key
                // definition and can produce constraint name "1" on MySQL.
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('restaurant_id')->nullable();
                $table->string('type', 64); // order.new, order.status, payment.received, review.new, system.alert
                $table->string('title', 200);
                $table->text('body')->nullable();
                $table->json('data')->nullable(); // order_id, invoice_id, etc.
                $table->string('channel', 32)->default('in_app'); // in_app, email, whatsapp, push
                $table->boolean('is_read')->default(false);
                $table->timestamp('read_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        foreach ([
            [['type'], 'notifications_type_index'],
            [['is_read'], 'notifications_is_read_index'],
            [['user_id', 'is_read', 'created_at'], 'notifications_user_read_created_idx'],
            [['restaurant_id', 'created_at'], 'notifications_restaurant_created_idx'],
        ] as [$columns, $indexName]) {
            if (! Schema::hasIndex('notifications', $columns)) {
                Schema::table('notifications', fn (Blueprint $table) => $table->index($columns, $indexName));
            }
        }

        $foreignKeys = Schema::getForeignKeys('notifications');
        $hasUserForeignKey = collect($foreignKeys)->contains(fn (array $key): bool => ($key['columns'] ?? []) === ['user_id']
            && ($key['foreign_table'] ?? null) === 'users'
            && ($key['foreign_columns'] ?? []) === ['id']);

        if (! $hasUserForeignKey) {
            Schema::table('notifications', fn (Blueprint $table) => $table->foreign('user_id', 'notifications_user_id_fk')->references('id')->on('users')->nullOnDelete());
        }

        $foreignKeys = Schema::getForeignKeys('notifications');
        $hasRestaurantForeignKey = collect($foreignKeys)->contains(fn (array $key): bool => ($key['columns'] ?? []) === ['restaurant_id']
            && ($key['foreign_table'] ?? null) === 'restaurants'
            && ($key['foreign_columns'] ?? []) === ['id']);

        if (! $hasRestaurantForeignKey) {
            Schema::table('notifications', fn (Blueprint $table) => $table->foreign('restaurant_id', 'notifications_restaurant_id_fk')->references('id')->on('restaurants')->nullOnDelete());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
