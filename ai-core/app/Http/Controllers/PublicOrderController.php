<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Inertia\Inertia;
use Inertia\Response;

class PublicOrderController extends Controller
{
    public function __invoke(string $orderNumber): Response
    {
        $order = Order::query()
            ->where('order_number', $orderNumber)
            ->with(['items.modifiers', 'table'])
            ->firstOrFail();

        return Inertia::render('Orders/Confirmation', [
            'order' => [
                'order_number' => $order->order_number,
                'status' => $order->status,
                'total' => $order->total,
                'table' => $order->table?->name,
                'items' => $order->items->map(fn ($item) => [
                    'name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'line_total' => $item->line_total,
                    'modifiers' => $item->modifiers->pluck('modifier_name')->values(),
                ]),
            ],
        ]);
    }
}