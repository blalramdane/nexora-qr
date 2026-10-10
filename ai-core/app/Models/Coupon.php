<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Coupon extends Model
{
    protected $fillable = [
        'restaurant_id', 'code', 'type', 'value', 'minimum_subtotal', 'maximum_discount',
        'usage_limit', 'usage_count', 'starts_at', 'ends_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2', 'minimum_subtotal' => 'decimal:2',
            'maximum_discount' => 'decimal:2', 'starts_at' => 'datetime',
            'ends_at' => 'datetime', 'is_active' => 'boolean',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function discountFor(float $subtotal): float
    {
        if ($this->type === 'percent') {
            $discount = $subtotal * (float) $this->value / 100;
            if ($this->maximum_discount !== null) {
                $discount = min($discount, (float) $this->maximum_discount);
            }
        } else {
            $discount = (float) $this->value;
        }

        return round(min($subtotal, max(0, $discount)), 2);
    }

    public function isUsableNow(): bool
    {
        $now = now();

        return $this->is_active
            && (!$this->starts_at || $this->starts_at->lte($now))
            && (!$this->ends_at || $this->ends_at->gte($now))
            && ($this->usage_limit === null || $this->usage_count < $this->usage_limit);
    }
}
