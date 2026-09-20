<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_id','category_id','name','slug','description','image_path',
        'price','compare_at_price','is_available','is_featured','sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'is_available' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    public function restaurant(): BelongsTo { return $this->belongsTo(Restaurant::class); }
    public function category(): BelongsTo { return $this->belongsTo(Category::class); }

    public function modifierGroups(): BelongsToMany
    {
        return $this->belongsToMany(ModifierGroup::class)->withPivot('sort_order')->orderBy('pivot_sort_order');
    }
}