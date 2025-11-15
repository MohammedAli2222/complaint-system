<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AuditService
{
    public function logAction($userId, $action, $details = null)
    {
        Log::info("User Action Logged", [
            'user_id' => $userId,
            'action' => $action,
            'details' => $details,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toDateTimeString()
        ]);
    }

    public function logSecurityEvent($event, $details = null)
    {
        Log::warning("Security Event", [
            'event' => $event,
            'details' => $details,
            'ip_address' => request()->ip(),
            'timestamp' => now()->toDateTimeString()
        ]);
    }
}
