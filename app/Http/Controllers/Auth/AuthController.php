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
        // هون عدلت
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            /* 'phone_number' => 'nullabe|string|min:8',
            'address' => 'nullable|string',
            'gender' => 'nullable',
            'date_of_birth' => 'nullable|date',
            'blood_type' => 'nullable|string',
            'emergency_contact' => 'nullable|string' */
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
             //   'name' => $request->first_name . ' ' . $request->last_name,
                'email' => $request->email,
                /* 'date_of_birth' => $request->date_of_birth,
                'blood_type' => $request->blood_type,
                'gender' => $request->gender, */
                'password' => Hash::make($request->password),
                //'phone_number' => $request->phone_number,
                'role_id' => $patientRole->id,
            ]);

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
                'user' => $user->load('patient')
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
      if ($user->role->name === 'patient')
      {
        
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
             'role_name'=> $user->role->name
        ]);
    }
}
