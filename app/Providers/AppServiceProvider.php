<?php

namespace App\Providers;

use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use Notification;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot()
{
    Notification::extend('database', function ($app) {
        return new DatabaseChannel();
    });

}


}
