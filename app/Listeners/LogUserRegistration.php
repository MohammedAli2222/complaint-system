<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Services\AuditService; // ← تأكد من المسار

class LogUserRegistration
{
    protected AuditService $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    public function handle(UserRegistered $event)
    {
        $this->auditService->logAction(
            $event->user->id ?? null, // ← استخدم null إذا ما كان فيه id
            'user_registered',
            [
                'email' => $event->user->email,
                'role' => $event->user->role ?? 'citizen'
            ]
        );
    }
}
