<?php

namespace App\Mail;

use App\Models\Complaint;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ComplaintStatusMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $complaint;
    public $newStatus;

    /**
     * استقبال كائن الشكوى والحالة الجديدة
     */
    public function __construct(Complaint $complaint, string $newStatus)
    {
        $this->complaint = $complaint;
        $this->newStatus = $newStatus;
    }

    /**
     * عنوان الرسالة
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'تحديث حالة الشكوى رقم: ' . $this->complaint->reference_number,
        );
    }
    public function content(): Content
    {
        // هنا نستخدم markdown بسيط أو html
        return new Content(
            htmlString: "
                <h1>مرحباً {$this->complaint->user->name}</h1>
                <p>تم تحديث حالة الشكوى الخاصة بك ذات الرقم المرجعي: <strong>{$this->complaint->reference_number}</strong></p>
                <p>الحالة الجديدة: <span style='color:blue'>{$this->newStatus}</span></p>
                <br>
                <p>يمكنك متابعة التفاصيل عبر التطبيق.</p>
                <p>شكراً لك،<br>نظام الشكاوى الحكومي</p>
            "
        );
    }
}
