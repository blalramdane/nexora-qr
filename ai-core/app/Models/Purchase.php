<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    protected $fillable = ['restaurant_id', 'supplier_id', 'created_by', 'invoice_number', 'purchase_date', 'total_amount', 'paid_amount', 'notes'];
    protected function casts(): array { return ['purchase_date' => 'date', 'total_amount' => 'decimal:2', 'paid_amount' => 'decimal:2']; }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function items(): HasMany { return $this->hasMany(PurchaseItem::class); }
    public function getRemainingAmountAttribute(): float { return round((float) $this->total_amount - (float) $this->paid_amount, 2); }
}
