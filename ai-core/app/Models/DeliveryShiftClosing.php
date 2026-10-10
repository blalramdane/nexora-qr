<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryShiftClosing extends Model
{
    protected $fillable = ['restaurant_id', 'delivery_rider_id', 'closed_by', 'shift_date', 'shift_name', 'expected_amount', 'collected_amount', 'handed_over_amount', 'collection_difference', 'handover_difference', 'notes', 'closed_at'];

    protected function casts(): array
    {
        return ['shift_date' => 'date', 'expected_amount' => 'decimal:2', 'collected_amount' => 'decimal:2', 'handed_over_amount' => 'decimal:2', 'collection_difference' => 'decimal:2', 'handover_difference' => 'decimal:2', 'closed_at' => 'datetime'];
    }

    public function restaurant(): BelongsTo { return $this->belongsTo(Restaurant::class); }
    public function rider(): BelongsTo { return $this->belongsTo(DeliveryRider::class, 'delivery_rider_id'); }
    public function closedBy(): BelongsTo { return $this->belongsTo(User::class, 'closed_by'); }
}
