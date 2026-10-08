<?php

namespace App\Support\Tenancy;

use App\Models\Restaurant;
use LogicException;

final class TenantContext
{
    private ?Restaurant $restaurant = null;

    public function set(Restaurant $restaurant): void
    {
        $this->restaurant = $restaurant;
    }

    public function restaurant(): Restaurant
    {
        if (!$this->restaurant) {
            throw new LogicException('No Nexora tenant is active for this request.');
        }

        return $this->restaurant;
    }

    public function id(): int
    {
        return $this->restaurant()->getKey();
    }

    public function hasTenant(): bool
    {
        return $this->restaurant !== null;
    }

    public function clear(): void
    {
        $this->restaurant = null;
    }
}
