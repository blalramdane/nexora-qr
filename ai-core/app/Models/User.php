<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed'];
    }

    public function restaurants(): BelongsToMany
    {
        return $this->belongsToMany(Restaurant::class)->withPivot(['role', 'is_active'])->withTimestamps();
    }

    public function hasRole(string $role, ?Restaurant $restaurant = null): bool
    {
        $restaurant ??= app(\App\Support\Tenancy\TenantContext::class)->restaurant();
        if (!$restaurant) return false;
        return $this->restaurants()->whereKey($restaurant->id)->wherePivot('role', $role)->wherePivot('is_active', true)->exists();
    }

    public function isRestaurantOwner(?Restaurant $restaurant = null): bool
    {
        return $this->hasRole('owner', $restaurant);
    }
}