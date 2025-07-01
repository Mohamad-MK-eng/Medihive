<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Specialty;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ClinicController extends Controller
{
    // Get all clinics with their images
    public function index()
    {
        $clinics = Clinic::all()->map(function ($clinic) {
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















    // In AppointmentController.php

    public function getClinicDoctors(Clinic $clinic)
    {
        // Eager load the necessary relationships
        $doctors = $clinic->doctors()
            ->with(['user', 'reviews', 'schedules'])
            ->get();

        // Format the response

        // مبدئيا هيك تمام بس انت حاطط شرط حلو تبع is active هاد في حال بدو يستقيل ويصفي معايناته القديمة وما بده حدا جديد
        // بتعمل فلترة على اللي is active ? true
        $formattedDoctors = $doctors->map(function ($doctor) {
            $user = $doctor->user;

            return [
                'id' => $doctor->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'specialty' => $doctor->specialty,
                'experience_years' => $doctor->experience_years,
                'profile_picture_url' => $user->getProfilePictureUrl(),
                'rate' => $doctor->rating,
                // حلوة الفكرة
                'is_active' => $user->is_active,
                /* 'schedules' => $doctor->schedules->map(function ($schedule) {
                    return [
                        'day' => $schedule->day,
                    ];
                }) */
            ];
        });

        return response()->json([
            'doctors' => $formattedDoctors
        ]);
    }


    public function getClinicDoctorsWithSlots(Clinic $clinic, Request $request)
    {
        $request->validate([
            'date' => 'required|date_format:Y-m-d'
        ]);

        $doctors = $clinic->doctors()
            ->with(['user', 'schedules', 'timeSlots' => function ($query) use ($request) {
                $query->where('date', $request->date)
                    ->where('is_booked', false);
            }])
            ->withAvg('reviews', 'rating')
            ->get()
            ->map(function ($doctor) {
                return [
                    'id' => $doctor->id,
                    'first_name' => $doctor->user->first_name,
                    'last_name' => $doctor->user->last_name,
                    'specialty' => $doctor->specialty,
                    'profile_picture_url' => $doctor->user->getProfilePictureUrl(),
                    'experience_years' => $doctor->experience_years,
                    'rating' => $doctor->reviews_avg_rating ? (float) $doctor->reviews_avg_rating : 0,
                    'available_slots' => $doctor->timeSlots->map(function ($slot) {
                        return [
                            'id' => $slot->id,
                            'start_time' => $slot->start_time,
                            'end_time' => $slot->end_time
                        ];
                    })
                ];
            });

        return response()->json($doctors);
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
