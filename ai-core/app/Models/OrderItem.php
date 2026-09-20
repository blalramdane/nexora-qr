<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = ['order_id','product_id','product_name','unit_price','quantity','line_total','notes'];

    protected function casts(): array
    {
        return ['unit_price' => 'decimal:2', 'quantity' => 'integer', 'line_total' => 'decimal:2'];
    }

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function modifiers(): HasMany { return $this->hasMany(OrderItemModifier::class); }
}