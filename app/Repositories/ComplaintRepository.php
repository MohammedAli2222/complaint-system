<?php
// app/Repositories/ComplaintRepository.php

namespace App\Repositories;

use App\Models\Complaint;
use App\Models\complaint_note;
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
}




