<?php

namespace App\Http\Controllers;

use App\Concerns\LogsActivity;
use App\Models\GroupOrder;
use App\Models\GroupOrderParticipant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GroupOrderController extends Controller
{
    use LogsActivity;

    public function create(Request $request)
    {
        $data = $request->validate([
            'restaurant_id' => ['required', 'integer'],
            'menu_id' => ['nullable', 'integer'],
            'max_participants' => ['nullable', 'integer', 'min:2', 'max:20'],
        ]);

        $group = GroupOrder::create([
            'restaurant_id' => $data['restaurant_id'],
            'menu_id' => $data['menu_id'] ?? null,
            'share_token' => Str::random(32),
            'status' => 'open',
            'max_participants' => $data['max_participants'] ?? 10,
            'expires_at' => now()->addHours(24),
        ]);

        $this->logActivity('group_order.created', GroupOrder::class, $group->id);

        return response()->json($group, 201);
    }

    public function show(string $token)
    {
        $group = GroupOrder::where('share_token', $token)->firstOrFail();
        $group->load('participants');

        return response()->json($group);
    }

    public function join(Request $request, string $token)
    {
        $group = GroupOrder::where('share_token', $token)->where('status', 'open')->firstOrFail();
        if ($group->participants()->count() >= $group->max_participants) {
            return response()->json(['error' => 'Group is full.'], 422);
        }
        $data = $request->validate([
            'participant_name' => ['nullable', 'string', 'max:80'],
            'participant_phone' => ['nullable', 'string', 'max:30'],
        ]);
        $p = GroupOrderParticipant::create([
            'group_order_id' => $group->id,
            'participant_name' => $data['participant_name'] ?? null,
            'participant_phone' => $data['participant_phone'] ?? null,
        ]);

        return response()->json($p, 201);
    }
}
