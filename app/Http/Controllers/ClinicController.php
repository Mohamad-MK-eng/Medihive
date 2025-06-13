<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use App\Models\Specialty;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ClinicController extends Controller
{
    // Get all clinics with their images
    public function index()
    {
        $clinics = Clinic::all()->map(function($clinic) {
            return [
                'id' => $clinic->id,
                'name' => $clinic->name,
                'location' => $clinic->location,
                'icon_url' => $clinic->getIconUrl()
            ];
        });

        return response()->json($clinics);
    }





// for a single clinic

 public function show($id)
    {
        $clinic = Clinic::findOrFail($id);

        return response()->json([
            'id' => $clinic->id,
            'name' => $clinic->name,
            'location' => $clinic->location,
            'description' => $clinic->description,
            'icon_url' => $clinic->getIconUrl(),
            'doctors_count' => $clinic->doctors->count()
        ]);
    }



    // modify for a single clinic
    public function getIconUrl($id)
    {
        $clinic = Clinic::findOrFail($id);

        if (!$clinic->image_path) {
            return response()->json(['message' => 'No image set for this clinic'], 404);
        }

        try {
            $filename = basename($clinic->description_picture);
            $relativePath = 'clinic_images/' . $filename;

            if (!Storage::disk('public')->exists($relativePath)) {
                return response()->json(['message' => 'Image file not found'], 404);
            }

            $fullPath = storage_path('app/public/' . $relativePath);
            return response()->file($fullPath);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving clinic image',
                'error' => $e->getMessage()
            ], 500);
        }
    }


}






// try
/*
// Get all specialties for a clinic
$clinic = Clinic::find(1);
$specialties = $clinic->specialties;

// Get all clinics offering a specialty
$specialty = Specialty::find(1);
$clinics = $specialty->clinics;

// Get doctors for a specialty
$doctors = $specialty->doctors;

// Get specialty for a doctor
$doctor = Doctor::find(1);
$specialty = $doctor->specialty;


*/

