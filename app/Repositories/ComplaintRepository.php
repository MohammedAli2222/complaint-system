<?php

namespace App\Repositories;

use App\Models\Complaint;
use App\Models\complaint_note;
use App\Models\ComplaintHistory;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ComplaintRepository
{
    // إنشاء شكوى
    public function create(array $data)
    {
        return Complaint::create($data);
    }

    // البحث برقم المرجع
    public function findByReference(string $ref)
    {
        return Complaint::where('reference_number', $ref)->first();
    }

    // البحث بالـ ID
    public function findById(int $id)
    {
        return Complaint::findOrFail($id);
    }

    // للمواطن: شكاويه فقط
    public function forCitizen(int $userId)
    {
        return Complaint::where('user_id', $userId)
            ->with(['entity:id,name', 'attachments:id,type'])
            ->select('id', 'reference_number', 'type', 'status', 'created_at', 'updated_at')
            ->latest()
            ->get();
    }

    // للموظف: المعينة له أو جهته
    public function forEmployee(int $userId)
    {
        return Complaint::where('assigned_to', $userId)
            ->orWhereHas('entity', fn($q) => $q->where('employee_id', $userId))
            ->with(['user:id,name', 'entity:id,name'])
            ->select('id', 'reference_number', 'type', 'status', 'created_at')
            ->latest()
            ->get();
    }

    // للأدمن: كل الشكاوى مع العلاقات
    public function forAdmin()
    {
        return Complaint::with([
            'user:id,name,email',
            'entity:id,name',
            'assignedTo:id,name',
            'lockedBy:id,name',
            'attachments:id,type',
            'history:id,old_status,new_status,changed_by,notes,created_at'
        ])
            ->select('id', 'reference_number', 'type', 'status', 'created_at', 'entity_id', 'assigned_to', 'locked_by')
            ->latest()
            ->get();
    }

    // توليد رقم مرجعي فريد
    public function generateUniqueReference(int $length = 10): string
    {
        $length = min($length, 32); // حماية من تجاوز الطول

        do {
            // 1. UUID v4
            $uuid = Str::uuid()->toString();

            // 2. إزالة الشرطات
            $uniquePart = str_replace('-', '', $uuid);

            // 3. تقصير السلسلة
            $referencePart = substr($uniquePart, 0, $length);

            // 4. إضافة البادئة
            $reference = 'REF-' . strtoupper($referencePart);
        } while (Complaint::where('reference_number', $reference)->exists());

        return $reference;
    }
    /**
     * جلب تفاصيل الشكوى مع السجل الزمني (Timeline) للتتبع
     */
    public function getComplaintWithHistory(string $referenceNumber, int $userId)
    {
        return Complaint::where('reference_number', $referenceNumber)
            ->where('user_id', $userId) // التأكد من أن الشكوى تخص المواطن
            ->with([
                'entity:id,name',
                'attachments:id,complaint_id,file_type,file_path',

                'history' => function ($query) {
                    $query->select('complaint_id', 'action', 'description', 'created_at', 'old_data', 'new_data', 'user_id')
                        ->with('user:id,name') // جلب اسم الموظف الذي قام بالتغيير
                        ->latest();
                }
            ])
            ->first();
    }

    public function addNote(Complaint $complaint, $note)
    {
        return complaint_note::create([
            'complaint_id' => $complaint->id,
            'user_id' => auth()->id(),
            'note' => $note,
        ]);
    }

    public function requestMoreInfo(Complaint $complaint, $message)
    {
        // تخزن الطلب في جدول ملاحظات كنوع "طلب معلومات"
        return complaint_note::create([
            'complaint_id' => $complaint->id,
            'user_id' => auth()->id(),
            'note' => "طلب معلومات إضافية: " . $message,

        ]);
    }

    // نقل handleAttachments هنا للـ Data Abstraction
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

    // updateComplaintStatus و logComplaintHistory موجودة، لكن أضف JSON encoding إذا لزم للـ old/new_data
    public function logComplaintHistory(int $complaintId, int $userId, string $action, string $description, array $oldData = [], array $newData = [])
    {
        return ComplaintHistory::create([
            'complaint_id' => $complaintId,
            'user_id'      => $userId,
            'action'       => $action,
            'description'  => $description,
            'old_data'     => json_encode($oldData), // لتوسع أفضل
            'new_data'     => json_encode($newData),
        ]);
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

    public function getLatestInfoRequest(int $complaintId): ?ComplaintHistory
    {
        return ComplaintHistory::where('complaint_id', $complaintId)
            ->where('action', 'request_more_info')
            ->latest()
            ->first();
    }

    public function getNewForEmployee(int $userId, ?int $entityId)
    {
        return Complaint::where('status', 'new') // فقط الجديدة
            ->where(function ($query) use ($userId, $entityId) {
                $query->where('assigned_to', $userId) // أسندت إليه
                    ->orWhere('locked_by', $userId) // قفلها
                    ->orWhereHas('entity', fn($q) => $q->where('id', $entityId)); // خاصة بقسمه
            })
            ->with([
                'user:id,name', // لجلب اسم المستخدم إذا لزم
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
                'updated_at', // ← التعديل هنا: أضف هذه الحقول
                'locked_by',
                'locked_at',
                'assigned_to' // لـ lock_details و assigned_to
            )
            ->latest()
            ->paginate(20); // pagination للأداء
    }

    public function getAllWithFilters(array $filters = [])
    {
        $query = Complaint::with([
            'user:id,name,email',
            'entity:id,name',
            'assignedTo:id,name',
            'lockedBy:id,name',
            'attachments:id,complaint_id,file_path,file_name,file_type,file_size,mime_type,created_at',  // أضف جميع الحقول من migration لعرض كامل
            'history:id,complaint_id,user_id,action,description,old_data,new_data,created_at'  // نفسه
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

        // تطبيق فلاتر
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
}
