<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Prescription;
use App\Models\Report;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Traits\HandlesFiles;
use Carbon\Carbon;
use DB;

class DoctorController extends Controller
{
    use HandlesFiles;


public function getProfile()
{
    try {
        $user = Auth::user(); // Get authenticated user

        // Check if user has a doctor profile
        if (!$user->doctor) {
            return response()->json([
                'error' => 'Doctor profile not found',
                'message' => 'User is not associated with a doctor profile'
            ], 404);
        }

        $doctor = $user->doctor;

        return response()->json([
            'name' => $user->first_name . ' ' . $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone_number,
            'date' => $user->created_at->format('d/m/Y'),
            'specialty' => $doctor->specialty,
            'consultation_fee' => $doctor->consultation_fee,
            'experience_years' => $doctor->experience_years
            // Add more fields as needed
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Failed to load profile',
            'message' => $e->getMessage()
        ], 500);
    }
}

public function getProfilePicture()
{
    $user = Auth::user();

    try {
        $path = $user->getFileUrl('profile_picture');
        $path = str_replace(asset('storage/'), '', $path);

        if (!Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'Profile picture file not found'], 404);
        }

        $fullPath = Storage::disk('public')->path($path);
        return response()->file($fullPath);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Error retrieving profile picture',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function uploadProfilePicture(Request $request)
{
    $request->validate([
        'profile_picture' => 'nullable|image|mimes:jpg,jpeg,png|max:3072'
    ]);

    $user = Auth::user();

    try {
        $uploaded = $user->uploadFile($request->file('profile_picture'), 'profile_picture');

        if (!$uploaded) {
            throw new \Exception('Failed to upload profile picture');
        }

        return response()->json([
            'success' => true,
            'profile_picture_url' => $user->getProfilePictureUrl(),
            'message' => 'Profile picture updated successfully'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to upload profile picture',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function updateProfile(Request $request)
{
    $user = Auth::user();

    $validator = Validator::make($request->all(), [
        'first_name' => 'sometimes|string|max:255',
        'last_name' => 'sometimes|string|max:255',

        'phone_number' => 'sometimes|string|max:20',
        'address' => 'sometimes|string',
        'profile_picture' => 'sometimes|image|mimes:jpg,jpeg,png|max:2048'
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    if ($request->hasFile('profile_picture')) {
        try {
            $uploaded = $user->uploadFile($request->file('profile_picture'), 'profile_picture');

            if (!$uploaded) {
                throw new \Exception('Failed to upload profile picture');
            }
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to upload picture',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    $user->update($validator->except(['profile_picture']));

    return response()->json([
        'message' => 'Profile updated successfully',
        'user' => [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone_number' => $user->phone_number,
            'address' => $user->address,
            'date_of_birth' => $user->date_of_birth,
            'gender' => $user->gender,
            'profile_picture_url' => $user->getProfilePictureUrl()
        ]
    ]);
}




    public function getSchedule()
    {
        $doctor = Auth::user()->doctor;

        if (!$doctor) {
            return response()->json(['message' => 'Doctor profile not found'], 404);
        }

        $schedules = DoctorSchedule::where('doctor_id', $doctor->id)
            ->get(['day', 'start_time', 'end_time']);

        return response()->json($schedules);
    }

    public function getAppointments(Request $request)
    {
        $doctor = Auth::user()->doctor;

        if (!$doctor) {
            return response()->json(['message' => 'Doctor profile not found'], 404);
        }

        $status = $request->query('status', 'upcoming');

        $query = Appointment::with(['patient.user', 'clinic'])
            ->where('doctor_id', $doctor->id);

        if ($status === 'upcoming') {
            $query->where('appointment_date', '>=', now())
                ->where('status', 'confirmed');
        } elseif ($status === 'past') {
            $query->where('appointment_date', '<', now());
        } elseif ($status === 'cancelled') {
            $query->where('status', 'cancelled');
        }

        $appointments = $query->orderBy('appointment_date', 'asc')
            ->paginate(10);

        return response()->json($appointments);
    }

    public function getTimeSlots(Request $request)
    {
        $doctor = Auth::user()->doctor;

        if (!$doctor) {
            return response()->json(['message' => 'Doctor profile not found'], 404);
        }

        $date = $request->query('date', now()->format('Y-m-d'));

        $timeSlots = TimeSlot::where('doctor_id', $doctor->id)
            ->where('date', $date)
            ->orderBy('start_time')
            ->get();

        return response()->json($timeSlots);
    }


    public function getDoctorScheduleInfo()
    {
        $doctor = Auth::user()->doctor;

        if (!$doctor) {
            return response()->json(['message' => 'Doctor profile not found'], 404);
        }

        // Get doctor's basic information
        $doctorInfo = [
            'name' => $doctor->user->first_name . ' ' . $doctor->user->last_name,
            'specialty' => $doctor->specialty,
            'experience_years' => $doctor->experience_years ?? 2,
            'rating' => $doctor->rating ?? 0,
            'consultation_fee' => number_format($doctor->consultation_fee ?? 100.00, 2),
            'bio' => $doctor->bio ?? "Lorem ipsum is simply dummy text of the printing and typesetting industry...",
        ];

        // Get all schedules ordered by day of week
        $schedules = $doctor->schedules()
            ->orderByRaw("FIELD(day, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday')")
            ->get();

        // Format working days and hours
        $workingDays = $schedules->map(function ($schedule) {
            return [
                'day' => ucfirst($schedule->day),
                'start_time' => Carbon::parse($schedule->start_time)->format('g:i A'),
                'end_time' => Carbon::parse($schedule->end_time)->format('g:i A')
            ];
        });

        // Create the formatted working hours string
        $daysAbbreviated = $workingDays->map(function ($day) {
            return substr($day['day'], 0, 3);
        })->implode('_');

        $firstSchedule = $workingDays->first();
        $workingHours = $daysAbbreviated . ' (' . $firstSchedule['start_time'] . ' _ ' . $firstSchedule['end_time'] . ')';

        return response()->json([
            'doctor' => $doctorInfo,
            'working_days' => $workingDays,
            'working_hours' => $workingHours,
            'consultation_fee' => $doctorInfo['consultation_fee']
        ]);
    }


    private function getFormattedAvailability(Doctor $doctor)
    {
        $schedules = $doctor->schedules()
            ->orderByRaw("FIELD(day, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday')")
            ->get();

        return $schedules->map(function ($schedule) {
            return [
                'day' => ucfirst($schedule->day),
                'start_time' => Carbon::parse($schedule->start_time)->format('g:i A'),
                'end_time' => Carbon::parse($schedule->end_time)->format('g:i A')
            ];
        });
    }

    private function getAppointmentStats(Doctor $doctor)
    {
        return [
            'total' => $doctor->appointments()->count(),
            'upcoming' => $doctor->appointments()
                ->where('appointment_date', '>=', now())
                ->whereIn('status', ['confirmed', 'pending'])
                ->count(),
            'completed' => $doctor->appointments()
                ->where('appointment_date', '<', now())
                ->where('status', 'completed')
                ->count()
        ];
    }


    public function getTopDoctors()
    {
        $topDoctors = Doctor::topRated()->get()->map(function ($doctor) {
            return [
                'id' => $doctor->id,
                'first_name' => $doctor->user->first_name, // Assuming name is in User model
                'last_name' => $doctor->user->last_name,
                'specialty' => $doctor->specialty,

                'experience_years' => $doctor->experience_years,
                'rate' => (float)$doctor->rating,
                'profile_picture_url' => $doctor->user->getProfilePictureUrl(), // Assuming user has profile picture
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $topDoctors
        ]);
    }



public function addPrescriptions(Request $request, $reportId)
{
    $doctor = Auth::user()->doctor;
    if (!$doctor) {
        return response()->json(['message' => 'Doctor profile not found'], 404);
    }

    $validated = $request->validate([
        'prescriptions' => 'nullable|array',
        'prescriptions.*.medication' => 'nullable|string|max:255',
        'prescriptions.*.dosage' => 'nullable|string|max:100',
        'prescriptions.*.frequency' => 'nullable|string|max:100',
        'prescriptions.*.instructions' => 'nullable|string',
        'prescriptions.*.is_completed' => 'sometimes|boolean',
    ]);

    $report = Report::whereHas('appointment', function($query) use ($doctor) {
            $query->where('doctor_id', $doctor->id);
        })
        ->where('id', $reportId)
        ->firstOrFail();

    return DB::transaction(function () use ($report, $validated) {
        $prescriptions = [];

        foreach ($validated['prescriptions'] as $prescriptionData) {
            $prescription = new Prescription([
                'report_id' => $report->id,
                'appointment_id' => $report->appointment_id,
                'medication' => $prescriptionData['medication'],
                'dosage' => $prescriptionData['dosage'],
                'frequency' => $prescriptionData['frequency'] ?? null,
                'instructions' => $prescriptionData['instructions'] ?? null,
                'is_completed' => $prescriptionData['is_completed'] ?? false,
                'issue_date' => now(),
            ]);
            $prescription->save();
            $prescriptions[] = $prescription;
        }

        return response()->json([
            'message' => 'Prescriptions added successfully',
            'prescriptions' => $prescriptions,
        ], 201);
    });
}

public function submitMedicalReport(Request $request, $appointmentId)
{
    $doctor = Auth::user()->doctor;
    if (!$doctor) {
        return response()->json(['message' => 'Doctor profile not found'], 404);
    }

    $appointment = Appointment::with(['clinic', 'doctor.user'])->where('doctor_id', $doctor->id)
        ->where('id', $appointmentId)
        ->firstOrFail();

    // Validate the request
    $validated = $request->validate([
        'title' => 'nullable|string|max:255',
        'content' => 'nullable|string|min:20',
        'prescriptions' => 'nullable|array',
        'prescriptions.*.medication' => 'nullable|string|max:255',
        'prescriptions.*.dosage' => 'nullable|string|max:100',
        'prescriptions.*.frequency' => 'nullable|string|max:100',
        'prescriptions.*.instructions' => 'nullable|string',    ]);

    return DB::transaction(function () use ($appointment, $validated) {
        // Create the report
        $report = Report::create([
            'appointment_id' => $appointment->id,
            'title' => $validated['title'],
            'content' => $validated['content']
        ]);

        // Add prescriptions
        foreach ($validated['prescriptions'] as $prescriptionData) {
            Prescription::create([
                'report_id' => $report->id,
                'appointment_id' => $appointment->id,
                'medication' => $prescriptionData['medication'],
                'dosage' => $prescriptionData['dosage'],
                'frequency' => $prescriptionData['frequency'],
                'instructions' => $prescriptionData['instructions'],
                'issue_date' => now()
            ]);
        }

        // Mark appointment as completed
        $appointment->update(['status' => 'completed']);

        // Format the response to exactly match your interface
        return response()->json([
            'success' => true,
            'message' => 'Medical report submitted successfully',
            'report' => [
                'date' => $appointment->appointment_date->format('Y-m-d h:i A'), // "2025-08-01 09:00 AM" format
                'clinic' => $appointment->clinic->name, // "Oncology"
                'doctor' => $appointment->doctor->user->name, // "John White" or null if not set
                'specialty' => $appointment->doctor->specialty, // "Cardiology"
                'title' => $report->title, // "Annual Checkup Report"
                'content' => $report->content, // Patient health details
                'prescriptions' => $report->prescriptions->map(function($prescription) {
                    return [
                        'medication' => $prescription->medication, // "Paracetamol"
                        'dosage' => $prescription->dosage, // "500mg"
                        'frequency' => $prescription->frequency, // "3x/day"
                        'instructions' => $prescription->instructions, // "After meal"
                    ];
                })->toArray()
            ]
        ]);
    });
}
}







