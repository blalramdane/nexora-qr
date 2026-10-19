<?php

namespace App\Support\Deployment;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Deployment convergence helpers.
 *
 * Production converged from earlier boot runs whose migration records were
 * lost. Recreating every existing table/column is the only allowed way to
 * make `migrate --force` converge again — migrate:fresh / db:wipe are
 * forbidden for production data. These helpers treat "already exists"
 * failures as "migration work already done" and complete normally, letting
 * the migration record be written; any other failure is rethrown untouched.
 */
final class SchemaSync
{
    /**
     * Create a table unless it already exists.
     */
    public static function createTable(string $table, Closure $callback): void
    {
        try {
            Schema::create($table, $callback);
        } catch (QueryException $exception) {
            if (self::isAlreadyExists($exception) && self::allExpectedDefinitionsExist($table, $callback)) {
                return;
            }

            throw $exception;
        }
    }

    /**
     * Alter a table, ignoring failures caused by already-applied changes.
     */
    public static function alterTable(string $table, Closure $callback): void
    {
        try {
            Schema::table($table, $callback);
        } catch (QueryException $exception) {
            if (self::isAlreadyExists($exception) && self::allExpectedDefinitionsExist($table, $callback)) {
                return;
            }

            throw $exception;
        }
    }

    private static function isAlreadyExists(QueryException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'already exists')
            || str_contains($message, 'Duplicate column name')
            || str_contains($message, 'duplicate column name');
    }

    /**
     * Only treat an "already exists" error as a converged migration when all
     * columns requested by its callback are actually present. A duplicate in a
     * multi-column ALTER can otherwise hide later missing columns and cause
     * Laravel to record an incomplete migration as successful.
     */
    private static function allExpectedDefinitionsExist(string $table, Closure $callback): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        $blueprint = new Blueprint(DB::connection(), $table, $callback);

        foreach ($blueprint->getAddedColumns() as $column) {
            if (! Schema::hasColumn($table, $column->name)) {
                return false;
            }
        }

        $columnIndexTypes = [
            'unique' => true,
            'index' => false,
            'fulltext' => false,
            'fullText' => false,
            'spatialIndex' => false,
            'vectorIndex' => false,
        ];

        foreach ($blueprint->getColumns() as $column) {
            foreach ($columnIndexTypes as $attribute => $unique) {
                if ($column->{$attribute} && ! self::hasIndexOnColumns($table, [$column->name], $unique)) {
                    return false;
                }
            }
        }

        foreach ($blueprint->getCommands() as $command) {
            if (in_array($command->name, ['unique', 'index', 'fulltext', 'fullText', 'spatialIndex', 'vectorIndex'], true)) {
                $columns = $command->columns ?? [];
                $unique = $command->name === 'unique';
                if ($columns !== [] && ! self::hasIndexOnColumns($table, $columns, $unique)) {
                    return false;
                }
            }

            if ($command->name === 'foreign') {
                $expectedColumns = array_map('strtolower', (array) ($command->columns ?? []));
                $expectedTable = strtolower((string) ($command->on ?? ''));
                $expectedReferences = array_map('strtolower', (array) ($command->references ?? []));
                $foreignKeys = Schema::getForeignKeys($table);
                $found = false;

                foreach ($foreignKeys as $foreignKey) {
                    if (
                        array_map('strtolower', $foreignKey['columns'] ?? []) === $expectedColumns
                        && strtolower((string) ($foreignKey['foreign_table'] ?? '')) === $expectedTable
                        && array_map('strtolower', $foreignKey['foreign_columns'] ?? []) === $expectedReferences
                    ) {
                        $found = true;
                        break;
                    }
                }

                if (! $found) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Check index columns through introspected metadata so SQLite, MySQL and
     * PostgreSQL index type labels do not cause false negatives.
     *
     * @param  list<string>  $columns
     */
    private static function hasIndexOnColumns(string $table, array $columns, bool $unique = false): bool
    {
        $expected = array_map('strtolower', $columns);

        foreach (Schema::getIndexes($table) as $index) {
            if (
                array_map('strtolower', $index['columns'] ?? []) === $expected
                && (! $unique || (bool) ($index['unique'] ?? false))
            ) {
                return true;
            }
        }

        return false;
    }
}
