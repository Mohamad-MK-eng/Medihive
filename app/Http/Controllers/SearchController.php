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
            $query->where('name', 'like', '%'.$request->name.'%');
        }

        if ($request->has('location')) {
            $query->where('location', 'like', '%'.$request->location.'%');
        }

        return $query->withCount('doctors')->paginate(10);
    }

    // Global doctor search
    public function searchDoctors(Request $request)
    {
        $query = Doctor::with(['user', 'clinic', 'reviews']);

        if ($request->has('name')) {
            $query->whereHas('user', function($q) use ($request) {
                $q->where('first_name', 'like', '%'.$request->name.'%')
                  ->orWhere('last_name', 'like', '%'.$request->name.'%');
            });
        }

        if ($request->has('specialty')) {
            $query->where('specialty', 'like', '%'.$request->specialty.'%');
        }

        if ($request->has('clinic_id')) {
            $query->where('clinic_id', $request->clinic_id);
        }

        return $query->paginate(10);
    }

    // Global patient search
    public function searchPatients(Request $request)
    {
        $query = Patient::with('user');

        if ($request->has('name')) {
            $query->whereHas('user', function($q) use ($request) {
                $q->where('first_name', 'like', '%'.$request->name.'%')
                  ->orWhere('last_name', 'like', '%'.$request->name.'%');
            });
        }

        if ($request->has('phone')) {
            $query->where('phone_number', 'like', '%'.$request->phone.'%');
        }

        return $query->paginate(10);
    }

    // Global secretary search
    public function searchSecretaries(Request $request)
    {
        $query = Secretary::with('user');

        if ($request->has('name')) {
            $query->whereHas('user', function($q) use ($request) {
                $q->where('first_name', 'like', '%'.$request->name.'%')
                  ->orWhere('last_name', 'like', '%'.$request->name.'%');
            });
        }

        return $query->paginate(10);
    }
}
