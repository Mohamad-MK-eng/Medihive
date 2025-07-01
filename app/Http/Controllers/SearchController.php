<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Secretary;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    // Global clinic search
    public function searchClinics(Request $request)
    {
        $query = Clinic::query();

        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        if ($request->has('location')) {
            $query->where('location', 'like', '%' . $request->location . '%');
        }

   $results = $query->withCount('doctors')->get();

        if ($results->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No clinics found matching your search criteria'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $results
        ]);
    }

    // Global doctor search
    public function searchDoctors(Request $request)
    {
        $query = Doctor::with(['user', 'clinic', 'reviews']);

        if ($request->has('name')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('first_name', 'like', '%' . $request->name . '%')
                    ->orWhere('last_name', 'like', '%' . $request->name . '%');
            });
        }

        if ($request->has('specialty')) {
            $query->where('specialty', 'like', '%' . $request->specialty . '%');
        }

        if ($request->has('clinic_id')) {
            $query->where('clinic_id', $request->clinic_id);
        }


        $results = $query->get();

        if ($results->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No doctors found matching your search criteria'
            ], 404);
        }


          return response()->json([
            'success' => true,
            'data' => $results->map(function ($doctor) {
                return [
                    'id' => $doctor->id,
                    'name' => $doctor->user->full_name,
                    'specialty' => $doctor->specialty,
                    'clinic' => $doctor->clinic->name,
                    'rating' => $doctor->rating,
                    'profile_picture' => $doctor->user->getFileUrl('profile_picture')
                ];
            })
        ]);



        }



    // Global patient search
    public function searchPatients(Request $request)
    {
        $query = Patient::with('user');

        if ($request->has('name')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('first_name', 'like', '%' . $request->name . '%')
                    ->orWhere('last_name', 'like', '%' . $request->name . '%');
            });
        }

        if ($request->has('phone')) {
            $query->where('phone_number', 'like', '%' . $request->phone . '%');
        }

    $results = $query->get();

        if ($results->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No patients found matching your search criteria'
            ], 404);



        }
            return response()->json([
            'success' => true,
            'data' => $results->map(function ($patient) {
                return [
                    'id' => $patient->id,
                    'name' => $patient->user->full_name,
                    'phone' => $patient->phone_number,
                    'email' => $patient->user->email,
                    'profile_picture' => $patient->user->getFileUrl('profile_picture')
                ];
            })
        ]);



    }


    // Global secretary search
    public function searchSecretaries(Request $request)
    {
        $query = Secretary::with('user');

        if ($request->has('name')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('first_name', 'like', '%' . $request->name . '%')
                    ->orWhere('last_name', 'like', '%' . $request->name . '%');
            });
        }

    $results = $query->get();

        if ($results->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No secretaries found matching your search criteria'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $results->map(function ($secretary) {
                return [
                    'id' => $secretary->id,
                    'name' => $secretary->user->full_name,
                    'email' => $secretary->user->email,
                    'workdays' => $secretary->workdays,
                    'profile_picture' => $secretary->user->getFileUrl('profile_picture')
                ];
            })
        ]);


    }
}
