<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Restaurant extends Model
{
    use HasFactory;

    protected $fillable = ['name','slug','phone','whatsapp_phone','facebook_url','instagram_url','tiktok_url','website_url','delivery_fee','delivery_areas','delivery_enabled','pickup_enabled','pos_integration_enabled','pos_provider','pos_endpoint_url','pos_integration_notes','logo_path','timezone','is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'delivery_enabled' => 'boolean', 'pickup_enabled' => 'boolean', 'pos_integration_enabled' => 'boolean', 'delivery_fee' => 'decimal:2', 'delivery_areas' => 'array'];
    }

    public function branches(): HasMany { return $this->hasMany(Branch::class); }
    public function menus(): HasMany { return $this->hasMany(Menu::class); }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'restaurant_users')
            ->withPivot('role')->withTimestamps();
    }

    public function deliveryRiders(): BelongsToMany
    {
        return $this->belongsToMany(DeliveryRider::class, 'restaurant_delivery_riders')
            ->withPivot(['status', 'invited_by'])->withTimestamps();
    }
}
