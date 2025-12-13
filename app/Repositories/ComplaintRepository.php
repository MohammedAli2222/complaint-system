<?php

namespace App\Repositories;

use App\Models\Complaint;
use App\Models\complaint_note;
use App\Models\Entity;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ComplaintRepository
{
    public function create(array $data)
    {
        return Complaint::create($data);
    }

    public function findByReference(string $ref)
    {
        return Complaint::where('reference_number', $ref)->first();
    }

    public function getComplaintDetailsForEmployee(int $complaintId): Complaint
    {
        return Complaint::with([
            'user:id,name,email',
            'entity:id,name',
            'assignedTo:id,name',
            'lockedBy:id,name',
            'audits', // تغيير إلى audits
            'attachments'
        ])
            ->findOrFail($complaintId);
    }

    public function findById(int $id)
    {
        return Complaint::findOrFail($id);
    }

    public function forCitizen(int $userId)
    {
        return Complaint::where('user_id', $userId)
            ->with(['entity:id,name', 'attachments:id,type'])
            ->select('id', 'reference_number', 'type', 'status', 'created_at', 'updated_at')
            ->latest()
            ->get();
    }

    public function forEmployee(int $userId)
    {
        return Complaint::where('assigned_to', $userId)
            ->orWhereHas('entity', fn($q) => $q->where('employee_id', $userId))
            ->with(['user:id,name', 'entity:id,name'])
            ->select('id', 'reference_number', 'type', 'status', 'created_at')
            ->latest()
            ->get();
    }

    public function forAdmin()
    {
        return Complaint::with([
            'user:id,name,email',
            'entity:id,name',
            'assignedTo:id,name',
            'lockedBy:id,name',
            'attachments:id,type',
            'audits' // تغيير إلى audits
        ])
            ->select('id', 'reference_number', 'type', 'status', 'created_at', 'entity_id', 'assigned_to', 'locked_by')
            ->latest()
            ->get();
    }

    public function generateUniqueReference(int $length = 10): string
    {
        $length = min($length, 32);

        do {
            $uuid = Str::uuid()->toString();

            $uniquePart = str_replace('-', '', $uuid);

            $referencePart = substr($uniquePart, 0, $length);

            $reference = 'REF-' . strtoupper($referencePart);
        } while (Complaint::where('reference_number', $reference)->exists());

        return $reference;
    }

    public function getComplaintTimelineForCitizen(string $referenceNumber, int $userId)
    {
        $complaint = Complaint::where('reference_number', $referenceNumber)
            ->where('user_id', $userId)
            ->with([
                'entity:id,name',
                'attachments:id,complaint_id,file_path,file_name,file_type,file_size,created_at',
                'audits' => function ($query) {
                    $query->select(
                        'id',
                        'auditable_id',
                        'event',
                        'old_values',
                        'new_values',
                        'created_at',
                        'user_id'
                    )
                        ->orderBy('created_at', 'asc');
                }
            ])
            ->select([
                'id',
                'reference_number',
                'type',
                'status',
                'entity_id',
                'location',
                'description',
                'created_at',
                'updated_at',
                'locked_by',
                'locked_at'
            ])
            ->first();

        if (!$complaint) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('الشكوى غير موجودة أو لا تملك صلاحية رؤيتها');
        }

        return $complaint;
    }

    public function addNote(Complaint $complaint, $note)
    {
        return complaint_note::create([
            'complaint_id' => $complaint->id,
            'user_id' => auth()->id(),
            'note' => $note,
        ]);
    }

    public function handleAttachments(Complaint $complaint, array $files, User $user)
    {
        foreach ($files as $file) {
            if (!$file->isValid()) {
                Log::warning('Invalid file upload detected.', ['filename' => $file->getClientOriginalName()]);
                continue;
            }

            $allowedMimes = ['image/jpeg', 'image/png', 'application/pdf'];
            if (!in_array($file->getClientMimeType(), $allowedMimes)) {
                throw new \Exception('نوع الملف غير مدعوم.', 400);
            }

            $storagePath = "complaints/{$complaint->reference_number}";
            $path = Storage::disk('public')->putFile($storagePath, $file);

            $this->createAttachment($complaint, [
                'file_path'     => $path,
                'file_name'     => $file->getClientOriginalName(),
                'file_type'     => $file->extension(),
                'file_size'     => $file->getSize(),
                'mime_type'     => $file->getClientMimeType(),
            ]);
        }
    }

    public function createAttachment(Complaint $complaint, array $data)
    {
        return $complaint->attachments()->create($data);
    }

    public function updateComplaintStatus(Complaint $complaint, string $newStatus): Complaint
    {
        $complaint->status = $newStatus;
        $complaint->save();
        return $complaint;
    }

    public function getLatestInfoRequest(int $complaintId): ?object
    {
        return \OwenIt\Auditing\Models\Audit::where('auditable_id', $complaintId)
            ->where('auditable_type', 'App\\Models\\Complaint')
            ->where('event', 'request_more_info')
            ->latest()
            ->first();
    }

    public function getNewForEmployee(int $userId, ?int $entityId)
    {
        return Complaint::where('status', 'new')
            ->where(function ($query) use ($userId, $entityId) {
                $query->where('assigned_to', $userId)
                    ->orWhere('locked_by', $userId)
                    ->orWhereHas('entity', fn($q) => $q->where('id', $entityId));
            })
            ->with([
                'user:id,name',
                'entity:id,name'
            ])
            ->select(
                'id',
                'reference_number',
                'type',
                'status',
                'created_at',
                'user_id',
                'entity_id',
                'location',
                'description',
                'updated_at',
                'locked_by',
                'locked_at',
                'assigned_to'
            )
            ->latest()
            ->paginate(20);
    }

    public function getAllWithFilters(array $filters = [])
    {
        $query = Complaint::with([
            'user:id,name,email',
            'entity:id,name',
            'assignedTo:id,name',
            'lockedBy:id,name',
            'attachments:id,complaint_id,file_path,file_name,file_type,file_size,mime_type,created_at',
            'audits:id,auditable_id,event,old_values,new_values,created_at,user_id'  // تغيير إلى audits
        ])
            ->select(
                'id',
                'reference_number',
                'type',
                'status',
                'created_at',
                'entity_id',
                'assigned_to',
                'locked_by',
                'user_id',
                'location',
                'description',
                'updated_at'
            )
            ->latest();

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }
        if (isset($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        return $query->paginate(20);
    }

    public function getEntitiesForDropdown()
    {
        return Entity::select('id', 'name')
            ->orderBy('name')
            ->get();
    }

    public function getMyAssignedOrLockedComplaints(int $userId)
    {
        return Complaint::where('assigned_to', $userId)
            ->orWhere('locked_by', $userId)
            ->with([
                'user:id,name,email',
                'entity:id,name',
                'assignedTo:id,name',
                'lockedBy:id,name',
                'attachments:id,file_name,file_path,file_type,file_size,mime_type'
            ])
            ->select(
                'id',
                'reference_number',
                'type',
                'status',
                'created_at',
                'updated_at',
                'entity_id',
                'assigned_to',
                'locked_by',
                'location',
                'description'
            )
            ->latest()
            ->paginate(20);
    }
}
