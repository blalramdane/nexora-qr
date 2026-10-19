<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = ['restaurant_id', 'menu_id', 'restaurant_table_id', 'restaurant_table_name', 'invoice_id', 'source', 'source_reference', 'payment_method', 'payment_status', 'order_number', 'tracking_token_hash', 'tracking_token_encrypted', 'idempotency_key', 'idempotency_request_hash', 'customer_name', 'customer_phone', 'fulfillment_type', 'delivery_address', 'delivery_area', 'delivery_rider_id', 'delivery_status', 'delivery_notes', 'delivery_assigned_at', 'delivery_picked_up_at', 'delivered_at', 'delivery_fee', 'coupon_id', 'coupon_code', 'discount_amount', 'notes', 'status', 'items_count', 'subtotal', 'total'];

    protected function casts(): array
    {
        return ['subtotal' => 'decimal:2', 'delivery_fee' => 'decimal:2', 'discount_amount' => 'decimal:2', 'total' => 'decimal:2', 'delivery_assigned_at' => 'datetime', 'delivery_picked_up_at' => 'datetime', 'delivered_at' => 'datetime'];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function restaurantTable(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'restaurant_table_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->oldest();
    }

    public function review(): HasOne
    {
        return $this->hasOne(OrderReview::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function deliveryRider(): BelongsTo
    {
        return $this->belongsTo(DeliveryRider::class);
    }

    public function cashSettlements(): BelongsToMany
    {
        return $this->belongsToMany(DeliveryCashSettlement::class, 'delivery_cash_settlement_orders')->withPivot('expected_amount')->withTimestamps();
    }

    public function integrationEvents(): HasMany
    {
        return $this->hasMany(OrderIntegrationEvent::class);
    }

    public function deliveryEvents(): HasMany
    {
        return $this->hasMany(DeliveryOrderEvent::class)->latest();
    }

    public function deliveryTrips(): BelongsToMany
    {
        return $this->belongsToMany(DeliveryTrip::class, 'delivery_trip_orders')->withPivot('stop_number')->withTimestamps();
    }
}
