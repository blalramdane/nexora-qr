<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A public, opaque invitation for a restaurant to choose and request a demo.
 * The bearer token is only stored as a SHA-256 hash.
 */
class DemoInvitation extends Model
{
    protected $fillable = [
        'local_business_id', 'created_by', 'demo_workspace_id', 'token_hash', 'requested_mode', 'style',
        'note', 'requested_features', 'status', 'expires_at', 'revoked_at', 'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_features' => 'array',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(LocalBusiness::class, 'local_business_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(DemoWorkspace::class, 'demo_workspace_id');
    }

    public function isUsable(): bool
    {
        return in_array($this->status, ['invited', 'requested'], true)
            && $this->revoked_at === null
            && $this->expires_at?->isFuture();
    }
}
