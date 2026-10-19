<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_id',
        'order_id',
        'invoice_number',
        'customer_name',
        'customer_phone',
        'customer_email',
        'fulfillment_type',
        'payment_method',
        'payment_status',
        'subtotal',
        'delivery_fee',
        'discount_amount',
        'tax_amount',
        'total',
        'notes',
        'status',
        'issued_at',
        'paid_at',
        'pdf_path',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
            'is_paid' => 'boolean',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function scopeIssued($query)
    {
        return $query->where('status', 'issued');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function markAsPaid(): void
    {
        $this->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    public function generateInvoiceNumber(string $prefix = 'NX-INV'): string
    {
        $last = self::where('restaurant_id', $this->restaurant_id)
            ->where('invoice_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->first();

        $number = $last ? (int) substr($last->invoice_number, strlen($prefix)) + 1 : 1;

        return $prefix.str_pad((string) $number, 6, '0', STR_PAD_LEFT);
    }
}
