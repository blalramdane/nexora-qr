<?php

namespace App\Console\Commands;

use App\Models\QrBranch;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateQrDashboardUser extends Command
{
    protected $signature = 'qr:user:create {name} {email} {role=owner} {--branch=}';
    protected $description = 'Create an owner or branch-scoped manager account for the QR dashboard';

    public function handle(): int
    {
        $role = (string) $this->argument('role');
        if (! in_array($role, ['owner', 'branch_manager'], true)) {
            $this->error('Role must be owner or branch_manager.');
            return self::FAILURE;
        }

        $branch = null;
        if ($role === 'branch_manager') {
            $slug = (string) $this->option('branch');
            $branch = QrBranch::query()->where('slug', $slug)->where('is_active', true)->first();
            if (! $branch) {
                $this->error('An active branch slug is required for a branch manager.');
                return self::FAILURE;
            }
        }

        $email = strtolower(trim((string) $this->argument('email')));
        if (User::query()->where('email', $email)->exists()) {
            $this->error('A user with this email already exists.');
            return self::FAILURE;
        }

        $password = $this->secret('Password (input hidden)');
        if (! is_string($password) || strlen($password) < 12) {
            $this->error('Password must be at least 12 characters.');
            return self::FAILURE;
        }

        $user = User::query()->create([
            'name' => (string) $this->argument('name'),
            'email' => $email,
            'password' => Hash::make($password),
            'qr_role' => $role,
            'qr_branch_id' => $branch?->id,
        ]);

        $this->info('QR dashboard user created: '.$user->email.' ('.$role.')');

        return self::SUCCESS;
    }
}
