<?php

namespace App\Console\Commands;

use App\Models\QrBranch;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateQrBranch extends Command
{
    protected $signature = 'qr:branch:create {name} {slug} {code}';
    protected $description = 'Create a branch identity for the QR cloud service';

    public function handle(): int
    {
        $slug = Str::slug((string) $this->argument('slug'));
        $code = strtoupper((string) $this->argument('code'));

        if ($slug === '' || $code === '') {
            $this->error('Branch slug and code must contain valid characters.');
            return self::FAILURE;
        }

        if (QrBranch::query()->where('slug', $slug)->orWhere('code', $code)->exists()) {
            $this->error('A branch with this slug or code already exists.');
            return self::FAILURE;
        }

        $branch = QrBranch::query()->create([
            'name' => (string) $this->argument('name'),
            'slug' => $slug,
            'code' => $code,
        ]);

        $this->info('Branch created: '.$branch->id.' / '.$branch->slug);
        $this->comment('Issue a separate agent credential with: php artisan qr:agent:issue '.$branch->slug);

        return self::SUCCESS;
    }
}
