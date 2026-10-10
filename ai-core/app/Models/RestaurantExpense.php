<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestaurantExpense extends Model
{
    protected $fillable = ['restaurant_id', 'created_by', 'category', 'title', 'amount', 'payment_method', 'expense_date', 'reference', 'notes'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'expense_date' => 'date'];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
