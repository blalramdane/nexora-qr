<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThemeAbTestParticipant extends Model
{
    protected $fillable = ['theme_ab_test_id', 'visitor_token_hash', 'variant', 'converted_at', 'order_id'];

    protected function casts(): array
    {
        return ['converted_at' => 'datetime'];
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(ThemeAbTest::class, 'theme_ab_test_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
