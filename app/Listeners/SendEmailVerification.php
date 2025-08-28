<?php

namespace App\Listeners;

use App\Events\EmailVerification;
use App\Mail\SendCodeEmailVerification;
use App\Models\EmailVerification as ModelsEmailVerification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendEmailVerification
{
    public function handle(EmailVerification $event): void
    {
        try {
            Log::info('Email verification event received for: ' . $event->email);

            // Delete any existing verification codes for this email
            $deleted = ModelsEmailVerification::where('email', $event->email)->delete();
            Log::info('Deleted ' . $deleted . ' existing verification codes');

            $code = mt_rand(100000, 999999);
            Log::info('Generated verification code: ' . $code);

            // Create new verification code
            $codeData = ModelsEmailVerification::create([
                'email' => $event->email,
                'code' => $code
            ]);

            Log::info('Verification code saved to database for: ' . $event->email);

            // Send email with the code
            Mail::to($event->email)->send(new SendCodeEmailVerification($code));

            Log::info('Verification email sent to: ' . $event->email);

        } catch (\Exception $e) {
            Log::error('Failed to send verification email: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
        }
    }
}
