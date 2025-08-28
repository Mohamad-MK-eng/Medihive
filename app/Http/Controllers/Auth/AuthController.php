<?php

namespace App\Http\Controllers\Auth;

use App\Events\EmailVerification;
use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\User;
use App\Models\Role;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as RoutingController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Password;
use Str;

class AuthController extends Controller
{


    /* original
    public function register(Request $request)
    {

        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
        ]);


        return DB::transaction(function () use ($request) {
            $patientRole = Role::firstOrCreate(
                ['name' => 'patient'],
                ['description' => 'Patient user']
            );

            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role_id' => $patientRole->id,
                'email_verified_at'=>false,
            ]);



            /// me me me m e
            //  this is  insan
            Patient::create([
                'user_id' => $user->id,

            ]);



 event(new \App\Events\EmailVerification($user->email));

        $token = $user->createToken('Patient Access Token')->accessToken;
            return response()->json([
                'message' => 'Patient registered successfully',
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => $user
            ], 201);
        });
    }

*/



 public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'phone_number' => 'required|string|min:8',
            'address' => 'required|string',
            'gender' => 'required',
            'date_of_birth' => 'required|date',
            'blood_type' => 'nullable|string',
            'emergency_contact' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        return DB::transaction(function () use ($request) {
            $patientRole = Role::firstOrCreate(
                ['name' => 'patient'],
                ['description' => 'Patient user']
            );

            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'name' => $request->first_name . ' ' . $request->last_name,
                'email' => $request->email,
                'date_of_birth' => $request->date_of_birth,
                'blood_type' => $request->blood_type,
                'gender' => $request->gender,
                'password' => Hash::make($request->password),
                'phone_number' => $request->phone_number,
                'role_id' => $patientRole->id,
                'email_verified_at' => null, // Ensure email is not verified initially
            ]);

            Patient::create([
                'user_id' => $user->id,
                'phone_number' => $request->phone_number,
                'date_of_birth' => $request->date_of_birth,
                'address' => $request->address,
                'gender' => $request->gender,
                'blood_type' => $request->blood_type,
                'emergency_contact' => $request->emergency_contact,
            ]);

            // 🔥 CRITICAL: Dispatch the email verification event
            EmailVerification::dispatch($user->email);

            $token = $user->createToken('Patient Access Token')->accessToken;

            return response()->json([
                'message' => 'Patient registered successfully. Please check your email for verification.',
                'access_token' => $token,
                'token_type' => 'Bearer',
                'patient' => $user->load('patient'),
                'email_verified' => false // Indicate email needs verification
            ], 201);
        });
    }


    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        if (!Auth::attempt($credentials)) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Invalid credentials'
            ], 401);
        }

        $user = Auth::user();
        $user->tokens()->delete();

        $tokenName = ucfirst($user->role->name) . ' Access Token';
        $token = $user->createToken($tokenName)->accessToken;

        $roleName = strtolower($user->role->name);
        $welcomeMessage = ucfirst($roleName) . ' logged in successfully';

        // Common user data
        $userData = [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'role_id' => $user->role_id,
            'role_name' => $roleName,
            'profile_picture' => $user->getProfilePictureUrl(),
        ];

        // Add role-specific data and return appropriate response
        switch ($roleName) {
            case 'patient':
                if (!$user->patient) {
                    Auth::logout();
                    return response()->json([
                        'error' => 'Forbidden',
                        'message' => 'Patient account not properly configured'
                    ], 403);
                }
                $userData['patient_id'] = $user->patient->id;
                $userData['phone_number'] = $user->patient->phone_number;
                break;

            case 'doctor':
                if (!$user->doctor) {
                    Auth::logout();
                    return response()->json([
                        'error' => 'Forbidden',
                        'message' => 'Doctor account not properly configured'
                    ], 403);
                }
                $userData['doctor_id'] = $user->doctor->id;
                $userData['specialty'] = $user->doctor->specialty;
                break;

            case 'secretary':
                if (!$user->secretary) {
                    Auth::logout();
                    return response()->json([
                        'error' => 'Forbidden',
                        'message' => 'Secretary account not properly configured'
                    ], 403);
                }
                $userData['secretary_id'] = $user->secretary->id;
                break;

            case 'admin':
                break;

            default:
                Auth::logout();
                return response()->json([
                    'error' => 'Forbidden',
                    'message' => 'Unknown user role'
                ], 403);
        }

        return response()->json([
            'message' => $welcomeMessage,
            'user' => $userData,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }
    // to try
    public function sendPasswordResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => __($status)])
            : response()->json(['error' => __($status)], 400);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => __($status)])
            : response()->json(['error' => __($status)], 400);
    }


    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:8',
        ]);

        $user = Auth::user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Current password is incorrect'], 400);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json(['message' => 'Password changed successfully']);
    }


    public function logout(Request $request)
    {
        try {
            $request->user()->token()->revoke();

            return response()->json([
                'success' => true,
                'message' => 'Successfully logged out'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to logout',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
