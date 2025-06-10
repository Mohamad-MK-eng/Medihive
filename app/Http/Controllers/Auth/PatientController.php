<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\Review;
use App\Models\Secretary;
use App\Models\TimeSlot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use DB;
use Storage;

class PatientController extends Controller
{



public function getProfile()
{
    $patient = Auth::user()->patient;
    if (!$patient) {
        return response()->json(['message' => 'Patient profile not found'], 404);
    }

    $profile = $patient->load([
        'user',
        'appointments.doctor.user',
        'prescriptions.medications',
        'payments',
        'notifications',
        'walletTransactions'
    ])->toArray();

    // Add profile picture data
    $profile['profile_picture'] = [
        'url' => $patient->getProfilePictureUrl(),
        'exists' => !empty($patient->profile_picture)
    ];

    // Add other profile data
    $profile['wallet_balance'] = $patient->wallet_balance;
    $profile['wallet_activated'] = !is_null($patient->wallet_activated_at);
    $profile['medical_history'] = $patient->getMedicalHistory();

    return response()->json($profile);
}

public function updateProfile(Request $request)
{
    $user = Auth::user();
    $patient = $user->patient;

    $validator = Validator::make($request->all(), [
        'first_name' => 'sometimes|string|max:255',
        'last_name' => 'sometimes|string|max:255',
        'phone_number' => 'sometimes|string|max:20',
        'address' => 'sometimes|string|nullable',
        'date_of_birth' => 'sometimes|date',
        'gender' => 'sometimes|string|in:male,female,other',
        'blood_type' => 'sometimes|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
        'chronic_conditions' => 'nullable|array',
        'emergency_contact' => 'sometimes|string|max:255',
        'profile_picture' => 'sometimes|image|mimes:jpg,jpeg,png|max:2048'
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    // Handle profile picture update if present
    if ($request->hasFile('profile_picture')) {
        try {
            $patient->uploadProfilePicture($request->file('profile_picture'));
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to upload picture',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // Update user data
    $user->update($request->only(['first_name', 'last_name']));

    // Update patient data
    $patient->update($validator->validated());

    return response()->json([
        'patient' => $patient->fresh()->load('user'),
        'profile_picture_url' => $patient->getProfilePictureUrl(),
        'message' => 'Profile updated successfully'
    ]);
}
















// 1. Get clinics with images
public function getClinics()
{
    $clinics = Clinic::select('id', 'name', 'location', 'opening_time', 'closing_time')
        ->withCount('doctors')
        ->get()
        ->map(function($clinic) {
            return [
                'id' => $clinic->id,
                'name' => $clinic->name,
                'location' => $clinic->location,
                'opening_time' => $clinic->opening_time,
                'closing_time' => $clinic->closing_time,
                'doctors_count' => $clinic->doctors_count,
                'image_url' => $clinic->getClinicImageUrl()
              ];
        });

    return response()->json($clinics);
}

// 2. Get doctors for a clinic with profile pictures
public function getClinicDoctors($clinicId)
{
    $doctors = Doctor::where('clinic_id', $clinicId)
        ->with(['user:id,first_name,last_name,profile_picture', 'specialties'])
        ->get()
        ->map(function($doctor) {
            return [
                'id' => $doctor->id,
                'first_name' => $doctor->user->first_name,
                'last_name' => $doctor->user->last_name,
                'specialty' => $doctor->specialty,
                'profile_picture_url' => $doctor->user->getProfilePictureUrl(), // Ensure this method exists
            ];
        });

    return response()->json($doctors);
}

// 3. Get full doctor details
public function getDoctorDetails($doctorId)
{
    $doctor = Doctor::with([
            'user:id,first_name,last_name,email,phone_number,profile_picture',
            'specialties',
            'qualifications',
            'clinic:id,name,location'
        ])
        ->findOrFail($doctorId);

    return response()->json([
        'id' => $doctor->id,
        'first_name' => $doctor->user->first_name,
        'last_name' => $doctor->user->last_name,
        'email' => $doctor->user->email,
        'phone' => $doctor->user->phone_number,
        'specialty' => $doctor->specialty,
        'profile_picture_url' => $doctor->user->getProfilePictureUrl(),
        'bio' => $doctor->bio,
        'experience_years' => $doctor->experience_years,
        'qualifications' => $doctor->qualifications,
        'clinic' => $doctor->clinic,
        'available_slots' => $doctor->timeSlots()->where('is_booked', false)->count(),
    ]);
}







public function getClinicDoctorsWithSlots($clinicId, Request $request)
{
    $request->validate([
        'date' => 'sometimes|date'
    ]);

    // Default to 7 days from now to match your seeder
    $date = $request->input('date')
        ? Carbon::parse($request->date)->format('Y-m-d')
        : now()->addDays(7)->format('Y-m-d');

    $doctors = Doctor::with(['user:id,first_name,last_name', 'timeSlots' => function($query) use ($date) {
            $query->where('date', $date)
                  ->where('is_booked', false)
                  ->orderBy('start_time');
        }])
        ->where('clinic_id', $clinicId)
        ->get()
        ->map(function($doctor) use ($date) {
            return [
                'id' => $doctor->id,
                'name' => $doctor->user->first_name . ' ' . $doctor->user->last_name,
                'specialty' => $doctor->specialty,
                'available_slots' => $doctor->timeSlots->map(function($slot) {
                    return [
                        'id' => $slot->id,
                        'start_time' => $slot->formatted_start_time,
                        'end_time' => $slot->formatted_end_time,
                        'date' => $slot->date->format('Y-m-d')
                    ];
                }),
                '_debug' => [
                    'doctor_id' => $doctor->id,
                    'date_queried' => $date,
                    'slots_count' => $doctor->timeSlots->count()
                ]
            ];
        });

    return response()->json($doctors);
}









    public function getAppointments(Request $request)
    {
        $patient = Auth::user()->patient;

        $appointments = $patient->appointments()
            ->with(['doctor.user:id,first_name,last_name', 'clinic:id,name'])
            ->when($request->has('status'), function($query) use ($request) {
                return $query->where('status', $request->status);
            })
            ->when($request->has('upcoming'), function($query) {
                return $query->where('appointment_date', '>=', now());
            })
            ->orderBy('appointment_date', 'desc')
            ->paginate(10);

        return response()->json([
            'data' => $appointments->items(),
            'meta' => [
                'current_page' => $appointments->currentPage(),
                'total' => $appointments->total(),
                'per_page' => $appointments->perPage(),
                'last_page' => $appointments->lastPage()
            ]
        ]);
    }






















//tested successfully
public function getAvailableSlots($doctorId, $date) {
    $slots = TimeSlot::where('doctor_id', $doctorId)
        ->where('date', $date)
        ->where('is_booked', false)
        ->get();

    return response()->json($slots);
}











    public function updateAppointment(Request $request, $id)
    {
        $validated = $request->validate([
            'appointment_date' => 'sometimes|date|after:now',
            'reason' => 'sometimes|string|max:500|nullable',
            'status' => 'sometimes|in:pending,confirmed,cancelled,completed',
            'cancellation_reason' => 'required_if:status,cancelled|string|max:255|nullable'
        ]);

        $appointment = Auth::user()->patient->appointments()->findOrFail($id);

        if ($appointment->status === 'completed') {
            return response()->json(['message' => 'Cannot modify completed appointments'], 403);
        }

        $appointment->update($validated);

        if ($request->has('status') && $request->status === 'cancelled') {
            $appointment->patient->notifications()->create([
                'title' => 'Appointment Cancelled',
                'body' => "Your appointment on {$appointment->appointment_date->format('M j, Y g:i A')} has been cancelled.",
                'type' => 'appointment_update'
            ]);
        }

        return response()->json($appointment->load('doctor.user'));
    }







    public function cancelAppointment(Request $request, $id)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        $appointment = Auth::user()->patient->appointments()
            ->where('status', '!=', 'completed')
            ->findOrFail($id);

        $hoursBeforeCancellation = 24;
        if (now()->diffInHours($appointment->appointment_date) < $hoursBeforeCancellation) {
            return response()->json([
                'message' => "Appointments must be cancelled at least {$hoursBeforeCancellation} hours in advance"
            ], 403);
        }

        $appointment->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $validated['reason']
        ]); // بدي عالج قصة الخصم

        /*  لا تقيم الكومنت لا تقيم الكومنت لا تقيم الكومنت
        $appointment->patient->notifications()->create([
            'title' => 'Appointment Cancelled',
            'body' => "Your appointment on {$appointment->appointment_date->format('M j, Y g:i A')} has been cancelled. Reason: {$validated['reason']}",
            'type' => 'appointment_update'
        ]);
*/
        return response()->json(['message' => 'Appointment cancelled successfully']);
    }











public function getWalletTransactions()
{
    $patient = Auth::user()->patient;
    if (!$patient) {
        return response()->json(['message' => 'Patient profile not found'], 404);
    }

    $transactions = $patient->walletTransactions()
        ->orderBy('created_at', 'desc')
        ->paginate(10);

    return response()->json($transactions);
}









 // tested successfully

    public function getMedicalHistory(Request $request)
{
    $patient = Auth::user()->patient;
    if (!$patient) {
        return response()->json(['message' => 'Patient profile not found'], 404);
    }


     return $request->user()->patient->appointments()
        ->with(['prescription', 'medicalNotes', 'doctor.user', 'clinic'])
        ->where('appointment_date', '<=', now())
        ->orderBy('appointment_date', 'desc')
        ->get()
        ->map(function ($appointment) {
            return [
                'id' => $appointment->id,
                'date' => $appointment->appointment_date,
                'clinic' => $appointment->clinic->name,
                'doctor' => $appointment->doctor->user->name,
                'diagnosis' => $appointment->diagnosis,
                'notes' => $appointment->notes,
                'prescription' => $appointment->prescription,
            ];
        });
/*
    $history = $patient->appointments()
        ->with(['doctor.user:id,first_name,last_name', 'prescriptions.medications'])
        ->where('status', 'completed')
        ->when($request->has('from'), function($query) use ($request) {
            return $query->whereDate('appointment_date', '>=', $request->from);
        })
        ->when($request->has('to'), function($query) use ($request) {
            return $query->whereDate('appointment_date', '<=', $request->to);
        })
        ->orderBy('appointment_date', 'desc')
        ->get();

    return response()->json($history);

    */
}

















//  not tested yet
    public function uploadDocument(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,png|max:5120',
            'type' => 'required|in:lab_report,prescription,scan',
            'appointment_id' => 'sometimes|exists:appointments,id'
        ]);

