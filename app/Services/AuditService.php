<?php

namespace App\Services;

use App\Models\Log;
use Illuminate\Support\Facades\Log as LaravelLog;

class AuditService
{
    public function logAction($userId, $action, $details = null)
    {
        Log::create([
            'user_id' => $userId,
            'action' => $action,
            'details' => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()
        ]);

        LaravelLog::info("User Action: {$action}", [
            'user_id' => $userId,
            'details' => $details,
            'ip' => request()->ip(),
            'timestamp' => now()->toDateTimeString()
        ]);
    }

    public function logSecurityEvent($event, $details = null)
    {
        Log::create([
            'user_id' => null,
            'action' => 'security_event',
            'details' => json_encode(array_merge(['event' => $event], (array)$details), JSON_UNESCAPED_UNICODE),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()
        ]);

        LaravelLog::warning("Security Event: {$event}", [
            'details' => $details,
            'ip' => request()->ip()
        ]);
    }
}
