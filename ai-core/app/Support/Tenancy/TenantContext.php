<?php

namespace App\Support\Tenancy;

use App\Models\Restaurant;

class TenantContext
{
    private ?Restaurant $restaurant = null;

    public function set(?Restaurant $restaurant): void { $this->restaurant = $restaurant; }
    public function restaurant(): ?Restaurant { return $this->restaurant; }
    public function id(): ?int { return $this->restaurant?->id; }
    public function check(): bool { return $this->restaurant !== null; }

    public function clear(): void { $this->restaurant = null; }
}