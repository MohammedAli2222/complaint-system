<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ComplaintResource extends JsonResource
{
    /**
     * تحويل المصدر إلى مصفوفة (JSON).
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'reference_number' => $this->reference_number,
            'type' => $this->type,
            'location' => $this->location,
            'description' => $this->description,
            'status' => $this->status,

            // 1. بيانات المواطن (صاحب الشكوى)
            // نعتمد على العلاقة 'user' التي يتم تحميلها مسبقاً في Repository
            'citizen' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email, // يتم جلب الإيميل بناءً على Repository
                ];
            }),

            // 2. بيانات الجهة الحكومية
            'entity' => $this->whenLoaded('entity', function () {
                return [
                    'id' => $this->entity->id,
                    'name' => $this->entity->name,
                ];
            }),

            // 3. بيانات الموظف المعين له الشكوى
            'assigned_to' => $this->whenLoaded('assignedTo', function () {
                return $this->assignedTo ? [
                    'id' => $this->assignedTo->id,
                    'name' => $this->assignedTo->name,
                ] : null;
            }),

            // 4. تفاصيل القفل (يحتوي الآن على بيانات الموظف القائم بالقفل)
            'lock_details' => [
                'is_locked' => !is_null($this->locked_by),

                // بيانات الموظف الذي قام بالقفل
                'locked_by' => $this->whenLoaded('lockedBy', function () {
                    return $this->lockedBy ? [
                        'id' => $this->lockedBy->id,
                        'name' => $this->lockedBy->name,
                    ] : null;
                }),

                'locked_at' => optional($this->locked_at)->toDateTimeString(),
            ],

            'timestamps' => [
                'created_at' => optional($this->created_at)->toDateTimeString(),
                'updated_at' => optional($this->updated_at)->toDateTimeString(),
            ],

            'history' => $this->whenLoaded('history', function () {
                return $this->history;
            }),

            // تحسين هيكل المرفقات
            'attachments' => $this->whenLoaded('attachments', function () {
                return $this->attachments->map(function ($attachment) {
                    return [
                        'id' => $attachment->id,
                        'file_name' => $attachment->file_name,
                        'file_type' => $attachment->file_type,
                        'file_size_kb' => round($attachment->file_size / 1024, 2),
                        'mime_type' => $attachment->mime_type,
                        // تأكد من أن URL للتنزيل يعمل بشكل صحيح
                        'download_url' => Storage::url($attachment->file_path),
                    ];
                });
            }),
        ];
    }
}
