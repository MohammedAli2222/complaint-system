<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use Illuminate\Support\Facades\Mail;
use App\Mail\OtpMail;
use Illuminate\Support\Facades\Log;

class SendOtpEmail
{
    public function handle(UserRegistered $event)
    {
        try {
            Mail::to($event->user->email)->send(new OtpMail($event->otp));
        } catch (\Exception $e) {
            Log::error('Failed to send OTP email: ' . $e->getMessage()); // ← الآن Log معرف
        }
    }
}
