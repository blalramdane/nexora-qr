<?php

namespace Tests\Unit;

use Database\Seeders\DatabaseSeeder;
use LogicException;
use Tests\TestCase;

class DatabaseSeederSafetyTest extends TestCase
{
    public function test_demo_database_seeder_refuses_to_run_in_production(): void
    {
        config(['app.env' => 'production']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('DatabaseSeeder creates demonstration accounts');

        app(DatabaseSeeder::class)->run();
    }
}
