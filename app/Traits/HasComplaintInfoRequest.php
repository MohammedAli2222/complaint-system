<?php

namespace App\Traits;

use App\Events\RequestMoreInfo;
use App\Events\CitizenResponded;
use App\Models\Complaint;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

trait HasComplaintInfoRequest
{
    protected function performRequestMoreInfo(Complaint $complaint, string $message, User $user): void
    {
        DB::transaction(function () use ($complaint, $message, $user) {
            $oldStatus = $complaint->status;

            $complaint->update(['status' => 'under_review']);

            $complaint->audits()->create([
                'event'      => 'request_more_info',
                'old_values' => ['status' => $oldStatus],
                'new_values' => ['status' => 'under_review', 'message' => $message],
                'user_id'    => $user->id,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            if (property_exists($this, 'audit')) {
                $this->audit->logAction($user->id, 'complaint.info_requested', [
                    'complaint_id' => $complaint->id,
                    'message'      => $message,
                ]);
            }

            broadcast(new RequestMoreInfo($complaint, $message))->toOthers();
        });
    }

    protected function performCitizenRespondToInfoRequest(Complaint $complaint, Request $request, User $user): Complaint
    {
        return DB::transaction(function () use ($complaint, $request, $user) {
            if ($complaint->user_id !== $user->id) {
                throw new \Exception('لا تملك صلاحية للرد على هذه الشكوى.', 403);
            }

            if ($complaint->status !== 'under_review') {
                throw new \Exception('لا يمكن الرد على شكوى حالتها ليست "قيد المراجعة".', 400);
            }

            if ($request->hasFile('files') && $complaint->attachments()->count() + count($request->file('files')) > 10) {
                throw new \Exception('تم تجاوز الحد الأقصى لعدد المرفقات (10).', 400);
            }

            if ($request->hasFile('files')) {
                $files = $request->file('files');
                $files = is_array($files) ? $files : [$files];

                $this->handleAttachments($complaint, $files, $user);
            }

            $oldStatus = $complaint->status;
            $notes = $request->input('notes');

            $complaint->update(['status' => 'processing']);

            $complaint->audits()->create([
                'event'      => 'citizen_responded',
                'old_values' => ['status' => $oldStatus],
                'new_values' => [
                    'status'            => 'processing',
                    'notes'             => $notes,
                    'attachments_added' => $request->hasFile('files') ? count($request->file('files')) : 0
                ],
                'user_id'    => $user->id,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            if (property_exists($this, 'audit')) {
                $this->audit->logAction($user->id, 'citizen.info_responded', [
                    'complaint_id'      => $complaint->id,
                    'attachments_count' => $request->hasFile('files') ? count($request->file('files')) : 0
                ]);
            }

            event(new CitizenResponded($complaint));

            return $complaint;
        });
    }

    // الدالة المفقودة - أضفها هنا
    protected function handleAttachments(Complaint $complaint, array $files, User $user): void
    {
        foreach ($files as $file) {
            if (!$file->isValid()) {
                continue;
            }

            $storagePath = "complaints/{$complaint->reference_number}";
            $path = Storage::disk('public')->putFile($storagePath, $file);

            $complaint->attachments()->create([
                'file_path'     => $path,
                'file_name'     => $file->getClientOriginalName(),
                'file_type'     => $file->extension(),
                'file_size'     => $file->getSize(),
                'mime_type'     => $file->getClientMimeType(),
            ]);
        }
    }
}
