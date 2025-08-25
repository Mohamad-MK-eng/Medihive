<?php

namespace App\Listeners;

use App\Events\EmailVerification;
use App\Mail\SendCodeEmailVerification;
use App\Models\EmailVerification as ModelsEmailVerification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendEmailVerification implements ShouldQueue
{
    public function handle(EmailVerification $event): void
    {
        // Use query() method like your friend
        ModelsEmailVerification::query()->where('email', $event->email)->delete();

        $data['email'] = $event->email;
        $data['code'] = mt_rand(100000, 999999);

        // Use query() method like your friend
        $codeData = ModelsEmailVerification::query()->create($data);

        // Send email with the code
        Mail::to($event->email)->send(new SendCodeEmailVerification($codeData['code']));
    }
}
