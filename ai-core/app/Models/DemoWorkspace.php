<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DemoWorkspace extends Model
{
    protected $fillable = [
        'local_business_id', 'created_by', 'preview_token_hash', 'owner_token_hash',
        'menu_snapshot', 'preview_request', 'status', 'expires_at', 'revoked_at',
    ];

    protected function casts(): array
    {
        return ['menu_snapshot' => 'array', 'preview_request' => 'array', 'expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(LocalBusiness::class, 'local_business_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(DemoSandboxOrder::class, 'demo_workspace_id')->latest();
    }

    public function isUsable(): bool
    {
        return $this->status === 'active' && $this->revoked_at === null && $this->expires_at?->isFuture();
    }
}
