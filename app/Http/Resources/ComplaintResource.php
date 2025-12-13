<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use App\Models\User;

class ComplaintResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'               => $this->id,
            'reference_number' => $this->reference_number,
            'type'             => $this->type,
            'location'         => $this->location,
            'description'      => $this->description,
            'status'           => $this->status,

            'citizen' => $this->whenLoaded('user', function () {
                return [
                    'id'    => $this->user->id,
                    'name'  => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),

            'entity' => $this->whenLoaded('entity', function () {
                return [
                    'id'   => $this->entity->id,
                    'name' => $this->entity->name,
                ];
            }),

            'assigned_to' => $this->whenLoaded('assignedTo', function () {
                return $this->assignedTo ? [
                    'id'   => $this->assignedTo->id,
                    'name' => $this->assignedTo->name,
                ] : null;
            }),

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

            'timestamps' => [
                'created_at' => optional($this->created_at)->toDateTimeString(),
                'updated_at' => optional($this->updated_at)->toDateTimeString(),
            ],

            'notes' => $this->whenLoaded('notes', function () {
                return $this->notes->map(function ($note) {
                    return [
                        'id'         => $note->id,
                        'note'       => $note->note,
                        'added_by'   => $note->user?->name ?? 'موظف',
                        'added_at'   => $note->created_at->translatedFormat('Y/m/d - h:i A'),
                    ];
                })->values();
            }),

            // السجل التاريخي (التدقيق)
            'history' => $this->whenLoaded('audits', function () {
                return $this->audits->map(function ($audit) {
                    return [
                        'id'           => $audit->id,
                        'action'       => $audit->event,
                        'description'  => $this->generateDescription($audit),
                        'performed_by' => User::find($audit->user_id)?->name ?? 'النظام',
                        'old_values'   => $audit->old_values ?? null,
                        'new_values'   => $audit->new_values ?? null,
                        'created_at'   => $audit->created_at->toDateTimeString(),
                    ];
                })->values();
            }),

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

    private function generateDescription($audit)
    {
        $old = $audit->old_values ?? [];
        $new = $audit->new_values ?? [];

        return match ($audit->event) {
            'created' => 'تم تقديم الشكوى بنجاح من قبل المواطن',

            'updated' => $this->getUpdatedDescription($old, $new, $audit),

            'request_more_info' => 'تم طلب معلومات إضافية: ' . ($new['message'] ?? 'يرجى تقديم تفاصيل إضافية'),

            'citizen_responded' => 'قام المواطن بالرد على طلب المعلومات الإضافية' .
                ($new['notes'] ? ' مع ملاحظة: ' . $new['notes'] : ''),

            default => "تم تنفيذ إجراء: " . ucfirst(str_replace('_', ' ', $audit->event)),
        };
    }

    private function getUpdatedDescription(array $old, array $new, $audit)
    {
        // تغيير الحالة
        if (isset($new['status']) && (!isset($old['status']) || $old['status'] !== $new['status'])) {
            $oldStatus = $this->translateStatus($old['status'] ?? null);
            $newStatus = $this->translateStatus($new['status']);
            if ($oldStatus === 'غير معروف') $oldStatus = 'غير محددة';
            return "تم تحديث حالة الشكوى من «{$oldStatus}» إلى «{$newStatus}»";
        }

        // قفل الشكوى
        if (
            isset($new['locked_by']) && $new['locked_by'] !== null &&
            (!isset($old['locked_by']) || $old['locked_by'] === null)
        ) {
            $userName = User::find($audit->user_id)?->name ?? 'موظف';
            return "تم قفل الشكوى بواسطة {$userName} لبدء المعالجة";
        }

        // فك القفل
        if (
            isset($new['locked_by']) && $new['locked_by'] === null &&
            isset($old['locked_by']) && $old['locked_by'] !== null
        ) {
            $userName = User::find($audit->user_id)?->name ?? 'موظف';
            return "تم فك قفل الشكوى بواسطة {$userName}";
        }

        // تعيين موظف
        if (isset($new['assigned_to'])) {
            if ($new['assigned_to'] === null) {
                return 'تم إلغاء تعيين الشكوى من الموظف';
            }
            $empName = User::find($new['assigned_to'])?->name ?? 'موظف';
            return "تم تعيين الشكوى للموظف: {$empName}";
        }

        return 'تم تحديث الشكوى';
    }

    private function translateStatus($status): string
    {
        return match ($status) {
            'new'           => 'جديدة',
            'processing'    => 'قيد المعالجة',
            'under_review'  => 'قيد المراجعة - بانتظار ردك',
            'done'          => 'منجزة',
            'rejected'      => 'مرفوضة',
            default         => 'غير معروف',
        };
    }
}
