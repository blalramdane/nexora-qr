<?php

namespace Tests\Unit;

use App\Support\Deployment\SchemaSync;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_alter_table_does_not_swallow_duplicate_error_when_an_expected_column_is_missing(): void
    {
        Schema::create('schema_sync_partial', function (Blueprint $table): void {
            $table->id();
            $table->string('already_present');
        });

        try {
            SchemaSync::alterTable('schema_sync_partial', function (Blueprint $table): void {
                $table->string('already_present');
                $table->string('missing_column');
            });

            $this->fail('An incomplete schema must not be accepted as a successful migration.');
        } catch (QueryException) {
            $this->assertFalse(Schema::hasColumn('schema_sync_partial', 'missing_column'));
        }
    }

    public function test_alter_table_accepts_duplicate_error_when_all_expected_columns_exist(): void
    {
        Schema::create('schema_sync_complete', function (Blueprint $table): void {
            $table->id();
            $table->string('first_column');
            $table->string('second_column');
        });

        SchemaSync::alterTable('schema_sync_complete', function (Blueprint $table): void {
            $table->string('first_column');
            $table->string('second_column');
        });

        $this->assertTrue(Schema::hasColumn('schema_sync_complete', 'first_column'));
        $this->assertTrue(Schema::hasColumn('schema_sync_complete', 'second_column'));
    }

    public function test_alter_table_does_not_swallow_duplicate_error_when_expected_index_is_missing(): void
    {
        Schema::create('schema_sync_missing_index', function (Blueprint $table): void {
            $table->id();
            $table->string('already_present');
            $table->string('other_present');
        });

        try {
            SchemaSync::alterTable('schema_sync_missing_index', function (Blueprint $table): void {
                $table->string('already_present')->index();
                $table->string('other_present');
            });

            $this->fail('A missing expected index must not be accepted as a successful migration.');
        } catch (QueryException) {
            $this->assertFalse(Schema::hasIndex('schema_sync_missing_index', ['already_present']));
        }
    }

    public function test_create_table_does_not_accept_an_existing_but_incomplete_table(): void
    {
        Schema::create('schema_sync_existing_table', function (Blueprint $table): void {
            $table->id();
            $table->string('already_present');
        });

        try {
            SchemaSync::createTable('schema_sync_existing_table', function (Blueprint $table): void {
                $table->id();
                $table->string('already_present');
                $table->string('missing_column');
            });

            $this->fail('An existing but incomplete table must not be accepted as a successful migration.');
        } catch (QueryException) {
            $this->assertFalse(Schema::hasColumn('schema_sync_existing_table', 'missing_column'));
        }
    }
}
