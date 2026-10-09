<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QrBranch extends Model
{
    protected $table = 'qr_branches';

    protected $fillable = ['name', 'slug', 'code', 'currency', 'menu_version', 'is_active', 'last_seen_at'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_seen_at' => 'datetime', 'menu_version' => 'integer'];
    }

    public function agents(): HasMany
    {
        return $this->hasMany(QrBranchAgent::class, 'branch_id');
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(QrMenuItem::class, 'branch_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(QrOrder::class, 'branch_id');
    }
}
