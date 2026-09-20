<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemModifier extends Model
{
    use HasFactory;

    protected $fillable = ['order_item_id','modifier_id','modifier_name','price_delta'];

    protected function casts(): array
    {
        return ['price_delta' => 'decimal:2'];
    }

    public function item(): BelongsTo { return $this->belongsTo(OrderItem::class, 'order_item_id'); }
    public function modifier(): BelongsTo { return $this->belongsTo(Modifier::class); }
}