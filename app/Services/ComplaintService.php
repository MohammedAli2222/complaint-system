<?php
// app/Services/ComplaintService.php

namespace App\Services;

use App\Repositories\ComplaintRepository;
use App\Events\ComplaintSubmitted;
use App\Events\ComplaintStatusUpdated;
use App\Events\ComplaintAssigned;        // ← جديد
use App\Events\RequestMoreInfo;
use Illuminate\Http\JsonResponse;
use App\Models\ComplaintHistory; // تأكد من وجود الموديل
use App\Mail\ComplaintStatusMail; // استدعاء كلاس الإيميل
use App\Models\Complaint;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ComplaintService
{
    protected ComplaintRepository $repo;
    protected AuditService $audit;

    public function __construct(ComplaintRepository $repo, AuditService $audit)
    {
        $this->repo = $repo;
        $this->audit = $audit;
    }

    // 1. تقديم شكوى
    public function submit(array $data, $user, ?Request $request = null)
    {
        return DB::transaction(function () use ($data, $user, $request) {

            // 1. توليد رقم مرجعي وإنشاء الشكوى
            $ref = $this->repo->generateUniqueReference();

            $complaint = $this->repo->create([
                'reference_number' => $ref,
                'user_id' => $user->id,
                'entity_id' => $data['entity_id'],
                'type' => $data['type'],
                'location' => $data['location'] ?? null,
                'description' => $data['description'],
                'status' => 'new'
            ]);

            if ($request && $request->hasFile('files')) {
                $files = $request->file('files');

                // إذا كان ملف واحد فقط
                if ($files instanceof UploadedFile) {
                    $this->handleAttachments($complaint, [$files], $user);
                }

                // إذا كانت مجموعة ملفات
                if (is_array($files)) {
                    foreach ($files as $file) {
                        if ($file instanceof UploadedFile && $file->isValid()) {
                            $this->handleAttachments($complaint, [$file], $user);
                        }
                    }
                }
            }



            event(new ComplaintSubmitted($complaint));
            $this->log($user->id, 'complaint_submitted', [
                'ref' => $ref,
                'ip' => $request?->ip(),
                'user_agent' => $request?->userAgent()
            ]);

            return $complaint;
        });
    }
    protected function handleAttachments($complaint, array $files, $user): void
    {
        /** @var UploadedFile $file */
        foreach ($files as $file) {

            if (!$file->isValid()) {
                Log::warning('Invalid file upload detected.', [
                    'filename' => $file->getClientOriginalName()
                ]);
                continue;
            }

            // 📂 تحديد مجلد التخزين داخل storage/app/public/complaints/{ref}
            $storagePath = "complaints/{$complaint->reference_number}";

            // 🗂️ رفع الملف باستخدام Storage facade
            $path = Storage::disk('public')->putFile($storagePath, $file);

            // 📝 حفظ بيانات المرفق في قاعدة البيانات
            $complaint->attachments()->create([
                'file_path'     => $path, // المسار داخل التخزين
                'file_name'     => $file->getClientOriginalName(),
                'file_type'     => $file->extension(),
                'file_size'     => $file->getSize(),
                'mime_type'     => $file->getClientMimeType(),
            ]);
        }
    }
    // 2. عرض شكوى
    public function getByReference(string $ref)
    {
        return $this->repo->findByReference($ref);
    }
    // 3. قفل الشكوى
    public function lock(int $id, $user)
    {
        return DB::transaction(function () use ($id, $user) {
            $complaint = $this->repo->findById($id);
            if ($complaint->locked_by !== null) {
                if ($user->id === $complaint->locked_by) {
                    throw new \Exception('Complaint is already locked by you.');
                }
                throw new \Exception('Complaint is already locked by another user.');
            }
            $complaint->update([
                'locked_by' => $user->id,
                'locked_at' => now(),
            ]);
            $this->audit->logAction($user->id, 'complaint.locked', [
                'complaint_id' => $complaint->id,
                'locked_by' => $user->id,
            ]);

            ComplaintHistory::create([
                'complaint_id' => $complaint->id,
                'user_id'      => $user->id,
                'action'       => 'complaint_locked',
                'description'  => "Complaint locked by {$user->name} to start processing.",
            ]);

            return $complaint;
        });
    }
    // 4. إلغاء قفل الشكوى (إلغاء الحجز) ← جديد
    public function unlock(int $id, $user): void
    {
        DB::transaction(function () use ($id, $user) {

            $complaint = $this->repo->findById($id);
            $userRole = $user->getRoleNames()->first();

            // 1. التحقق من صلاحية الجهة الحكومية (مهم جداً)
            if ($userRole !== 'admin' && $complaint->entity_id !== $user->entity_id) {
                throw new \Exception('لا تملك صلاحية لفتح قفل شكاوى لا تتبع لجهتك الحكومية.', 403);
            }

            // 2. التحقق من أن الموظف الحالي هو من قام بالقفل أو أنه مشرف
            if ($userRole !== 'admin' && $complaint->locked_by !== $user->id) {
                $lockedByName = $complaint->lockedBy ? $complaint->lockedBy->name : 'موظف آخر';
                throw new \Exception("لا يمكن فتح القفل. الشكوى مقفولة بواسطة {$lockedByName}، ويجب أن يكون الموظف القافل هو من يفتحها.", 403);
            }

            // 3. تطبيق فتح القفل
            if ($complaint->locked_by === null) {
                // إذا لم تكن مقفولة، نخرج دون إثارة خطأ، أو يمكنك إرجاع رسالة للمستخدم
                return;
            }

            $complaint->update([
                'locked_by' => null,
                'locked_at' => null,
            ]);

            // تسجيل تدقيق فتح القفل (AOP)
            $this->audit->logSecurityEvent('complaint_unlocked', [
                'complaint_id' => $id,
                'user_id' => $user->id
            ]);
        });
    }
    // 5. تعيين موظف للشكوى ← جديد
    public function assign($complaint, int $employeeId, $assignerUser)
    {
        return DB::transaction(function () use ($complaint, $employeeId, $assignerUser) {
            $oldEmployeeId = $complaint->assigned_to;

            $complaint->update(['assigned_to' => $employeeId]);
            $this->audit->logAction($assignerUser->id, 'complaint.assigned', [
                'complaint_id' => $complaint->id,
                'old_employee_id' => $oldEmployeeId,
                'new_employee_id' => $employeeId,
            ]);

            ComplaintHistory::create([
                'complaint_id' => $complaint->id,
                'user_id'      => $assignerUser->id, // من قام بالتعيين
                'action'       => 'complaint_assigned',
                'description'  => "Complaint assigned to employee ID: {$employeeId} by Admin/Supervisor {$assignerUser->name}.",
                'old_data'     => ['assigned_to' => $oldEmployeeId],
                'new_data'     => ['assigned_to' => $employeeId],
            ]);

            event(new ComplaintAssigned($complaint, $employeeId));

            return $complaint;
        });
    }
    /**
     * تحديث حالة الشكوى (يستخدمه الموظف/المشرف)
     */
    public function updateStatus(int $id, string $newStatus, ?string $notes, $user)
    {
        return DB::transaction(function () use ($id, $newStatus, $notes, $user) {
            $complaint = $this->repo->findById($id);
            $oldStatus = $complaint->status;

            // 1. ⚠️ التحكم في التزامن (Concurrency Control)
            if (!$user->hasRole('admin') && $complaint->locked_by !== $user->id) {
                if ($complaint->locked_by === null) {
                    throw new \Exception('Complaint must be locked first before updating its status.');
                }
                throw new \Exception('Complaint is locked by another user and cannot be modified.');
            }

            // 2. تحديث بيانات الشكوى
            $complaint->update(['status' => $newStatus]);

            // 3. ✅ فك القفل التلقائي (Auto-Unlock)
            // إذا كانت الحالة النهائية هي 'done' أو 'rejected'
            if (in_array($newStatus, ['done', 'rejected'])) {
                $complaint->update([
                    'locked_by' => null,
                    'locked_at' => null,
                ]);
            }

            // 4. التسجيل في سجل التدقيق (Audit Log)
            $this->audit->logAction($user->id, 'complaint.status_updated', [
                'complaint_id' => $complaint->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ]);

            ComplaintHistory::create([
                'complaint_id' => $complaint->id,
                'user_id'      => $user->id,
                'action'       => 'status_update',
                'description'  => "Status changed from {$oldStatus} to {$newStatus}." . ($notes ? " Notes: {$notes}" : ''),
                'old_data'     => ['status' => $oldStatus],
                'new_data'     => ['status' => $newStatus, 'notes' => $notes],
            ]);

            event(new ComplaintStatusUpdated($complaint));
            if ($complaint->user && filter_var($complaint->user->email, FILTER_VALIDATE_EMAIL)) {
                try {
                    Mail::to($complaint->user->email)->queue(new ComplaintStatusMail($complaint, $newStatus));
                    Log::info("Status update email queued for complaint {$complaint->reference_number}");
                } catch (\Exception $e) {
                    Log::error("Failed to queue status update email for complaint {$id}: " . $e->getMessage());
                }
            }

            return $complaint;
        });
    }
    /**
     * خدمة تتبع الشكوى للمواطن
     */
    public function trackComplaint(string $ref, $user)
    {
        return $this->repo->getComplaintWithHistory($ref, $user->id);
    }

    // 7. Dashboard
    public function getDashboard($user)
    {
        return match ($user->role) {
            'citizen' => $this->repo->forCitizen($user->id),
            'employee' => $this->repo->forEmployee($user->id),
            'admin' => $this->repo->forAdmin(),
            default => collect()
        };
    }

    // 8. Audit Log (محدث)
    public function log($userId, $action, $details = null)
    {
        $this->audit->logAction($userId, $action, $details);
    }

    public function addNote(Complaint $complaint, $note)
    {
        return $this->repo->addNote($complaint, $note);
    }

    public function requestMoreInfo(Complaint $complaint, string $message)
    {
        $complaint->history()->create([
            'action' => 'request_more_info',
            'details' => $message,
            'user_id' => auth()->id(),
        ]);

        broadcast(new RequestMoreInfo($complaint, $message))->toOthers();

        return true;
    }
}
