<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LocalBusiness extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_id', 'name', 'slug', 'city', 'area', 'address', 'category', 'phone', 'website_url', 'maps_url',
        'source', 'source_place_id', 'source_url', 'menu_url', 'menu_status', 'claim_status',
        'listing_status', 'internal_notes', 'source_checked_at', 'lead_status', 'confidence_score',
        'last_contacted_at', 'next_action_at', 'contact_channel', 'evidence_notes', 'menu_rights_status',
    ];

    protected function casts(): array
    {
        return ['source_checked_at' => 'datetime', 'last_contacted_at' => 'datetime', 'next_action_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (LocalBusiness $business) {
            if (! $business->slug) {
                $base = Str::slug($business->name) ?: 'local-business';
                $slug = $base;
                $suffix = 2;
                while (static::query()->where('slug', $slug)->exists()) {
                    $slug = $base.'-'.$suffix++;
                }
                $business->slug = $slug;
            }
        });
    }

    public function scopePubliclyListed($query)
    {
        return $query->where('listing_status', 'published');
    }

    public function activities()
    {
        return $this->hasMany(LocalBusinessActivity::class)->latest('occurred_at');
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
