<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function register(Request $request)
    {
<<<<<<< HEAD
        $validator = Validator::make($request->all(), [
=======
        // هون عدلت
        /* $validator = Validator::make($request->all(), [
>>>>>>> a5b16c9d1c7fee0adccb0160511e5bdc5c3596a8
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'phone_number' => 'required|string|min:8',
            'address' => 'required|string',
            'gender' => 'required',
            'date_of_birth' => 'required|date',
            'blood_type' => 'nullable|string',
<<<<<<< HEAD
            'emergency_contact' => 'nullable|string'
=======
            'emergency_contact' => 'nullable|string' */
        //  ]); */

        $validator  = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
>>>>>>> a5b16c9d1c7fee0adccb0160511e5bdc5c3596a8
        ]);


        return DB::transaction(function () use ($request) {
            $patientRole = Role::firstOrCreate(
                ['name' => 'patient'],
                ['description' => 'Patient user']
            );

            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
<<<<<<< HEAD
                'name' => $request->first_name . ' ' . $request->last_name,
=======
                //   'name' => $request->first_name . ' ' . $request->last_name,
>>>>>>> a5b16c9d1c7fee0adccb0160511e5bdc5c3596a8
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
<<<<<<< HEAD
                'phone_number' => $request->phone_number,
                'date_of_birth' => $request->date_of_birth,
                'address' => $request->address,
                'gender' => $request->gender,
                'blood_type' => $request->blood_type,
                'emergency_contact' => $request->emergency_contact,
=======
                //   'phone_number' => $request->phone_number,
                //  'date_of_birth' => $request->date_of_birth,
                //  'address' => $request->address,
                //  'gender' => $request->gender,
                //   'blood_type' => $request->blood_type,
                //  'emergency_contact' => $request->emergency_contact,
>>>>>>> a5b16c9d1c7fee0adccb0160511e5bdc5c3596a8
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
<<<<<<< HEAD
=======

>>>>>>> a5b16c9d1c7fee0adccb0160511e5bdc5c3596a8
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

<<<<<<< HEAD
=======
        // in case patient
        if ($user->role->name === 'patient') {

            return response()->json([
                'message' => 'Patient logged in successfully',
                'user' => $user->load('patient'),
                'access_token' => $token,
                'token_type' => 'Bearer',

            ]);
        }

>>>>>>> a5b16c9d1c7fee0adccb0160511e5bdc5c3596a8
        return response()->json([
            'user' => $user->load('role'),
            'access_token' => $token,
            'token_type' => 'Bearer',
<<<<<<< HEAD
'role_name'=> $user->role->name
=======
            'role_name' => $user->role->name
>>>>>>> a5b16c9d1c7fee0adccb0160511e5bdc5c3596a8
        ]);
    }
}
