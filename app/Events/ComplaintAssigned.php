<?php

namespace App\Events;

use App\Models\Complaint;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ComplaintAssigned
{
    use Dispatchable, InteractsWithSockets, SerializesModels;


    // app/Events/ComplaintAssigned.php
    public $complaint;
    public $employeeId;

    /**
     * Create a new event instance.
     */

    public function __construct(Complaint $complaint, int $employeeId)
    {
        $this->complaint = $complaint;
        $this->employeeId = $employeeId;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
