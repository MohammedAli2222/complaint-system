<?php

namespace App\Listeners;

use App\Events\EmployeeCreated;
use App\Services\AuditService; // استيراد خدمة التدقيق

class LogEmployeeCreation
{
    protected AuditService $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Handle the event.
     */
    public function handle(EmployeeCreated $event): void
    {
        $employee = $event->employee;

        $this->auditService->logSecurityEvent('employee_created', [
            'employee_id' => $employee->id,
            'employee_email' => $employee->email,
            'created_by_user' => auth()->check() ? auth()->user()->email : 'System',
            'entity_id' => $employee->entity_id,
        ]);
    }
}
