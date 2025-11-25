<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountLockedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $lockoutUntil;

    public function __construct($lockoutUntil)
    {
        $this->lockoutUntil = $lockoutUntil;
    }

    public function build()
    {
        return $this->subject('Account Locked Due to Multiple Failed Login Attempts')
                    ->view('emails.account_locked')
                    ->with([
                        'lockoutUntil' => $this->lockoutUntil,
                    ]);
    }
}
