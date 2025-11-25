<?php

namespace App\Http\Controllers;

use App\Services\AdminUserService;
use Illuminate\Http\Request;
use App\Models\User;

class AdminUserController extends Controller
{
    protected $service;

    public function __construct(AdminUserService $service)
    {
        $this->middleware('auth:sanctum');
        $this->service = $service;
    }

    //إرجاع جميع الموظفين مع الجهة والصلاحيات
    public function index()
    {
        $this->authorize('viewAny', User::class); // التحقق من صلاحية العرض العام

        $employees = $this->service->listEmployees();
        return response()->json([
            'status' => true,
            'employees' => $employees
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', User::class); // التحقق من صلاحية الإنشاء

        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'email'        => 'required|email|unique:users',
            'password'     => 'required|min:6',
            'entity_id'    => 'required|exists:entities,id',
            'permissions'  => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name'
        ]);

        $user = $this->service->createEmployee($validated);

        return response()->json(['status' => true, 'user' => $user]);
    }

    public function update(Request $request, $id)
    {
        $employee = User::findOrFail($id);
        $this->authorize('update', $employee); // التحقق من صلاحية التحديث

        $validated = $request->validate([
            'name'      => 'sometimes',
            'email'     => 'sometimes|email',
            'password'  => 'sometimes|min:6',
            'entity_id' => 'sometimes|exists:entities,id'
        ]);


        $user = $this->service->updateEmployee($id, $validated);

        return response()->json(['status' => true, 'user' => $user]);
    }

    public function updatePermissions(Request $request, $id)
    {
        $employee = User::findOrFail($id);
        $this->authorize('updatePermissions', $employee); // التحقق من صلاحية تحديث الصلاحيات

        $validated = $request->validate([
            'permissions'   => 'required|array',
            'permissions.*' => 'string|exists:permissions,name'
        ]);

        $user = $this->service->updatePermissions($id, $validated['permissions']);

        return response()->json(['status' => true, 'user' => $user]);
    }

    public function destroy($id)
    {
        $employee = User::findOrFail($id);
        $this->authorize('delete', $employee); // التحقق من صلاحية الحذف

        $this->service->deleteEmployee($id);

        return response()->json(['status' => true]);
    }
}
