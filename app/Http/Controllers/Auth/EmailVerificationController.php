<?php

namespace App\Http\Controllers\Auth;

use App\Events\EmailVerification as EventsEmailVerification;
use Illuminate\Http\Request;
use App\Http\Controllers\LocalController;
use App\Models\EmailVerification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class EmailVerificationController extends LocalController
{
    public function userCheckCode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:email_verifications,email',
            'code' => 'required|string|exists:email_verifications,code',
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors());
        }

        // find the code - use first() instead of firstWhere() for better error handling
        $emailverification = EmailVerification::where('email', $request->email)->first();

        // Check if verification record was found
        if (!$emailverification) {
            return $this->sendError(['error' => 'No verification code found for this email']);
        }

        // Check if code matches - use object syntax instead of array
        if ($request->code != $emailverification->code) {
            return $this->sendError(['error' => 'Code is not valid']);
        }

        // check if it is not expired: the time is one hour
        if ($emailverification->created_at->addHour()->isPast()) {
            $emailverification->delete();
            return $this->sendError(['error' => 'Verification code has expired']);
        }

        // find user
        $user = User::where('email', $emailverification->email)->first();

        if (!$user) {
            return $this->sendError(['error' => 'User not found']);
        }

        // update user email_verified
        $user->update([
            'email_verified_at' => now(), // Use email_verified_at instead of email_verified
        ]);

        // delete current code
        $emailverification->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Email verified successfully',
        ], 200);
    }

    public function resendCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'exists:users,email']
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors());
        }

        EventsEmailVerification::dispatch($request->email);

        return $this->sendResponse(['message' => 'Verification code sent successfully']);
    }
}
