<?php

namespace App\Services;

use App\Repositories\UserRepository;
use App\Events\UserRegistered;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

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
        // التحقق من البيانات الأساسية
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

        // تحقق من قوة كلمة المرور
        $passwordError = $this->validatePassword($data['password']);
        if ($passwordError) {
            return response()->json([
                'status' => false,
                'message' => $passwordError
            ], 422);
        }

        // التحقق من وجود الإيميل في الداتابيز
        if ($this->repo->findByEmail($data['email'])) {
            $this->auditService->logSecurityEvent('duplicate_registration_attempt', [
                'email' => $data['email']
            ]);
            return response()->json([
                'status' => false,
                'message' => 'Email already registered. Please login instead.'
            ], 422);
        }

        // التحقق من وجود عملية تسجيل pending
        $cacheKey = "pending_user_" . $data['email'];
        if (Cache::get($cacheKey)) {
            return response()->json([
                'status' => false,
                'message' => 'Registration already in progress. Please check your email or try again later.'
            ], 422);
        }

        // إنشاء OTP وتخزين مؤقت
        $otp = (string) rand(100000, 999999);
        $data['password'] = Hash::make($data['password']);
        $data['role'] = 'citizen'; // تحديد الدور الافتراضي

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
                // 'role' => $user->role
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

        $user = $this->repo->findByEmail($credentials['email']);

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            $this->handleFailedLogin($credentials['email']);
            $this->auditService->logSecurityEvent('failed_login', ['email' => $credentials['email']]);
            return response()->json([
                'status' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        $this->resetLoginAttempts($credentials['email']);

        $token = $user->createToken('auth_token')->plainTextToken;

        $this->auditService->logAction($user->id, 'user_logged_in');

        return response()->json([
            'status' => true,
            'message' => "Login successful",
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role
            ]
        ]);
    }

    // ========== الدوال المساعدة للأمان ==========

    private function validatePassword(string $password): ?string
    {
        if (strlen($password) < 8) {
            return 'Password must be at least 8 characters long';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            return 'Password must contain at least one uppercase letter';
        }

        if (!preg_match('/[a-z]/', $password)) {
            return 'Password must contain at least one lowercase letter';
        }

        if (!preg_match('/[0-9]/', $password)) {
            return 'Password must contain at least one number';
        }

        return null;
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

        // تنظيف الحظر إذا انتهى
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

        // إذا تجاوز الحد المسموح، احظر الحساب
        if ($currentAttempts >= $this->maxLoginAttempts) {
            $lockoutKey = "account_lockout_" . $email;
            $lockoutUntil = now()->addMinutes($this->lockoutTime);

            Cache::put($lockoutKey, $lockoutUntil, $lockoutUntil);

            $this->auditService->logSecurityEvent('account_locked', [
                'email' => $email,
                'attempts' => $currentAttempts,
                'locked_until' => $lockoutUntil
            ]);
        }
    }

    private function resetLoginAttempts(string $email): void
    {
        Cache::forget("login_attempts_" . $email);
        Cache::forget("account_lockout_" . $email);
    }

    public function logout($user): JsonResponse
    {
        $user->currentAccessToken()->delete();

        $this->auditService->logAction($user->id, 'user_logged_out');

        return response()->json([
            'status' => true,
            'message' => 'Logout successful'
        ]);
    }
}
