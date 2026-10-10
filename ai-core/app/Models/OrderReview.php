<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderReview extends Model
{
    protected $fillable = ['restaurant_id', 'order_id', 'rating', 'comment'];

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function restaurant(): BelongsTo { return $this->belongsTo(Restaurant::class); }
}
