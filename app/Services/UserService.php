<?php

namespace App\Services;

use App\Repositories\UserRepository;
use App\Events\UserRegistered;
use App\Jobs\AuditLogJob;
use App\Mail\AccountLockedMail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class UserService
{
    protected UserRepository $repo;
    protected AuditService $auditService;
    protected int $otpTTL = 10;
    protected int $maxLoginAttempts = 5;
    protected int $lockoutTime = 30; // دقائق

    public function __construct(UserRepository $repo, AuditService $auditService)
    {
        $this->repo = $repo;
        $this->auditService = $auditService;
    }
    public function register(array $data): JsonResponse
    {
        if (!isset($data['email']) || !isset($data['password']) || !isset($data['name'])) {
            return response()->json([
                'status' => false,
                'message' => 'Email, name and password are required'
            ], 422);
        }

        // تحقق من صيغة الإيميل
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid email format'
            ], 422);
        }

        if ($this->repo->findByEmail($data['email'])) {
            $this->auditService->logSecurityEvent('duplicate_registration_attempt', [
                'email' => $data['email']
            ]);
            return response()->json([
                'status' => false,
                'message' => 'Email already registered. Please login instead.'
            ], 422);
        }

        $cacheKey = "pending_user_" . $data['email'];
        if (Cache::get($cacheKey)) {
            return response()->json([
                'status' => false,
                'message' => 'Registration already in progress. Please check your email or try again later.'
            ], 422);
        }

        $otp = (string) rand(100000, 999999);
        $data['password'] = Hash::make($data['password']);


        Cache::put($cacheKey, [
            'data' => $data,
            'otp' => $otp
        ], now()->addMinutes($this->otpTTL));

        $tempUser = (object)[
            'id' => null,
            'email' => $data['email'],
            'name' => $data['name'],
            'role' => 'citizen'
        ];

        event(new UserRegistered($tempUser, $otp));

        return response()->json([
            'status' => true,
            'message' => 'OTP sent to your email. Please verify within 10 minutes.',
            'pending_email' => $data['email']
        ]);
    }
    public function verifyOtp(string $email, string $otp): JsonResponse
    {
        $cacheKey = "pending_user_" . $email;
        $pending = Cache::get($cacheKey);

        if (!$pending) {
            $this->auditService->logSecurityEvent('expired_otp_attempt', ['email' => $email]);
            return response()->json([
                'status' => false,
                'message' => 'OTP expired or invalid request'
            ], 404);
        }

        if ((string)$pending['otp'] !== (string)$otp) {
            $this->auditService->logSecurityEvent('invalid_otp_attempt', ['email' => $email]);
            return response()->json([
                'status' => false,
                'message' => 'Invalid OTP'
            ], 400);
        }

        $user = $this->repo->create($pending['data']);
        $user->assignRole('citizen');
        Cache::forget($cacheKey);

        $this->auditService->logAction($user->id, 'user_verified', [
            'email' => $user->email,
            'role' => $user->role
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Account created successfully.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames()
            ]
        ]);
    }

    public function login(array $credentials): JsonResponse
    {
        if (!isset($credentials['email']) || !isset($credentials['password'])) {
            return response()->json([
                'status' => false,
                'message' => 'Email and password are required'
            ], 422);
        }

        $lockoutCheck = $this->checkAccountLockout($credentials['email']);
        if ($lockoutCheck) {
            return $lockoutCheck;
        }

        if (!Auth::attempt($credentials)) {
            $this->handleFailedLogin($credentials['email']);

            return response()->json([
                'status' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        $user = Auth::user();
        $this->resetLoginAttempts($user->email);

        $token = $user->createToken('auth_token')->plainTextToken;

        dispatch(new AuditLogJob(
            $user->id,
            'user_logged_in',
            [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]
        ));

        return response()->json([
            'status' => true,
            'message' => "Login successful",
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email
            ]
        ]);
    }

    public function logout($user): JsonResponse
    {
        $user->tokens()->delete();

        dispatch(new AuditLogJob(
            $user->id,
            'user_logged_out',
            [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'logout_at' => now()->toDateTimeString()
            ]
        ));


        return response()->json([
            'status' => true,
            'message' => 'Logout successful.'
        ], 200);
    }
    private function checkAccountLockout(string $email): ?JsonResponse
    {
        $lockoutKey = "account_lockout_" . $email;
        $lockoutTime = Cache::get($lockoutKey);

        if ($lockoutTime && now()->lessThan($lockoutTime)) {
            $remainingMinutes = now()->diffInMinutes($lockoutTime);
            return response()->json([
                'status' => false,
                'message' => "Account temporarily locked. Try again in {$remainingMinutes} minutes."
            ], 423);
        }

        if ($lockoutTime && now()->greaterThan($lockoutTime)) {
            Cache::forget($lockoutKey);
            $this->resetLoginAttempts($email);
        }

        return null;
    }
    private function handleFailedLogin(string $email): void
    {
        $attemptsKey = "login_attempts_" . $email;

        $currentAttempts = Cache::get($attemptsKey, 0) + 1;

        Cache::put($attemptsKey, $currentAttempts, now()->addMinutes($this->lockoutTime));

        if ($currentAttempts >= $this->maxLoginAttempts) {

            $lockoutKey = "account_lockout_" . $email;
            $lockoutUntil = now()->addMinutes($this->lockoutTime);

            Cache::put($lockoutKey, $lockoutUntil, $lockoutUntil);

            try {
                Mail::to($email)->queue(new AccountLockedMail($lockoutUntil));
            } catch (\Exception $e) {
                Log::error("Failed to send account locked email: " . $e->getMessage());
            }

            dispatch(new AuditLogJob(
                null,
                'account_locked',
                [
                    'email' => $email,
                    'attempts' => $currentAttempts,
                    'locked_until' => $lockoutUntil
                ]
            ));
        } else {
            dispatch(new AuditLogJob(
                null,
                'failed_login',
                ['email' => $email]
            ));
        }
    }
    private function resetLoginAttempts(string $email): void
    {
        Cache::forget("login_attempts_" . $email);
        Cache::forget("account_lockout_" . $email);
    }


    public function createEmployee(array $data): JsonResponse
    {
        $data['password'] = Hash::make($data['password']);
        $user = $this->repo->create($data);
        $user->assignRole('employee');

        return response()->json([
            'status' => true,
            'message' => 'Employee user created successfully.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->getRoleNames()->first(),
                'entity_id' => $user->entity_id,
            ]
        ], 201);
    }

    public function getCitizenById(int $id): JsonResponse
    {
        $citizen = $this->repo->findCitizenById($id);

        if (!$citizen) {
            return response()->json([
                'status' => false,
                'message' => 'Citizen not found.'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Citizen details retrieved successfully.',
            'data' => [
                'id' => $citizen->id,
                'name' => $citizen->name,
                'email' => $citizen->email,
                'role' => $citizen->getRoleNames()->first()
            ]
        ]);
    }


    public function getUsersByType(string $type = 'all', int $perPage = 15)
    {
        $currentUser = auth()->user();
        return $this->repo->getFilteredUsers($type, $perPage, $currentUser);
    }
}
