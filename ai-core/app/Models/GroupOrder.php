<?php

namespace App\Models;

use App\Models\Concerns\BelongsToRestaurant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GroupOrder extends Model
{
    use BelongsToRestaurant, HasFactory;

    protected $fillable = ['restaurant_id', 'menu_id', 'host_order_id', 'share_token', 'status', 'max_participants', 'expires_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'max_participants' => 'integer'];
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function hostOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'host_order_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(GroupOrderParticipant::class);
    }
}
