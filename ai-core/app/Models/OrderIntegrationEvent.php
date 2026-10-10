<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderIntegrationEvent extends Model
{
    protected $fillable = ['restaurant_id', 'order_id', 'event_type', 'status', 'attempts', 'available_at', 'processed_at', 'last_error', 'payload'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'available_at' => 'datetime', 'processed_at' => 'datetime'];
    }

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function restaurant(): BelongsTo { return $this->belongsTo(Restaurant::class); }
}
