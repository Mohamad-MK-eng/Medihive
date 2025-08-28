<?php

namespace App\Listeners;

use App\Mail\ResetPassword as MailResetPassword;
use App\Models\ResetCodePassword;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class ResetPassword
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        //Delete all old codes that user send before.
        ResetCodePassword::query()->where('email', $event->email)->delete();

        $data['email'] = $event->email;

        //Generate random code
        $data['code'] = mt_rand(100000,999999);

        //Create a new code
        $codeData = ResetCodePassword::query()->create($data);

        //Send email to user
        Mail::to($event->email)->send(new MailResetPassword($codeData['code']));
    }
}
