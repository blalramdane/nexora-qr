<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Template extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'name', 'description', 'category', 'is_premium', 'is_active'];

    protected function casts(): array
    {
        return ['is_premium' => 'boolean', 'is_active' => 'boolean'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(TemplateVersion::class);
    }

    public function activeVersion(): HasMany
    {
        return $this->hasMany(TemplateVersion::class)->where('is_active', true)->latestOfMany('version');
    }
}
