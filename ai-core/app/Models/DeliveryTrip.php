<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DeliveryTrip extends Model
{
    protected $fillable = ['restaurant_id', 'delivery_rider_id', 'trip_number', 'status', 'notes', 'started_at', 'completed_at', 'created_by'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function restaurant(): BelongsTo { return $this->belongsTo(Restaurant::class); }
    public function rider(): BelongsTo { return $this->belongsTo(DeliveryRider::class, 'delivery_rider_id'); }
    public function orders(): BelongsToMany { return $this->belongsToMany(Order::class, 'delivery_trip_orders')->withPivot('stop_number')->withTimestamps()->orderByPivot('stop_number'); }
}
