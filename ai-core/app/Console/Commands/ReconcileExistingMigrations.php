<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Deployment convergence helper.
 *
 * Some production databases were created by earlier boot attempts whose
 * migration records were lost. Rather than running destructive re-creation
 * commands (migrate:fresh / db:wipe are forbidden for production data), this
 * command marks a pending migration as applied when it is provably safe to
 * do so: every table the migration creates already exists and the migration's
 * up() contains no column-level alteration of shared tables.
 *
 * It is a no-op on any database that is already in sync.
 */
class ReconcileExistingMigrations extends Command
{
    protected $signature = 'migrations:reconcile-existing {--dry-run : Show what would be marked without writing}';

    protected $description = 'Mark pending migrations applied when all tables they create already exist (no data touched)';

    public function handle(): int
    {
        $migrator = $this->laravel['migrator'];
        $repository = $migrator->getRepository();

        /** @var array<string, string> $files Map migration name to file path. */
        $files = $migrator->getMigrationFiles([database_path('migrations')]);
        $ran = $repository->getRan();

        $safeToMark = [];
        foreach ($files as $name => $path) {
            if (in_array($name, $ran, true)) {
                continue;
            }

            $source = file_get_contents($path);
            $upSource = $this->upSection($source);

            if ($upSource === null) {
                continue;
            }

            if (preg_match_all("/Schema::create\(\s*'([^']+)'/", $upSource, $matches) === 0) {
                continue; // No table creation; leave for migrate (column/data upgrades).
            }

            if (preg_match('/Schema::table\(/', $upSource) === 1) {
                // Mixed migration: its column changes must be guarded in the file itself.
                continue;
            }

            $missing = collect($matches[1])->filter(
                fn (string $table): bool => ! Schema::hasTable($table)
            );

            if ($missing->isNotEmpty()) {
                continue;
            }

            $safeToMark[] = $name;
        }

        if ($safeToMark === []) {
            $this->info('No migration records need reconciliation.');

            return self::SUCCESS;
        }

        foreach ($safeToMark as $name) {
            $this->line(($this->option('dry-run') ? '[dry-run] would mark ' : 'marking ').'applied: '.$name);
        }

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        $batch = $repository->getNextBatchNumber();
        foreach ($safeToMark as $name) {
            $repository->log($name, $batch);
        }

        return self::SUCCESS;
    }

    private function upSection(string $source): ?string
    {
        $start = strpos($source, 'function up');
        $end = strpos($source, 'function down');

        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        return substr($source, $start, $end - $start);
    }
}
