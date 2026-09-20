<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    public const STATUSES = ['pending', 'confirmed', 'preparing', 'ready', 'completed', 'cancelled'];

    protected $fillable = [
        'restaurant_id','branch_id','table_id','order_number','status',
        'customer_name','customer_phone','notes','subtotal','total','idempotency_key',
    ];

    protected function casts(): array
    {
        return ['subtotal' => 'decimal:2', 'total' => 'decimal:2'];
    }

    public function restaurant(): BelongsTo { return $this->belongsTo(Restaurant::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function table(): BelongsTo { return $this->belongsTo(RestaurantTable::class, 'table_id'); }
    public function items(): HasMany { return $this->hasMany(OrderItem::class); }
    public function events(): HasMany { return $this->hasMany(OrderEvent::class); }
}