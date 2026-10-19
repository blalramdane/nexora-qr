<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_id',
        'order_id',
        'customer_id',
        'menu_id',
        'rating',
        'comment',
        'rating_breakdown',
        'is_approved',
        'is_published',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'rating_breakdown' => 'array',
            'is_approved' => 'boolean',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true)->where('is_approved', true);
    }

    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    public function averageRating(int $restaurantId): float
    {
        return self::published()->where('restaurant_id', $restaurantId)->avg('rating') ?? 0.0;
    }
}
