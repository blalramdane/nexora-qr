<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $fillable = ['restaurant_id', 'name', 'phone', 'company_name', 'tax_number', 'notes', 'is_active'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function purchases(): HasMany { return $this->hasMany(Purchase::class); }
}
