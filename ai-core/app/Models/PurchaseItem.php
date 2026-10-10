<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    protected $fillable = ['purchase_id', 'item_name', 'quantity', 'unit', 'unit_cost', 'line_total'];
    protected function casts(): array { return ['quantity' => 'decimal:3', 'unit_cost' => 'decimal:3', 'line_total' => 'decimal:2']; }
    public function purchase(): BelongsTo { return $this->belongsTo(Purchase::class); }
}
