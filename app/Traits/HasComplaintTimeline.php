<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Support\Collection;

trait HasComplaintTimeline
{
    use TranslatesComplaintStatus; // سننشئه لاحقًا لترجمة الحالة

    /**
     * بناء الـ timeline الكامل من الشكوى
     */
    public function buildTimeline($complaint, User $citizen): Collection
    {
        $timeline = collect();

        // حدث تقديم الشكوى
        $timeline->push([
            'date'        => $complaint->created_at->translatedFormat('Y/m/d - h:i A'),
            'actor'       => 'أنت',
            'actor_type'  => 'citizen',
            'action'      => 'تقديم الشكوى',
            'description' => 'تم تقديم الشكوى بنجاح وإرسالها إلى الجهة المختصة.',
        ]);

        $previousState = [];

        foreach ($complaint->audits as $audit) {
            $actorName = User::find($audit->user_id)?->name ?? 'النظام';
            $isCitizen = $audit->user_id === $citizen->id;
            $actorType = $isCitizen ? 'أنت' : 'موظف';

            $old = $audit->old_values ?? [];
            $new = $audit->new_values ?? [];

            // تجنب التكرار
            if ($new === $previousState) {
                continue;
            }
            $previousState = $new;

            [$action, $description] = $this->parseAuditEvent($audit, $actorName);

            if (!$action) {
                continue;
            }

            $timeline->push([
                'date'        => $audit->created_at->translatedFormat('Y/m/d - h:i A'),
                'actor'       => $actorName,
                'actor_type'  => $actorType,
                'action'      => $action,
                'description' => $description,
            ]);
        }

        return $timeline;
    }

    private function parseAuditEvent($audit, string $actorName): array
    {
        $old = $audit->old_values ?? [];
        $new = $audit->new_values ?? [];

        if (isset($new['locked_by']) && $new['locked_by'] !== null &&
            (!isset($old['locked_by']) || $old['locked_by'] === null)) {
            return ['قفل الشكوى', "قام {$actorName} بقفل الشكوى لبدء معالجتها"];
        }

        if (isset($new['locked_by']) && $new['locked_by'] === null &&
            isset($old['locked_by']) && $old['locked_by'] !== null) {
            return ['فك قفل الشكوى', "قام {$actorName} بفك قفل الشكوى"];
        }

        if (isset($new['status']) && $new['status'] !== ($old['status'] ?? null)) {
            $oldStatus = $this->translateStatus($old['status'] ?? null);
            $newStatus = $this->translateStatus($new['status']);
            if ($oldStatus === 'غير معروف') $oldStatus = 'غير محددة';

            return ['تحديث حالة الشكوى', "تم تغيير حالة الشكوى من «{$oldStatus}» إلى «{$newStatus}» بواسطة {$actorName}"];
        }

        if ($audit->event === 'request_more_info') {
            return ['طلب معلومات إضافية', 'تم طلب معلومات إضافية منك: ' . ($new['message'] ?? '')];
        }

        if ($audit->event === 'citizen_responded') {
            return ['ردك على طلب المعلومات', 'قمت بالرد على طلب المعلومات الإضافية بنجاح' . ($new['notes'] ? ' مع ملاحظة: ' . $new['notes'] : '')];
        }

        return [null, null];
    }
}
