<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocalBusinessActivity extends Model
{
    protected $fillable = [
        'local_business_id', 'user_id', 'type', 'outcome', 'channel', 'notes', 'occurred_at',
    ];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(LocalBusiness::class, 'local_business_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
