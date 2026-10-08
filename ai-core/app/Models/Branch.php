<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use BelongsToRestaurant, HasFactory;

    protected $fillable = [
        'restaurant_id',
        'name',
        'slug',
        'phone',
        'address',
        'supported_order_types',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'supported_order_types' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
