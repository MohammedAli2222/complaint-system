<?php

namespace App\Providers;

use App\Events\EmployeeCreated;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Events\UserRegistered;
use App\Listeners\LogEmployeeCreation;
use App\Listeners\SendOtpEmail;
use App\Listeners\LogUserRegistration;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        UserRegistered::class => [
            SendOtpEmail::class,
            LogUserRegistration::class,
        ],
        EmployeeCreated::class => [
            LogEmployeeCreation::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        parent::boot(); // ← هذا اللي بيكون فيه boot
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
