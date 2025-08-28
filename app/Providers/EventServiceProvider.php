<?php

namespace App\Providers;

use App\Events\EmailVerification;
use App\Events\ResetPassword;
use App\Listeners\ResetPassword as ListenersResetPassword;
use App\Listeners\SendEmailVerification;
use App\Mail\SendCodeEmailVerification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;


class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        EmailVerification::class => [
            SendEmailVerification::class,
        ],

        ResetPassword::class => [
            ListenersResetPassword::class,
        ],

    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {

    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
