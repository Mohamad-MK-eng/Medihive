<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\User;
use App\Models\Role;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Password;
use Str;

class AuthController extends Controller
{
    public function register(Request $request)
    {

        $validator  = $request->validate([
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
                'name' => $request->first_name . ' ' . $request->last_name,
                //   'name' => $request->first_name . ' ' . $request->last_name,
                'email' => $request->email,
                'date_of_birth' => $request->date_of_birth,
                'blood_type' => $request->blood_type,
                'gender' => $request->gender,
                'password' => Hash::make($request->password),
                'phone_number' => $request->phone_number,
                'role_id' => $patientRole->id,
            ]);
                //  this is fucking insan
            Patient::create([
                'user_id' => $user->id,
                //   'phone_number' => $request->phone_number,
                //  'date_of_birth' => $request->date_of_birth,
                //  'address' => $request->address,
                //  'gender' => $request->gender,
                //   'blood_type' => $request->blood_type,
                //  'emergency_contact' => $request->emergency_contact,
            ]);



            $token = $user->createToken('Patient Access Token')->accessToken;

            return response()->json([
                'message' => 'Patient registered successfully',
                'access_token' => $token,
                'token_type' => 'Bearer',
                'patient' => $user->load('patient')
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
        $user->tokens()->delete(); // for  previous tokens

        $token = $user->createToken('Personal Access Token')->accessToken;

        // in case patient
        if ($user->role->name === 'patient') {

            return response()->json([
                'message' => 'Patient logged in successfully',
                'user' => $user->load('patient'),
                'access_token' => $token,
                'token_type' => 'Bearer',

            ]);
        }

        return response()->json([
            'user' => $user->load('role'),
            'access_token' => $token,
            'token_type' => 'Bearer',
'role_name'=> $user->role->name,
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

/**
 * Reset password
 */
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

/**
 * Change password (protected route - requires authentication)
 */
public function changePassword(Request $request)
{
    $request->validate([
        'current_password' => 'required',
        'new_password' => 'required|min:8|confirmed',
    ]);

    $user = Auth::user();

    if (!Hash::check($request->current_password, $user->password)) {
        return response()->json(['error' => 'Current password is incorrect'], 400);
    }

    $user->password = Hash::make($request->new_password);
    $user->save();

    return response()->json(['message' => 'Password changed successfully']);
}
}

