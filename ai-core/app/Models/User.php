<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_owner' => 'boolean',
        ];
    }

    public function isPlatformOwner(): bool
    {
        return (bool) $this->is_platform_owner;
    }

    public function restaurants(): BelongsToMany
    {
        return $this->belongsToMany(Restaurant::class, 'restaurant_users')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function deliveryRider()
    {
        return $this->hasOne(DeliveryRider::class);
    }

    public function roleIn(Restaurant $restaurant): ?string
    {
        $membership = $this->restaurants()->whereKey($restaurant->getKey())->first();

        return $membership?->pivot?->role;
    }

    public function isOwnerOf(Restaurant $restaurant): bool
    {
        return $this->roleIn($restaurant) === 'owner';
    }
}