        $path = $request->file('file')->store('patient_documents');

        $document = Auth::user()->patient->documents()->create([
            'file_path' => $path,
            'type' => $validated['type'],
            'appointment_id' => $validated['appointment_id'] ?? null,
            'uploaded_at' => now()
        ]);

        return response()->json($document, 201);
    }





// not tested  yet
    public function getWalletBalance()
{
    $patient = Auth::user()->patient;
    if (!$patient) {
        return response()->json(['message' => 'Patient profile not found'], 404);
    }

    return response()->json([
        'balance' => $patient->wallet_balance,
        'wallet_activated' => !is_null($patient->wallet_activated_at)
    ]);
}











// not tested yet
public function getPrescriptions()
{
    $patient = Auth::user()->patient;
    if (!$patient) {
        return response()->json(['message' => 'Patient profile not found'], 404);
    }

    $prescriptions = $patient->prescriptions()
        ->with(['medications', 'appointment.doctor.user'])
        ->orderBy('created_at', 'desc')
        ->get();

    return response()->json($prescriptions);
}








// not tested yet
public function getPaymentHistory()
{
    $user = Auth::user();

    if (!$user->patient) {
        return response()->json([
            'message' => 'Patient profile not found',
            'payments' => []
        ], 200);
    }

    $payments = $user->payments()
        ->with('appointment.doctor.user')
        ->orderBy('created_at', 'desc')
        ->paginate(10);

    return response()->json($payments);
}
















