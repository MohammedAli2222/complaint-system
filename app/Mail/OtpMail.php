<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OtpMail  extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $otp;   // ← ← ← هنا الحل

    /**
     * Create a new message instance.
     */
    public function __construct($otp)
    {
        $this->otp = $otp; // ← تخزين الكود
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject("Your OTP Code")
                    ->markdown('emails.otp')
                    ->with([
                        'otp' => $this->otp
                    ]);
    }
}
