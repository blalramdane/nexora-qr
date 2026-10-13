<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreatePlatformOwner extends Command
{
    protected $signature = 'nexora:create-platform-owner
        {email=nexora.owner@example.com : Login email for the new NEXORA platform-owner account}
        {--name=NEXORA Platform Owner : Display name for the account}';

    protected $description = 'Create a new NEXORA platform-owner account with a generated one-time password.';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->components->error('Enter a valid email address. No account was created.');

            return self::FAILURE;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->components->error('That email already belongs to an account. Nothing was changed.');

            return self::FAILURE;
        }

        if (! $this->confirm('Create a new platform-owner account for '.$email.' with access to all NEXORA restaurants?')) {
            $this->components->warn('Cancelled. No changes were made.');

            return self::SUCCESS;
        }

        $password = Str::password(28, letters: true, numbers: true, symbols: true);
        $user = User::create([
            'name' => trim((string) $this->option('name')) ?: 'NEXORA Platform Owner',
            'email' => $email,
            'password' => $password,
        ]);
        $user->forceFill(['is_platform_owner' => true])->save();

        if (! $user->fresh()->isPlatformOwner()) {
            $this->components->error('The account was created but its owner flag could not be verified.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info('NEXORA platform-owner account created. Save the one-time password now:');
        $this->table(['Field', 'Value'], [
            ['Name', $user->name],
            ['Login email', $user->email],
            ['Temporary password', $password],
        ]);
        $this->newLine();
        $this->components->warn('Change the email and password after your first login at /owner/account.');

        return self::SUCCESS;
    }
}
