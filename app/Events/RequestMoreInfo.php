<?php

namespace App\Events;

use App\Models\Complaint;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class RequestMoreInfo implements ShouldBroadcast
{
    use InteractsWithSockets;

    public function __construct(public Complaint $complaint, public string $message) {}

    public function broadcastOn()
    {
        return new PrivateChannel('complaint.' . $this->complaint->user_id);
    }

    public function broadcastAs()
    {
        return 'request-more-info';
    }
}
