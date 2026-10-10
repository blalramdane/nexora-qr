<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryOrderEvent extends Model
{
    protected $fillable = ['restaurant_id', 'order_id', 'delivery_rider_id', 'actor_id', 'event_type', 'from_status', 'to_status', 'notes', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function rider(): BelongsTo { return $this->belongsTo(DeliveryRider::class, 'delivery_rider_id'); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
}
