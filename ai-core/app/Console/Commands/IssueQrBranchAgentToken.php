<?php

namespace App\Console\Commands;

use App\Models\QrBranch;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class IssueQrBranchAgentToken extends Command
{
    protected $signature = 'qr:agent:issue {branch-slug} {--name=Primary POS}';
    protected $description = 'Issue a branch-bound QR sync token; the plaintext is shown once';

    public function handle(): int
    {
        $branch = QrBranch::query()->where('slug', $this->argument('branch-slug'))->first();
        if (! $branch) {
            $this->error('Branch not found.');
            return self::FAILURE;
        }

        $token = Str::random(64);
        $branch->agents()->create([
            'name' => (string) $this->option('name'),
            'token_hash' => hash('sha256', $token),
        ]);

        $this->newLine();
        $this->warn('Copy this token now. It cannot be retrieved again:');
        $this->line($token);
        $this->comment('Store it in the branch agent secret store; never commit it or expose it to the QR browser.');
        return self::SUCCESS;
    }
}
