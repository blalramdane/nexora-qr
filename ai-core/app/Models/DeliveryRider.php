<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryRider extends Model
{
    protected $fillable = ['user_id', 'phone', 'vehicle_type', 'coverage_areas', 'open_to_area_orders', 'status'];

    protected function casts(): array
    {
        return ['coverage_areas' => 'array', 'open_to_area_orders' => 'boolean'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function orders(): HasMany { return $this->hasMany(Order::class); }
    public function trips(): HasMany { return $this->hasMany(DeliveryTrip::class, 'delivery_rider_id'); }
    public function ledgerEntries(): HasMany { return $this->hasMany(DeliveryRiderLedgerEntry::class, 'delivery_rider_id'); }
    public function restaurants(): BelongsToMany
    {
        return $this->belongsToMany(Restaurant::class, 'restaurant_delivery_riders')->withPivot(['status', 'invited_by'])->withTimestamps();
    }
}
