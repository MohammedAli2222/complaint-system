<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ComplaintResource extends JsonResource
{

    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'reference_number' => $this->reference_number,
            'user_id' => $this->user_id,
            'entity_id' => $this->entity_id,
            'type' => $this->type,
            'location' => $this->location,
            'description' => $this->description,
            'status' => $this->status,

            'lock_details' => [
                'locked_by' => $this->locked_by,
                'locked_at' => $this->locked_at ? $this->locked_at->toDateTimeString() : null,
            ],
            'assigned_to' => $this->assigned_to,

            'timestamps' => [
                'created_at' => $this->created_at->toDateTimeString(),
                'updated_at' => $this->updated_at->toDateTimeString(),
            ],

            'history' => $this->whenLoaded('history', function () {
                return $this->history; // يمكن إنشاء Resource خاص لـ History أيضاً
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
                        'download_url' => Storage::url($attachment->file_path),
                    ];
                });
            }),
        ];
    }
}
