<?php

namespace App\Services;

use App\Events\ComplaintAssigned;
use App\Events\ComplaintStatusUpdated;
use App\Repositories\ComplaintRepository;
use App\Traits\HasComplaintTimeline;
use App\Traits\HasComplaintLocking;
use App\Traits\HasComplaintInfoRequest;
use App\Traits\HasComplaintSubmission;
use App\Traits\TranslatesComplaintStatus;
use App\Models\Complaint;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ComplaintService
{
    use HasComplaintTimeline;
    use HasComplaintLocking;
    use HasComplaintInfoRequest;
    use HasComplaintSubmission;
    use TranslatesComplaintStatus;

    protected ComplaintRepository $repo;
    protected AuditService $audit;

    public function __construct(ComplaintRepository $repo, AuditService $audit)
    {
        $this->repo = $repo;
        $this->audit = $audit;
    }

    // ============ واجهات للـ Controller ============

    public function trackComplaint(string $ref, User $user)
    {
        $cacheKey = "complaint_timeline_{$ref}_{$user->id}";

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($ref, $user) {
            $complaint = $this->repo->getComplaintTimelineForCitizen($ref, $user->id);

            $complaint->loadMissing([
                'entity:id,name',
                'attachments:id,file_name,file_path',
                'audits' => fn($q) => $q->orderBy('created_at', 'asc')
            ]);

            $timeline = $this->buildTimeline($complaint, $user); // ← استدعاء الدالة الصحيحة من الـ Trait

            return [
                'reference_number'       => $complaint->reference_number,
                'type'                   => $complaint->type,
                'status'                 => $complaint->status,
                'status_in_arabic'       => $this->translateStatus($complaint->status),
                'entity_name'            => $complaint->entity?->name ?? 'غير محدد',
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
                'timeline'               => $timeline->values()->all(),
            ];
        });
    }

    public function lock(int $id, User $user)
    {
        $complaint = $this->repo->findById($id);
        $this->performLock($complaint, $user); // اسم جديد من الـ Trait
        return $complaint;
    }

    public function unlock(int $id, User $user)
    {
        $complaint = $this->repo->findById($id);
        $this->performUnlock($complaint, $user);
        return $complaint;
    }
    public function updateStatus(int $id, string $newStatus, ?string $notes, User $user)
    {
        $complaint = $this->repo->findById($id);
        // هنا تحتاج دالة updateStatus في Trait أو Repository
        // سنضيفها مؤقتًا هنا حتى ننشئ Trait منفصل
        DB::transaction(function () use ($complaint, $newStatus, $notes, $user) {
            $oldStatus = $complaint->status;

            $complaint->update(['status' => $newStatus]);

            if (in_array($newStatus, ['done', 'rejected'])) {
                $complaint->update(['locked_by' => null, 'locked_at' => null]);
            }

            $this->audit->logAction($user->id, 'complaint.status_updated', [
                'complaint_id' => $complaint->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ]);

            event(new ComplaintStatusUpdated($complaint));
        });

        return $complaint;
    }

    public function assign(Complaint $complaint, int $employeeId, User $assigner)
    {
        // مؤقتًا حتى ننشئ Trait
        DB::transaction(function () use ($complaint, $employeeId, $assigner) {
            $old = $complaint->assigned_to;
            $complaint->update(['assigned_to' => $employeeId]);

            $this->audit->logAction($assigner->id, 'complaint.assigned', [
                'complaint_id' => $complaint->id,
                'old_assigned' => $old,
                'new_assigned' => $employeeId,
            ]);

            event(new ComplaintAssigned($complaint, $employeeId));
        });

        return $complaint;
    }

    public function addNote(Complaint $complaint, string $note, User $user)
    {
        $note = $this->repo->addNote($complaint, $note);

        $this->audit->logAction($user->id, 'complaint.note_added', [
            'complaint_id' => $complaint->id,
            'note' => $note->note,
        ]);

        return $note;
    }

    public function requestMoreInfo(int $id, Request $request)
    {
        $complaint = $this->repo->findById($id);
        $message = $request->input('message');
        $user = $request->user();

        $this->performRequestMoreInfo($complaint, $message, $user);

        return $complaint;
    }

    public function citizenRespondToInfoRequest(int $id, Request $request)
    {
        $complaint = $this->repo->findById($id);
        $user = $request->user();

        return $this->performCitizenRespondToInfoRequest($complaint, $request, $user);
    }

    public function getDashboard(User $user)
    {
        return match ($user->role ?? $user->getRoleNames()->first()) {
            'citizen' => $this->repo->forCitizen($user->id),
            'employee' => $this->repo->forEmployee($user->id),
            'admin' => $this->repo->forAdmin(),
            default => collect(),
        };
    }

    // ============ دوال أساسية لا تحتاج Trait ============

    public function getByReference(string $ref): ?Complaint
    {
        return $this->repo->findByReference($ref);
    }

    public function getComplaintDetailsForEmployee(int $id): Complaint
    {
        return $this->repo->getComplaintDetailsForEmployee($id);
    }

    public function getAllComplaints(array $filters = [])
    {
        return $this->repo->getAllWithFilters($filters);
    }

    public function getEmployeeNewComplaints(User $user)
    {
        return $this->repo->getNewForEmployee($user->id, $user->entity_id);
    }

    public function getEntitiesForDropdown()
    {
        return $this->repo->getEntitiesForDropdown();
    }

    public function getMyAssignedOrLockedComplaints(User $user)
    {
        return $this->repo->getMyAssignedOrLockedComplaints($user->id);
    }

    public function getLatestInfoRequestMessage(Complaint $complaint): ?string
    {
        $audit = $complaint->audits()
            ->where('event', 'request_more_info')
            ->latest()
            ->first();

        return $audit?->new_values['message'] ?? null;
    }
}
