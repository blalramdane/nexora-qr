<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrBranchAgent extends Model
{
    protected $table = 'qr_branch_agents';

    protected $fillable = ['branch_id', 'name', 'token_hash', 'last_seen_at', 'revoked_at'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(QrBranch::class, 'branch_id');
    }
}
