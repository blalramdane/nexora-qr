<?php

namespace App\Http\Controllers;

use App\Concerns\LogsActivity;
use App\Models\LoyaltyPoint;
use App\Services\LoyaltyService;
use Illuminate\Http\Request;

class LoyaltyController extends Controller
{
    use LogsActivity;

    public function balance(Request $request)
    {
        $phone = $request->query('phone');
        if (! $phone) {
            return response()->json(['points_balance' => 0]);
        }
        $lp = LoyaltyPoint::where('phone', $phone)->first();

        return response()->json(['points_balance' => $lp?->points_balance ?? 0]);
    }

    public function redeem(Request $request, LoyaltyService $service)
    {
        $data = $request->validate([
            'loyalty_point_id' => ['required', 'integer', 'exists:loyalty_points,id'],
            'points' => ['required', 'integer', 'min:1'],
        ]);
        $lp = LoyaltyPoint::findOrFail($data['loyalty_point_id']);
        $tx = $service->redeem($lp, (int) $data['points']);

        return response()->json($tx, 201);
    }
}
