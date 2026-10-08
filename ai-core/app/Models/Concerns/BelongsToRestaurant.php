<?php

namespace App\Models\Concerns;

use App\Models\Scopes\RestaurantScope;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToRestaurant
{
    protected static function bootBelongsToRestaurant(): void
    {
        static::addGlobalScope(new RestaurantScope());

        static::creating(function ($model): void {
            if (!$model->restaurant_id) {
                $model->restaurant_id = app(TenantContext::class)->id();
            }
        });
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Restaurant::class);
    }
}
