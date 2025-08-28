<?php

namespace App\Http\Controllers\Auth;

use App\Events\EmailVerification as EventsEmailVerification;
use Illuminate\Http\Request;
use App\Http\Controllers\LocalController;
use App\Models\EmailVerification as EmailVerificationModel;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class EmailVerificationController extends LocalController
{
    public function userCheckCode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:email_verifications,email',
            'code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first());
        }

        try {
            $emailVerification = EmailVerificationModel::where('email', $request->email)
                ->where('code', $request->code)
                ->first();

            if (!$emailVerification) {
                return $this->sendError('Invalid verification code');
            }

            // Check if code is expired (1 hour)
            if ($emailVerification->created_at->addHour()->isPast()) {
                $emailVerification->delete();
                return $this->sendError('Verification code has expired');
            }

            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return $this->sendError('User not found');
            }

            $user->update([
                'email_verified_at' => now(),
            ]);

            $emailVerification->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Email verified successfully',
            ], 200);

        } catch (\Exception $e) {
            Log::error('Email verification error: ' . $e->getMessage());
            return $this->sendError('An error occurred during verification');
        }
    }

    public function resendCode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'exists:users,email']
        ]);

        if ($validator->fails()) {
            return $this->sendError($validator->errors()->first());
        }

        try {
            EventsEmailVerification::dispatch($request->email);

            return $this->sendResponse([
                'message' => 'Verification code sent successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Resend code error: ' . $e->getMessage());
            return $this->sendError('Failed to send verification code');
        }
    }
}
