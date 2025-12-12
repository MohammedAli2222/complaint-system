<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use App\Models\User;

class ComplaintResource extends JsonResource
{
    /**
     * تحويل المصدر إلى مصفوفة (JSON).
     */
    public function toArray($request)
    {
        return [
            'id'               => $this->id,
            'reference_number' => $this->reference_number,
            'type'             => $this->type,
            'location'         => $this->location,
            'description'      => $this->description,
            'status'           => $this->status,

            // 1. بيانات المواطن (صاحب الشكوى)
            'citizen' => $this->whenLoaded('user', function () {
                return [
                    'id'    => $this->user->id,
                    'name'  => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),

            // 2. بيانات الجهة الحكومية
            'entity' => $this->whenLoaded('entity', function () {
                return [
                    'id'   => $this->entity->id,
                    'name' => $this->entity->name,
                ];
            }),

            // 3. بيانات الموظف المعين
            'assigned_to' => $this->whenLoaded('assignedTo', function () {
                return $this->assignedTo ? [
                    'id'   => $this->assignedTo->id,
                    'name' => $this->assignedTo->name,
                ] : null;
            }),

            // 4. تفاصيل القفل
            'lock_details' => [
                'is_locked'  => !is_null($this->locked_by),
                'locked_by'  => $this->whenLoaded('lockedBy', function () {
                    return $this->lockedBy ? [
                        'id'   => $this->lockedBy->id,
                        'name' => $this->lockedBy->name,
                    ] : null;
                }),
                'locked_at'  => optional($this->locked_at)->toDateTimeString(),
            ],

            // 5. التواريخ
            'timestamps' => [
                'created_at' => optional($this->created_at)->toDateTimeString(),
                'updated_at' => optional($this->updated_at)->toDateTimeString(),
            ],

            // 6. السجل التاريخي (التدقيق الآلي من laravel-auditing)
            'history' => $this->whenLoaded('audits', function () {
                return $this->audits->map(function ($audit) {
                    return [
                        'id'           => $audit->id,
                        'action'       => $audit->event,
                        'description'  => $this->generateDescription($audit),
                        'performed_by' => $audit->user_name ? $audit->user_name : 'النظام',
                        'old_values'   => $audit->old_values ? $audit->old_values : null,
                        'new_values'   => $audit->new_values ? $audit->new_values : null,
                        'ip_address'   => $audit->ip_address,
                        'created_at'   => $audit->created_at->toDateTimeString(),
                    ];
                })->values(); // لإعادة ترقيم المصفوفة
            }),

            // 7. المرفقات
            'attachments' => $this->whenLoaded('attachments', function () {
                return $this->attachments->map(function ($attachment) {
                    return [
                        'id'            => $attachment->id,
                        'file_name'     => $attachment->file_name,
                        'file_type'     => $attachment->file_type,
                        'file_size_kb'  => round($attachment->file_size / 1024, 2),
                        'mime_type'     => $attachment->mime_type,
                        'download_url'  => Storage::url($attachment->file_path),
                    ];
                });
            }),
        ];
    }

    /**
     * توليد وصف عربي واضح لكل حدث تدقيق
     */
    private function generateDescription($audit)
    {
        return match ($audit->event) {
            'created'   => 'تم تقديم الشكوى بنجاح من قبل المواطن',
            'updated'   => $this->getStatusChangeDescription($audit),
            'assigned'  => $this->getAssignmentDescription($audit),
            'locked'    => "تم قفل الشكوى بواسطة " . ($audit->user_name ? $audit->user_name : 'النظام') . " لبدء المعالجة",
            'unlocked'  => "تم فك قفل الشكوى بواسطة " . ($audit->user_name ? $audit->user_name : 'النظام'),
            'deleted'   => 'تم حذف الشكوى (غير متوقع في النظام العادي)',
            default     => "تم تنفيذ إجراء: " . ucfirst(str_replace('_', ' ', $audit->event)),
        };
    }

    /**
     * وصف خاص بتغيير الحالة (الأكثر أهمية وشيوعاً)
     */
    private function getStatusChangeDescription($audit)
    {
        $oldStatus = $audit->old_values['status'] ? $audit->old_values['status'] : null;
        $newStatus = $audit->new_values['status'] ? $audit->new_values['status'] : null;

        $translate = [
            'new'          => 'جديدة',
            'processing'   => 'قيد المعالجة',
            'under_review' => 'قيد المراجعة - بانتظار ردك',
            'done'         => 'منجزة',
            'rejected'     => 'مرفوضة',
        ];

        $oldAr = $oldStatus ? ($translate[$oldStatus] ? $translate[$oldStatus] : $oldStatus) : 'غير معروف';
        $newAr = $newStatus ? ($translate[$newStatus] ? $translate[$newStatus] : $newStatus) : 'غير معروف';

        return "تم تحديث حالة الشكوى من «{$oldAr}» إلى «{$newAr}»";
    }

    /**
     * وصف خاص بالتعيين
     */
    private function getAssignmentDescription($audit)
    {
        $employeeId = $audit->new_values['assigned_to'] ? $audit->new_values['assigned_to'] : null;

        if (!$employeeId) {
            return 'تم إلغاء تعيين الشكوى من الموظف';
        }

        $employeeName = User::find($employeeId) ? User::find($employeeId)->name : 'موظف غير معروف';

        return "تم تعيين الشكوى للموظف: {$employeeName}";
    }
}

