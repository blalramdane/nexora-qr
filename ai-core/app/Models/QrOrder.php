<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrOrder extends Model
{
    protected $table = 'qr_orders';

    protected $fillable = [
        'branch_id', 'source_order_uuid', 'status', 'customer_name', 'customer_phone',
        'customer_address', 'notes', 'fulfillment_type', 'subtotal_minor', 'currency',
        'menu_version', 'items_snapshot', 'payload_hash', 'delivery_lease_token',
        'delivery_lease_expires_at', 'delivery_attempts', 'local_order_id',
        'resolution_note', 'submitted_at', 'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'items_snapshot' => 'array',
            'subtotal_minor' => 'integer',
            'menu_version' => 'integer',
            'delivery_attempts' => 'integer',
            'delivery_lease_expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(QrBranch::class, 'branch_id');
    }
}
