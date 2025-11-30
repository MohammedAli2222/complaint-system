<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AuditLogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected ?int $userId;
    protected string $action;
    protected array $details;

    public function __construct(?int $userId, string $action, array $details = [])
    {
        $this->userId  = $userId;
        $this->action  = $action;
        $this->details = $details;
    }

    public function handle(): void
    {
        // مثال بسيط: تسجيل في ملف audit.log منفصل
        Log::channel('audit')->info('AUDIT', [
            'user_id'   => $this->userId,
            'action'    => $this->action,
            'details'   => $this->details,
            'ip'        => request()->ip(),
            'user_agent'=> request()->userAgent(),
            'url'       => request()->fullUrl(),
            'timestamp' => now()->toDateTimeString(),
        ]);

        // إذا كنت تستخدم جدول audit_logs في المستقبل:
        // \App\Models\AuditLog::create([...]);
    }
}
