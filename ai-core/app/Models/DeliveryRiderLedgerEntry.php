<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryRiderLedgerEntry extends Model
{
    protected $fillable = ['restaurant_id', 'delivery_rider_id', 'created_by', 'settlement_id', 'type', 'amount', 'reference', 'description', 'occurred_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'occurred_at' => 'datetime'];
    }

    public function restaurant(): BelongsTo { return $this->belongsTo(Restaurant::class); }
    public function rider(): BelongsTo { return $this->belongsTo(DeliveryRider::class, 'delivery_rider_id'); }
    public function settlement(): BelongsTo { return $this->belongsTo(DeliveryCashSettlement::class, 'settlement_id'); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
