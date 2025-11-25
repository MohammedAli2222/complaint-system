<?php

namespace App\Http\Controllers;

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
    
}