//  until another time
public function submitRating(Request $request)
{
    $validated = $request->validate([
        'doctor_id' => 'required|exists:doctors,id',
        'appointment_id' => 'required|exists:appointments,id',
        'rating' => 'required|integer|between:1,5',
        'comment' => 'nullable|string|max:500'
    ]);

    $review = Review::create([
        'patient_id' => $request->user()->patient->id,
        ...$validated
    ]);

    return response()->json($review, 201);
}








//too complex the real one is in the appointment controller
/*
    public function createAppointment(Request $request)
    {
        $validated = $request->validate([
            'doctor_id' => 'required|exists:doctors,id',
            'clinic_id' => 'required|exists:clinics,id',
            'appointment_date' => 'required|date|after:now',
            'reason' => 'sometimes|string|max:500',
            'notes' => 'sometimes|string|nullable'
        ]);

        try {
            $appointmentDate = Carbon::parse($validated['appointment_date']);
            $dayOfWeek = strtolower($appointmentDate->englishDayOfWeek);
            $appointmentTime = $appointmentDate->format('H:i:s');
            $dateOnly = $appointmentDate->format('Y-m-d');

            // checking if the doctor exists at this clinic
            $doctor = Doctor::with('schedules')
                ->where('id', $validated['doctor_id'])
                ->where('clinic_id', $validated['clinic_id'])
                ->firstOrFail();

            // Check if doctor has any schedules
            if ($doctor->schedules->isEmpty()) {
                return response()->json([
                    'message' => 'This doctor has no availability scheduled. Please contact the clinic.'
                ], 400);
            }



            $clinic = Clinic::findOrFail($validated['clinic_id']);
            $clinicOpen = Carbon::parse($clinic->opening_time);
            $clinicClose = Carbon::parse($clinic->closing_time);
            $requestedTime = Carbon::parse($appointmentTime);

            if ($requestedTime->lt($clinicOpen) || $requestedTime->gt($clinicClose)) {
                return response()->json([
                    'message' => 'Clinic is closed at this time. Open hours: ' .
                                $clinic->opening_time . ' to ' . $clinic->closing_time
                ], 400);
            }





            // Check specific day availability
            $daySchedule = $doctor->schedules->firstWhere('day', $dayOfWeek);

            if (!$daySchedule) {
                $availableDays = $doctor->schedules->pluck('day')
                    ->unique()
                    ->map(fn($day) => ucfirst($day))
                    ->implode(', ');

                return response()->json([
                    'message' => 'Doctor not available on ' . ucfirst($dayOfWeek) . '.',
                    'available_days' => $availableDays ?: 'No days scheduled'
                ], 400);
            }

            // Check time slot (single day ) availability
            $scheduleStart = Carbon::parse($daySchedule->start_time);
            $scheduleEnd = Carbon::parse($daySchedule->end_time);

            if ($requestedTime->lt($scheduleStart) || $requestedTime->gt($scheduleEnd)) {
                return response()->json([
                    'message' => 'Doctor availability on ' . ucfirst($dayOfWeek) . ': ' .
                                $daySchedule->start_time . ' to ' . $daySchedule->end_time
                ], 400);
            }

            // existing appointments has  limit to 3 per day
            $existingAppointmentsCount = Appointment::where('doctor_id', $doctor->id)
                ->whereDate('appointment_date', $dateOnly)
                ->count();




            if ($existingAppointmentsCount >= 3) {
                return response()->json([
                    'message' => 'Doctor has reached maximum appointments for this day (3 appointments max)'
                ], 409);
            }




            // Check for specific time slot conflicts (within 30 minutes)
            $conflictingAppointment = Appointment::where('doctor_id', $doctor->id)
                ->whereBetween('appointment_date', [
                    $appointmentDate->copy()->subMinutes(30),
                    $appointmentDate->copy()->addMinutes(30)
                ])
                ->exists();

            if ($conflictingAppointment) {
                return response()->json([
                    'message' => 'Time slot already booked or too close to another appointment'
                ], 409);
            }




            $patient = Auth::user()->patient;

            if (!$patient) {
                return response()->json([
                    'message' => 'Patient profile not found'
                ], 404);
            }

            // Create the appointment
            $appointmentData = [
                'patient_id' => $patient->id,
                'doctor_id' => $validated['doctor_id'],
                'clinic_id' => $validated['clinic_id'],
                'appointment_date' => $validated['appointment_date'],
                'reason' => $validated['reason'],
                'notes' => $validated['notes'] ?? null,
                'status' => 'pending'
            ];

            $appointment = Appointment::create($appointmentData);

            return response()->json([
                'appointment' => $appointment,
                'message' => 'Appointment booked successfully'  // && بدي يرجعلي معلومات عن الطبيب ك تابع get profile
                // clinic name , doctor name ,

            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Appointment booking failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

*/



}










/* second try :

public function bookFromAvailableSlot(Request $request)
{
    $validated = $request->validate([
        'slot_id' => 'required|exists:time_slots,id',
        'reason' => 'nullable|string|max:500',
        'notes' => 'nullable|string'
    ]);

    return DB::transaction(function () use ($validated) {
        $slot = TimeSlot::findOrFail($validated['slot_id']);

        // Check if slot is still available
        if ($slot->is_booked) {
            return response()->json(['message' => 'This time slot is no longer available'], 409);
        }

        // Mark slot as booked
        $slot->update(['is_booked' => true]);

        // Create appointment
        $appointment = Appointment::create([
            'patient_id' => Auth::user()->patient->id,
            'doctor_id' => $slot->doctor_id,
            'time_slot_id' => $slot->id,
            'appointment_date' => $slot->date->format('Y-m-d') . ' ' . $slot->start_time,
            'end_time' => $slot->end_time,
            'reason' => $validated['reason'],
            'notes' => $validated['notes'] ?? null,
            'status' => 'confirmed'
        ]);

        return response()->json([
            'appointment' => $appointment->load('doctor.user', 'clinic'),
            'message' => 'Appointment booked successfully'
        ], 201);
    });
}
*/

