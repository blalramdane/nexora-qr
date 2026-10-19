<?php

namespace App\Http\Controllers;

use App\Concerns\LogsActivity;
use App\Models\RestaurantTable;
use App\Models\TableServiceRequest;
use Illuminate\Http\Request;

class TableServiceRequestController extends Controller
{
    use LogsActivity;

    public function index(Request $request)
    {
        $requests = TableServiceRequest::where('status', 'pending')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json($requests);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'restaurant_table_id' => ['required', 'integer', 'exists:restaurant_tables,id'],
            'table_token' => ['required', 'string', 'min:32', 'max:128'],
            'request_type' => ['required', 'string', 'in:call_waiter,request_bill,refill_water,other'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $table = RestaurantTable::query()
            ->whereKey($data['restaurant_table_id'])
            ->where('qr_token_hash', hash('sha256', $data['table_token']))
            ->where('is_active', true)
            ->firstOrFail();

        $sr = TableServiceRequest::create([
            'restaurant_id' => $table->restaurant_id,
            'restaurant_table_id' => $table->id,
            'branch_id' => $table->branch_id,
            'request_type' => $data['request_type'],
            'status' => 'pending',
            'note' => $data['note'] ?? null,
        ]);

        return response()->json($sr->fresh(), 201);
    }

    public function acknowledge(TableServiceRequest $serviceRequest)
    {
        $serviceRequest->update(['status' => 'acknowledged', 'acknowledged_at' => now()]);

        return response()->json($serviceRequest);
    }

    public function resolve(TableServiceRequest $serviceRequest)
    {
        $serviceRequest->update(['status' => 'resolved', 'resolved_at' => now()]);

        return response()->json($serviceRequest);
    }
}
