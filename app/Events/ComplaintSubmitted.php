<?php
// app/Events/ComplaintSubmitted.php

namespace App\Events;

use App\Models\Complaint;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ComplaintSubmitted
{
    use Dispatchable, SerializesModels;

    public $complaint;

    public function __construct(Complaint $complaint)
    {
        $this->complaint = $complaint;
    }
}
