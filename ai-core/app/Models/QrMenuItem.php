<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrMenuItem extends Model
{
    protected $table = 'qr_menu_items';

    protected $fillable = [
        'branch_id', 'source_product_id', 'name', 'category_name', 'description',
        'price_minor', 'currency', 'is_available', 'options',
    ];

    protected function casts(): array
    {
        return ['price_minor' => 'integer', 'is_available' => 'boolean', 'options' => 'array'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(QrBranch::class, 'branch_id');
    }
}
