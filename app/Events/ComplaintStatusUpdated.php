<?php
// app/Events/ComplaintStatusUpdated.php

namespace App\Events;

use App\Models\Complaint;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ComplaintStatusUpdated
{
    use Dispatchable, SerializesModels;

    public $complaint;

    public function __construct(Complaint $complaint)
    {
        $this->complaint = $complaint;
    }
}
