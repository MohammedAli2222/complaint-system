<?php

namespace App\Http\Controllers;

use App\Http\Resources\ComplaintResource;
use App\Models\Complaint;
use App\Services\ComplaintService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

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
        $complaint = $this->service->getByReference($ref);

        if (!$complaint) {
            return response()->json([
                'status'  => false,
                'message' => 'الشكوى غير موجودة أو الرقم المرجعي غير صحيح.'
            ], 404);
        }

        $this->authorize('view', $complaint);

        $complaint = $this->service->getComplaintDetailsForEmployee($complaint->id);

        return response()->json([
            'status'  => true,
            'message' => 'تم جلب تفاصيل الشكوى بنجاح.',
            'data'    => new ComplaintResource($complaint)
        ]);
    }

    // قفل الشكوى
    public function lock($id, Request $request)
    {
        $user = $request->user();

        $complaint = $this->service->lock($id, $user);

        return response()->json([
            'status' => true,
            'message' => 'Complaint locked successfully.',
            'data' => new ComplaintResource($complaint)
        ]);
    }

    // فك قفل الشكوى
    public function unlock($id, Request $request)
    {
        $user = $request->user();

        $this->service->unlock($id, $user);

        $complaint = Complaint::findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Complaint unlocked successfully.',
            'data' => new ComplaintResource($complaint)
        ]);
    }

    // تحديث حالة الشكوى
    public function updateStatus($id, Request $request)
    {
        $request->validate([
            'status' => ['required', Rule::in(['processing', 'done', 'rejected', 'under_review'])],
            'notes' => 'nullable|string|max:1000'
        ]);

        $user = $request->user();
        $newStatus = $request->input('status');
        $notes = $request->input('notes');

        $complaint = $this->service->updateStatus($id, $newStatus, $notes, $user);

        return response()->json([
            'status' => true,
            'message' => 'تم تحديث حالة الشكوى بنجاح.',
            'data' => new ComplaintResource($complaint)
        ]);
    }

    // تعيين الشكوى لموظف
    public function assign($id, Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:users,id'
        ]);

        $complaint = Complaint::findOrFail($id);
        $this->authorize('assign', $complaint);

        $employeeId = $request->input('employee_id');
        $assigner = $request->user();

        $complaint = $this->service->assign($complaint, $employeeId, $assigner);

        return response()->json([
            'status' => true,
            'message' => 'تم تعيين الشكوى للموظف بنجاح.',
            'data' => new ComplaintResource($complaint)
        ]);
    }

    // إضافة ملاحظة داخلية
    public function addNote($id, Request $request)
    {
        $request->validate([
            'note' => 'required|string|max:1000'
        ]);

        $complaint = Complaint::findOrFail($id);
        $this->authorize('addNote', $complaint);

        $note = $request->input('note');
        $user = $request->user();

        $this->service->addNote($complaint, $note, $user);

        return response()->json([
            'status' => true,
            'message' => 'تم إضافة الملاحظة بنجاح.'
        ]);
    }

    // طلب معلومات إضافية
    public function requestMoreInfo($id, Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:1000'
        ]);

        $complaint = Complaint::findOrFail($id);
        $this->authorize('requestMoreInfo', $complaint);

        $this->service->requestMoreInfo($id, $request); // نمرر $id و $request

        return response()->json([
            'status' => true,
            'message' => 'تم طلب معلومات إضافية بنجاح. حالة الشكوى تغيرت إلى قيد المراجعة (under_review).'
        ]);
    }

    // رد المواطن على طلب معلومات إضافية
    public function respondToInfoRequest($id, Request $request)
    {
        $request->validate([
            'notes' => 'nullable|string|max:1000',
            'files' => 'sometimes|array',
            'files.*' => 'file|mimes:jpg,jpeg,png,pdf|max:5048'
        ]);

        $complaint = Complaint::findOrFail($id);
        $this->authorize('respondToInfoRequest', $complaint); 
        $this->service->citizenRespondToInfoRequest($id, $request);

        return response()->json([
            'status' => true,
            'message' => 'تم إرسال المعلومات الإضافية والمرفقات بنجاح. حالة الشكوى تغيرت إلى قيد المعالجة (processing).'
        ]);
    }

    // جلب رسالة طلب المعلومات الأخيرة
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

    // لوحة التحكم
    public function dashboard(Request $request)
    {
        $user = $request->user();

        $data = $this->service->getDashboard($user);

        return response()->json([
            'status' => true,
            'message' => 'تم جلب بيانات لوحة التحكم بنجاح.',
            'data' => ComplaintResource::collection($data)
        ]);
    }

    // جميع الشكاوى
    public function getAllComplaints(Request $request): JsonResponse
    {
        $this->authorize('viewAllComplaints');

        $filters = $request->only(['status', 'start_date', 'end_date']);

        $complaints = $this->service->getAllComplaints($filters);

        return response()->json([
            'status' => true,
            'message' => 'جميع الشكاوى تم جلبها بنجاح.',
            'data' => ComplaintResource::collection($complaints)
        ]);
    }

    // شكاوى جديدة للموظف
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

    // قائمة الجهات الحكومية
    public function getEntities()
    {
        $entities = $this->service->getEntitiesForDropdown();

        return response()->json([
            'status' => true,
            'message' => 'Entities retrieved successfully for dropdown.',
            'data' => $entities,
        ]);
    }

    // الشكاوى المسندة أو المقفلة للموظف
    public function myAssignedOrLockedComplaints(Request $request): JsonResponse
    {
        $user = $request->user();

        $this->authorize('viewMyComplaints', Complaint::class);

        $complaints = $this->service->getMyAssignedOrLockedComplaints($user);

        return response()->json([
            'status' => true,
            'message' => 'تم جلب الشكاوى المسندة إليك أو المقفلة من قبلك بنجاح.',
            'data' => ComplaintResource::collection($complaints),
            'pagination' => [
                'current_page' => $complaints->currentPage(),
                'last_page'    => $complaints->lastPage(),
                'per_page'     => $complaints->perPage(),
                'total'        => $complaints->total(),
            ]
        ]);
    }

    /**
     * تتبع حالة الشكوى (Timeline) للمواطن
     */
    public function track(string $ref, Request $request)
    {
        $user = $request->user();

        $complaint = $this->service->getByReference($ref);

        if (!$complaint) {
            return response()->json([
                'status'  => false,
                'message' => 'الشكوى غير موجودة أو الرقم المرجعي غير صحيح.'
            ], 404);
        }

        if ($complaint->user_id !== $user->id) {
            return response()->json([
                'status'  => false,
                'message' => 'غير مصرح لك بتتبع هذه الشكوى.'
            ], 403);
        }

        $timelineData = $this->service->trackComplaint($ref, $user);

        return response()->json([
            'status'  => true,
            'message' => 'تم جلب مسار الشكوى بنجاح.',
            'data'    => $timelineData
        ]);
    }
}
