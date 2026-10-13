<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RestaurantTable extends Model
{
    protected $fillable = ['restaurant_id', 'name', 'qr_token_hash', 'qr_token_encrypted', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected $hidden = ['qr_token_hash', 'qr_token_encrypted'];

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'restaurant_table_id');
    }
}
