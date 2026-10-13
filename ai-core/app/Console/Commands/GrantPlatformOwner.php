<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class GrantPlatformOwner extends Command
{
    protected $signature = 'nexora:grant-platform-owner {email : Email of the NEXORA platform owner account}';

    protected $description = 'Grant cross-restaurant NEXORA owner-studio access to one existing user account.';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->components->error('No user account matches that email. No changes were made.');

            return self::FAILURE;
        }

        if ($user->isPlatformOwner()) {
            $this->components->info('This account is already a NEXORA platform owner.');

            return self::SUCCESS;
        }

        if (! $this->confirm('Grant platform-wide restaurant and menu management to '.$user->name.' ('.$user->email.')?')) {
            $this->components->warn('Cancelled. No changes were made.');

            return self::SUCCESS;
        }

        $user->forceFill(['is_platform_owner' => true])->save();
        $this->components->info('NEXORA platform-owner access enabled.');

        return self::SUCCESS;
    }
}
