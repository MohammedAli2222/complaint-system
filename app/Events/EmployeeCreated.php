<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmployeeCreated
{
    use Dispatchable, SerializesModels;

    public User $employee;

    /**
     * Create a new event instance.
     */
    public function __construct(User $employee)
    {
        $this->employee = $employee;
    }
}
