<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = ['order_id', 'product_id', 'product_name', 'variant_name', 'modifiers', 'quantity', 'unit_price', 'line_total'];

    protected function casts(): array
    {
        return ['unit_price' => 'decimal:2', 'line_total' => 'decimal:2', 'modifiers' => 'array'];
    }

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
}
