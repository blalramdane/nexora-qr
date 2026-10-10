<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\Restaurant;
use App\Services\SyncRestaurantToLocalDirectory;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('nexora:sync-local-directory {--dry-run : Count restaurants without changing directory records}', function (SyncRestaurantToLocalDirectory $sync) {
    $count = 0;

    Restaurant::query()->orderBy('id')->chunkById(100, function ($restaurants) use ($sync, &$count) {
        foreach ($restaurants as $restaurant) {
            $count++;
            if (! $this->option('dry-run')) {
                $sync->sync($restaurant);
            }
        }
    });

    $this->info(($this->option('dry-run') ? 'Would sync ' : 'Synced ').$count.' restaurant(s) to NEXORA Local.');
})->purpose('Backfill and refresh restaurant listings in NEXORA Local');
