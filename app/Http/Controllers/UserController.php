<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    protected UserService $service;

    public function __construct(UserService $service)
    {
        $this->service = $service;
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => ['required', Password::min(8)->mixedCase()->numbers()],
        ]);

        return $this->service->register($request->all());
    }
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'otp' => 'required|string|size:6'
        ]);

        return $this->service->verifyOtp(
            $request->input('email'),
            $request->input('otp')
        );
    }
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string'
        ]);

        return $this->service->login($request->all());
    }
    public function logout(Request $request)
    {
        return $this->service->logout($request->user());
    }
    public function getCitizen($id)
    {
        $this->authorize('viewCitizens');

        return $this->service->getCitizenById($id);
    }
    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $type = $request->query('type', 'all');
        $perPage = $request->query('per_page', 15);

        $users = $this->service->getUsersByType($type, $perPage);

        return response()->json([
            'status' => true,
            'message' => 'تم جلب المستخدمين بنجاح',
            'filtered_by' => $type,
            'total' => $users->total(),
            'data' => $users->items(),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ]
        ]);
    }

    public function myPermissions(Request $request)
    {
        $user = $request->user();

        $permissions = $user->getAllPermissions()->pluck('name')->unique()->values();

        return response()->json([
            'status' => true,
            'message' => 'تم جلب صلاحياتك بنجاح.',
            'data' => $permissions
        ]);
    }
}
