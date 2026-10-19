<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ThemeAbTest extends Model
{
    protected $fillable = [
        'restaurant_id', 'menu_id', 'variant_a', 'variant_b', 'status', 'traffic_split',
        'impressions_a', 'impressions_b', 'conversions_a', 'conversions_b', 'started_at', 'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'traffic_split' => 'integer',
            'impressions_a' => 'integer',
            'impressions_b' => 'integer',
            'conversions_a' => 'integer',
            'conversions_b' => 'integer',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ThemeAbTestParticipant::class);
    }
}
