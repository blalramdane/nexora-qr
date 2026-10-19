<?php

namespace App\Concerns;

use App\Models\ActivityLog;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait LogsActivity
{
    protected function logActivity(string $action, ?string $subjectType = null, ?int $subjectId = null, array $metadata = []): void
    {
        try {
            ActivityLog::create([
                'restaurant_id' => app(TenantContext::class)->id() ?? null,
                'user_id' => Auth::id(),
                'action' => $action,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'metadata' => $metadata,
                'ip_address' => Request::ip(),
            ]);
        } catch (\Throwable $e) {
            // Never fail the request because of logging
        }
    }
}
