<?php

namespace App\Events;

use App\Models\Complaint;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CitizenResponded
{
    use Dispatchable, SerializesModels;

    public function __construct(public Complaint $complaint)
    {
        //
    }

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('complaint.' . $this->complaint->user_id);
    }
}
