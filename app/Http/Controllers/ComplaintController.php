<?php
// app/Http/Controllers/ComplaintController.php

namespace App\Http\Controllers;

use App\Http\Resources\ComplaintResource;
use App\Models\Complaint;
use App\Services\ComplaintService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class ComplaintController extends Controller
{
    protected ComplaintService $service;

    public function __construct(ComplaintService $service)
    {
        $this->service = $service;
    }

    // تقديم شكوى
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'entity_id' => 'required|exists:entities,id',
            'type' => 'required|string|max:255',
            'location' => 'required|string',
            'description' => 'required|string',
            'files' => 'sometimes|array',
            'files.*' => 'file|mimes:jpg,jpeg,png,pdf|max:5048'
        ]);

        $complaint = $this->service->submit(
            $request->all(),
            $request->user(),
            $request
        );

        return response()->json([
            'status' => true,
            'message' => 'Complaint submitted successfully. Your reference number is: ' . $complaint->reference_number,
            'reference_number' => $complaint->reference_number,
            'attachments' => $complaint->attachments->map(fn($att) => [
                'name' => $att->file_name,
                'url' => Storage::url($att->file_path)
            ])
        ], 201);
    }

    // عرض شكوى
    public function show(string $ref, Request $request)
    {
        $complaint = Complaint::where('reference_number', $ref)
            ->with('history', 'attachments')
            ->firstOrFail();

        $this->authorize('view', $complaint);

        return response()->json([
            'status' => true,
            'message' => 'Complaint details retrieved successfully.',
            'data' => new ComplaintResource($complaint)
        ]);
    }

    // قفل الشكوى
    public function lock($id, Request $request)
    {
        $complaint = Complaint::findOrFail($id);
        $this->authorize('lock', $complaint);

        try {
            $this->service->lock($id, $request->user());

            return response()->json([
                'status' => true,
                'message' => 'تم قفل الشكوى بنجاح. يمكنك الآن البدء بمعالجتها.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 409);
        }
    }

    // فتح قفل الشكوى
    public function unlock($id, Request $request): JsonResponse
    {
        $complaint = Complaint::findOrFail($id);
        $this->authorize('unlock', $complaint);

        try {
            $this->service->unlock($id, $request->user());
            return response()->json([
                'status' => true,
                'message' => 'تم فتح قفل الشكوى بنجاح.'
            ]);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() === 403 ? 403 : 409;
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], $statusCode);
        }
    }

    // تتبع الشكوى
    public function track($ref, Request $request)
    {
        $complaint = $this->service->trackComplaint($ref, $request->user());

        if (!$complaint) {
            return response()->json([
                'status' => false,
                'message' => 'الشكوى غير موجودة أو لا تملك صلاحية رؤيتها'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $complaint
        ]);
    }

    // تحديث حالة الشكوى
    public function updateStatus($id, Request $request): JsonResponse
    {
        $complaint = Complaint::findOrFail($id);
        $this->authorize('update', $complaint);

        $request->validate([
            'status' => 'required|string|in:new,processing,rejected,done',
            'notes'  => 'nullable|string'
        ]);

        $this->service->updateStatus(
            $id,
            $request->status,
            $request->notes,
            $request->user()
        );

        return response()->json([
            'status' => true,
            'message' => 'تم تحديث الحالة وإرسال إشعار بريدي للمواطن.'
        ]);
    }

    // تعيين موظف للشكوى
    public function assign($id, Request $request)
    {
        $complaint = Complaint::findOrFail($id);
        $this->authorize('assign', $complaint);

        $request->validate([
            'employee_id' => [
                'required',
                'exists:users,id',
                Rule::exists('model_has_roles', 'model_id')->where(function ($query) {
                    return $query->where(
                        'role_id',
                        Role::where('name', 'employee')->value('id')
                    );
                }),
            ],
        ]);

        $this->service->assign(
            $complaint,
            $request->employee_id,
            $request->user()
        );

        return response()->json([
            'status' => true,
            'message' => 'تم تعيين الشكوى بنجاح وإرسال إشعار للموظف.'
        ]);
    }

    // إضافة ملاحظة للشكوى
    public function addNote($id, Request $request)
    {
        $complaint = Complaint::findOrFail($id);
        $this->authorize('addNote', $complaint);

        $request->validate([
            'note' => 'required|string|max:2000',
        ]);

        $this->service->addNote($complaint, $request->note);

        return response()->json([
            'message' => 'تمت إضافة الملاحظة بنجاح'
        ]);
    }

    // طلب معلومات إضافية من المواطن
    public function requestMoreInfo(Request $request, $id)
    {
        $complaint = Complaint::findOrFail($id);
        $this->authorize('requestMoreInfo', $complaint);

        $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        $this->service->requestMoreInfo($complaint, $request->message);

        return response()->json([
            'status' => true,
            'message' => 'تم إرسال طلب معلومات إضافية للمواطن'
        ]);
    }

    // Dashboard - عرض الشكاوى حسب الصلاحية
    public function dashboard(Request $request)
    {
        $user = $request->user();

        // التحقق من الصلاحية لعرض الشكاوى
        if (!$user->can('complaints.view-any') && !$user->can('view_assigned_complaints')) {
            abort(403, 'Unauthorized action.');
        }

        $data = $this->service->getDashboard($user);

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    public function respondToInfoRequest($id, Request $request): JsonResponse
    {
        $request->validate([
            'notes' => 'nullable|string|max:2000',
            'files' => 'sometimes|array|max:5',
            'files.*' => 'file|mimes:jpg,jpeg,png,pdf|max:5048'
        ]);

        $complaint = Complaint::findOrFail($id);

        $this->authorize('respondToInfoRequest', $complaint);

        try {
            $this->service->citizenRespondToInfoRequest($complaint, $request);

            return response()->json([
                'status' => true,
                'message' => 'تم إرسال المعلومات الإضافية والمرفقات بنجاح. حالة الشكوى تغيرت إلى قيد المعالجة (processing).'
            ], 200);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], $statusCode);
        }
    }

    public function getInfoRequestMessage($id): JsonResponse
    {
        $complaint = Complaint::findOrFail($id);
        $this->authorize('view', $complaint);
        $message = $this->service->getLatestInfoRequestMessage($complaint);

        return response()->json([
            'status' => true,
            'message' => $message ?? 'لا يوجد طلب معلومات إضافية حاليًا.'
        ]);
    }

    public function getAllComplaints(Request $request): JsonResponse
    {
        $this->authorize('viewAllComplaints');


        $filters = $request->validate([
            'status' => 'nullable|string|in:new,processing,under_review,done,rejected',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $complaints = $this->service->getAllComplaints($filters);

        return response()->json([
            'status' => true,
            'message' => 'جميع الشكاوى تم جلبها بنجاح.',
            'data' => ComplaintResource::collection($complaints)
        ]);
    }

    public function getEmployeeNewComplaints(Request $request): JsonResponse
    {
        $this->authorize('viewNewComplaints');

        $user = $request->user();
        $complaints = $this->service->getEmployeeNewComplaints($user);

        return response()->json([
            'status' => true,
            'message' => 'الشكاوى الجديدة الخاصة بك تم جلبها بنجاح.',
            'data' => ComplaintResource::collection($complaints)
        ]);
    }
}
