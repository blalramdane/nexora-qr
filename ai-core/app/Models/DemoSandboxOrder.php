<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemoSandboxOrder extends Model
{
    protected $fillable = ['demo_workspace_id', 'order_number', 'customer_name', 'customer_phone', 'items', 'total', 'status'];

    protected function casts(): array
    {
        return ['items' => 'array', 'total' => 'decimal:2'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(DemoWorkspace::class, 'demo_workspace_id');
    }
}
