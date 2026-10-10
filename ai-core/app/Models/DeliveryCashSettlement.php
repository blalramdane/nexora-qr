<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DeliveryCashSettlement extends Model
{
    protected $fillable = ['restaurant_id', 'delivery_rider_id', 'created_by', 'confirmed_by', 'expected_amount', 'collected_amount', 'handed_over_amount', 'status', 'notes', 'confirmed_at'];

    protected function casts(): array
    {
        return ['expected_amount' => 'decimal:2', 'collected_amount' => 'decimal:2', 'handed_over_amount' => 'decimal:2', 'confirmed_at' => 'datetime'];
    }

    public function restaurant(): BelongsTo { return $this->belongsTo(Restaurant::class); }
    public function rider(): BelongsTo { return $this->belongsTo(DeliveryRider::class, 'delivery_rider_id'); }
    public function orders(): BelongsToMany { return $this->belongsToMany(Order::class, 'delivery_cash_settlement_orders')->withPivot('expected_amount')->withTimestamps(); }
}
