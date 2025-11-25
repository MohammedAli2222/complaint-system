<?php
// app/Http/Controllers/ComplaintController.php

namespace App\Http\Controllers;

use App\Http\Resources\ComplaintResource;
use App\Models\Complaint;
use App\Services\ComplaintService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
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
        ], 201);
    }
    // عرض شكوى
    public function show(string $ref)
    {
        $complaint = Complaint::where('reference_number', $ref)
            ->with('history', 'attachments') // تحميل العلاقات مسبقاً
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
            // التعامل مع محاولات القفل المكررة أو المتزامنة
            $statusCode = 409; // Conflict

            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], $statusCode);
        }
    }

    public function track($ref, Request $request)
    {
        $complaint = $this->service->trackComplaint($ref, $request->user());

        if (!$complaint) {
            return response()->json(['status' => false, 'message' => 'الشكوى غير موجودة أو لا تملك صلاحية رؤيتها'], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $complaint
        ]);
    }
    public function updateStatus($id, Request $request): JsonResponse
    {
        $complaint = Complaint::findOrFail($id);
        $this->authorize('update', $complaint); //  تطبيق السياسة

        $request->validate([
            'status' => 'required|string|in:new,processing,rejected,done', // يجب حصر الحالات الممكنة
            'notes'  => 'nullable|string'
        ]);

        $this->service->updateStatus(
            $id,
            $request->status,
            $request->notes,
            $request->user()
        );

        return response()->json(['status' => true, 'message' => 'تم تحديث الحالة وإرسال إشعار بريدي للمواطن.']);
    }
    public function assign($id, Request $request)
    {
        $complaint = Complaint::findOrFail($id);

        // تطبيق سياسة التعيين (التي تسمح للمدير فقط بالتعيين)
        $this->authorize('assign', $complaint);

        // 🚨 التعديل المطلوب في قواعد التحقق (Validation) 🚨
        $request->validate([
            'employee_id' => [
                'required',
                'exists:users,id',
                // 👈 القيد الجديد: التحقق من أن المستخدم المعين لديه دور 'employee'
                // نستخدم whereHas للتأكد من علاقة الأدوار
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

        return response()->json(['status' => true, 'message' => 'تم تعيين الشكوى بنجاح وإرسال إشعار للموظف.']);
    }
    public function unlock($id, Request $request): JsonResponse
    {
        // 🔒 التحقق من الصلاحية (الموظف أو المشرف فقط يستطيع فتح القفل)
        if (!$request->user()->hasRole(['employee', 'admin'])) {
            return response()->json(['message' => 'Unauthorized. Only employees or admins can unlock complaints.'], 403);
        }

        try {
            // يتم تمرير الطلب إلى طبقة الخدمة لتنفيذ المنطق
            $this->service->unlock($id, $request->user());
            return response()->json(['status' => true, 'message' => 'تم فتح قفل الشكوى بنجاح.']);
        } catch (\Exception $e) {
            // يتم إرجاع 403 إذا كانت المشكلة في الصلاحيات أو إذا حاول موظف فتح قفل شكوى لم يقم هو بقفلها
            $statusCode = $e->getCode() === 403 ? 403 : 409;
            return response()->json(['status' => false, 'message' => $e->getMessage()], $statusCode);
        }
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

        return response()->json(['message' => 'تمت إضافة الملاحظة بنجاح']);
    }

    // طلب معلومات إضافية من المواطن
    public function requestMoreInfo(Request $request, $id)
    {
        $complaint = Complaint::findOrFail($id);

        $this->authorize('requestMoreInfo', $complaint);

        $this->service->requestMoreInfo($complaint, $request->message);

        return response()->json([
            'status' => true,
            'message' => 'تم إرسال طلب معلومات إضافية للمواطن'
        ]);
    }
}
