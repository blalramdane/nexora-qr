<?php

namespace App\Http\Middleware;

use App\Models\QrBranchAgent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateQrBranchAgent
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainToken = $request->bearerToken();
        if (! is_string($plainToken) || strlen($plainToken) < 40) {
            return response()->json(['message' => 'Unauthenticated branch agent.'], 401);
        }

        $agent = QrBranchAgent::query()
            ->with('branch')
            ->where('token_hash', hash('sha256', $plainToken))
            ->whereNull('revoked_at')
            ->first();

        if (! $agent || ! $agent->branch || ! $agent->branch->is_active) {
            return response()->json(['message' => 'Unauthenticated branch agent.'], 401);
        }

        $agent->forceFill(['last_seen_at' => now()])->save();
        $agent->branch->forceFill(['last_seen_at' => now()])->save();

        $request->attributes->set('qr_branch_agent', $agent);
        $request->attributes->set('qr_branch', $agent->branch);

        return $next($request);
    }
}
