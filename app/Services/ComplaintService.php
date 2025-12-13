<?php
// app/Services/ComplaintService.php

namespace App\Services;

use App\Repositories\ComplaintRepository;
use App\Events\ComplaintSubmitted;
use App\Events\ComplaintStatusUpdated;
use App\Events\ComplaintAssigned;
use App\Events\RequestMoreInfo;
use App\Mail\ComplaintStatusMail;
use App\Models\Complaint;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Events\CitizenResponded;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class ComplaintService
{
    protected ComplaintRepository $repo;
    protected AuditService $audit;

    public function __construct(ComplaintRepository $repo, AuditService $audit)
    {
        $this->repo = $repo;
        $this->audit = $audit;
    }

    public function submit(array $data, $user, ?Request $request = null)
    {
        return DB::transaction(function () use ($data, $user, $request) {

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

                if ($files instanceof UploadedFile) {
                    $this->handleAttachments($complaint, [$files], $user);
                }

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

    public function getByReference(string $ref)
    {
        return $this->repo->findByReference($ref);
    }

    public function getComplaintDetailsForEmployee(int $complaintId): Complaint
    {
        return $this->repo->getComplaintDetailsForEmployee($complaintId);
    }

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

            return $complaint;
        });
    }

    public function unlock(int $id, $user): void
    {
        DB::transaction(function () use ($id, $user) {

            $complaint = $this->repo->findById($id);
            $userRole = $user->getRoleNames()->first();

            if ($userRole !== 'admin' && $complaint->entity_id !== $user->entity_id) {
                throw new \Exception('لا تملك صلاحية لفتح قفل شكاوى لا تتبع لجهتك الحكومية.', 403);
            }

            if ($userRole !== 'admin' && $complaint->locked_by !== $user->id) {
                $lockedByName = $complaint->lockedBy ? $complaint->lockedBy->name : 'موظف آخر';
                throw new \Exception("لا يمكن فتح القفل. الشكوى مقفولة بواسطة {$lockedByName}، ويجب أن يكون الموظف القافل هو من يفتحها.", 403);
            }

            if ($complaint->locked_by === null) {
                return;
            }

            $complaint->update([
                'locked_by' => null,
                'locked_at' => null,
            ]);

            $this->audit->logSecurityEvent('complaint_unlocked', [
                'complaint_id' => $id,
                'user_id' => $user->id
            ]);
        });
    }

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

            event(new ComplaintAssigned($complaint, $employeeId));

            return $complaint;
        });
    }

    public function updateStatus(int $id, string $newStatus, ?string $notes, $user)
    {
        return DB::transaction(function () use ($id, $newStatus, $notes, $user) {
            $complaint = $this->repo->findById($id);
            $oldStatus = $complaint->status;

            if (!$user->hasRole('admin') && $complaint->locked_by !== $user->id) {
                if ($complaint->locked_by === null) {
                    throw new \Exception('Complaint must be locked first before updating its status.');
                }
                throw new \Exception('Complaint is locked by another user and cannot be modified.');
            }

            $complaint->update(['status' => $newStatus]);

            if (in_array($newStatus, ['done', 'rejected'])) {
                $complaint->update([
                    'locked_by' => null,
                    'locked_at' => null,
                ]);
            }

            $this->audit->logAction($user->id, 'complaint.status_updated', [
                'complaint_id' => $complaint->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
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

    public function trackComplaint(string $ref, User $user)
    {
        return Cache::remember("complaint_timeline_{$ref}_{$user->id}", now()->addMinutes(5), function () use ($ref, $user) {

            $complaint = $this->repo->getComplaintTimelineForCitizen($ref, $user->id);
            $timeline = collect();

            $timeline->push([
                'date'        => $complaint->created_at->translatedFormat('Y/m/d - h:i A'),
                'actor'       => 'أنت',
                'actor_type'  => 'citizen',
                'action'      => 'تقديم الشكوى',
                'description' => 'تم تقديم الشكوى بنجاح وإرسالها إلى الجهة المختصة.',
            ]);

            foreach ($complaint->audits as $audit) {
                $actor = User::find($audit->user_id)?->name ?? 'النظام';
                $actorType = ($audit->user_id === $complaint->user_id) ? 'أنت' : 'موظف';

                $description = $this->generateAuditDescriptionForTimeline($audit, $complaint);

                $timeline->push([
                    'date'        => $audit->created_at->translatedFormat('Y/m/d - h:i A'),
                    'actor'       => $actor,
                    'actor_type'  => $actorType,
                    'action'      => ucfirst($audit->event),
                    'description' => $description,
                ]);
            }

            $timeline = $timeline->values();

            return [
                'reference_number'       => $complaint->reference_number,
                'type'                   => $complaint->type,
                'status'                 => $complaint->status,
                'status_in_arabic'       => match ($complaint->status) {
                    'new'           => 'جديدة',
                    'processing'    => 'قيد المعالجة',
                    'under_review'  => 'قيد المراجعة - بانتظار ردك',
                    'done'          => 'منجزة',
                    'rejected'      => 'مرفوضة',
                    default         => 'غير معروف',
                },
                'entity_name'            => $complaint->entity->name,
                'location'               => $complaint->location ?? 'غير محدد',
                'description'            => $complaint->description,
                'submitted_at'           => $complaint->created_at->translatedFormat('l، j F Y - h:i A'),
                'is_being_processed'     => !is_null($complaint->locked_by),
                'awaiting_your_response' => $complaint->status === 'under_review',
                'latest_request_message' => $this->getLatestInfoRequestMessage($complaint),
                'attachments'            => $complaint->attachments->map(fn($a) => [
                    'name' => $a->file_name ?? 'ملف مرفق',
                    'url'  => Storage::url($a->file_path),
                ])->values(),
                'timeline'               => $timeline->all(),
            ];
        });
    }

    private function generateAuditDescriptionForTimeline($audit, $complaint)
    {
        return match ($audit->event) {
            'created'   => 'تم تقديم الشكوى',
            'updated'   => $this->describeUpdatedEvent($audit),
            default     => ucfirst($audit->event),
        };
    }

    private function describeUpdatedEvent($audit)
    {
        if (isset($audit->new_values['locked_by']) && $audit->new_values['locked_by'] !== null) {
            return 'تم قفل الشكوى لبدء المعالجة';
        } elseif (isset($audit->new_values['locked_by']) && $audit->new_values['locked_by'] === null) {
            return 'تم فك قفل الشكوى';
        } elseif (isset($audit->new_values['status'])) {
            $old = $audit->old_values['status'] ?? 'غير معروف';
            $new = $audit->new_values['status'] ?? 'غير معروف';
            $map = [
                'new' => 'جديدة',
                'processing' => 'قيد المعالجة',
                'under_review' => 'قيد المراجعة',
                'done' => 'منجزة',
                'rejected' => 'مرفوضة'
            ];
            return "تم تغيير الحالة من " . ($map[$old] ?? $old) . " إلى " . ($map[$new] ?? $new);
        } elseif (isset($audit->new_values['assigned_to'])) {
            $name = User::find($audit->new_values['assigned_to'] ?? null)?->name ?? 'موظف';
            return "تم تعيين الشكوى لـ {$name}";
        }
        return 'تم تحديث الشكوى';
    }

    public function getDashboard($user)
    {
        return match ($user->role) {
            'citizen' => $this->repo->forCitizen($user->id),
            'employee' => $this->repo->forEmployee($user->id),
            'admin' => $this->repo->forAdmin(),
            default => collect()
        };
    }

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
        return DB::transaction(function () use ($complaint, $message) {
            $oldStatus = $complaint->status;
            $newStatus = 'under_review';

            $complaint->update(['status' => $newStatus]);

            $complaint->audit('request_more_info', [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'message' => $message,
            ]);

            $this->audit->logAction(auth()->id(), 'complaint.info_requested', [
                'complaint_id' => $complaint->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ]);

            broadcast(new RequestMoreInfo($complaint, $message))->toOthers();

            return true;
        });
    }

    public function citizenRespondToInfoRequest(Complaint $complaint, ?Request $request = null)
    {
        return DB::transaction(function () use ($complaint, $request) {
            $user = auth()->user();

            if ($complaint->user_id !== $user->id) {
                throw new \Exception('لا تملك صلاحية للرد على هذه الشكوى.', 403);
            }
            if ($complaint->status !== 'under_review') {
                throw new \Exception('لا يمكن الرد على شكوى حالتها ليست "قيد المراجعة".', 400);
            }

            if ($request && $request->hasFile('files') && $complaint->attachments()->count() >= 10) {
                throw new \Exception('تم تجاوز الحد الأقصى لعدد المرفقات (10).', 400);
            }

            if ($request && $request->hasFile('files')) {
                $files = is_array($request->file('files')) ? $request->file('files') : [$request->file('files')];
                $this->handleAttachments($complaint, $files, $user);
            }

            $oldStatus = $complaint->status;
            $newStatus = 'processing';
            $notes = $request?->input('notes');
            $this->repo->updateComplaintStatus($complaint, $newStatus);

            $description = "المواطن قام بالرد على طلب معلومات إضافية." . ($notes ? " الملاحظة: " . $notes : '');

            $complaint->audit('citizen_responded', [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'notes' => $notes,
                'attachments_added' => $request?->hasFile('files') ? count($request->file('files')) : 0
            ]);

            $this->audit->logAction($user->id, 'citizen.info_responded', [
                'complaint_id' => $complaint->id,
                'ip' => $request?->ip(),
                'attachments_count' => $request?->hasFile('files') ? count($request->file('files')) : 0
            ]);

            event(new CitizenResponded($complaint));

            return $complaint;
        });
    }

    public function getLatestInfoRequestMessage(Complaint $complaint): ?string
    {
        $audit = $this->repo->getLatestInfoRequest($complaint->id);
        return $audit ? $audit->new_values['message'] ?? null : null; // استخراج الـ message من new_values
    }

    public function getEmployeeNewComplaints(User $user)
    {
        return $this->repo->getNewForEmployee($user->id, $user->entity_id);
    }

    public function getAllComplaints(array $filters = [])
    {
        return $this->repo->getAllWithFilters($filters);
    }

    public function getEntitiesForDropdown()
    {
        return $this->repo->getEntitiesForDropdown();
    }

    public function getMyAssignedOrLockedComplaints(User $user)
    {
        if (!$user->hasRole('employee') && !$user->hasRole('admin')) {
            throw new \Exception('غير مصرح لك بعرض هذه الشكاوى.', 403);
        }
        return $this->repo->getMyAssignedOrLockedComplaints($user->id);
    }
}
