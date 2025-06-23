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
use App\Traits\HandlesFiles;

class PatientController extends Controller
{
    use HandlesFiles;



    public function getProfile()
    {
        $user = Auth::user();
        $patient = $user->patient;

        if (!$patient) {
            return response()->json(['message' => 'Patient profile not found'], 404);
        }

        $profile = [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
             'email' => $user->email,
            'phone_number' => $patient->phone_number,
            'address' => $patient->address,
            'date_of_birth' => $patient->date_of_birth,
            'gender' => $patient->gender,
            'blood_type' => $patient->blood_type,
            'chronic_conditions' => $patient->chronic_conditions,
            'profile_picture_url' => $user->getProfilePictureUrl(),
            'wallet_balance' => $patient->wallet_balance,
            'wallet_pin' => $patient->wallet_pin
        ];

        return response()->json([
            'message' => ' Patient Profile Fetched successfully',
            'patient' =>$profile]);
    }






    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        $patient = $user->patient;
        if (!$patient) {
            return response()->json(['message' => 'Patient profile not found'], 404);
        }


        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'phone_number' => 'sometimes|string|max:20',
            'address' => 'sometimes|string|nullable',
            'date_of_birth' => 'sometimes|date',
            'gender' => 'sometimes|string|in:male,female,other',
            'blood_type' => 'sometimes|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
            'chronic_conditions' => 'nullable|array',
            'profile_picture' => 'sometimes|image|mimes:jpg,jpeg,png|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Handle profile picture update if present
        if ($request->hasFile('profile_picture')) {
            try {
                $patient->user->uploadFile($request->file('profile_picture'), 'profile_picture');
            } catch (\Exception $e) {
                return response()->json([
                    'error' => 'Failed to upload picture',
                    'message' => $e->getMessage()
                ], 500);
            }
        }



        $validated = $validator->validated();




        if (isset($validated['first_name']) || isset($validated['last_name'])) {
            $user->update([
                'first_name' => $validated['first_name'] ?? $user->first_name,
                'last_name' => $validated['last_name'] ?? $user->last_name
            ]);
        }

        // Remove user-related fields from patient data
        $patientData = collect($validated)->except(['first_name', 'last_name', 'profile_picture'])->all();

        // Update patient data
        $patient->update($patientData);

        return response()->json([
            'patient' => [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
             'email' => $user->email,
            'phone_number' => $patient->phone_number,
            'address' => $patient->address,
            'date_of_birth' => $patient->date_of_birth,
            'gender' => $patient->gender,
            'blood_type' => $patient->blood_type,
            'chronic_conditions' => $patient->chronic_conditions,
            'profile_picture_url' => $user->getProfilePictureUrl(),
            'wallet_balance' => $patient->wallet_balance,
            'wallet_pin' => $patient->wallet_pin
            ],
            'message' => 'Profile updated successfully'
        ]);
    }


    public function getProfilePicture()
    {
        $patient = Auth::user()->patient;

        if (!$patient) {
            return response()->json(['message' => 'Patient profile not found'], 404);
        }

        if (!$patient->user->profile_picture) {
            return response()->json(['message' => 'No profile picture set'], 404);
        }

        try {
            // Get the stored path
            $path = $patient->user->profile_picture;

            // Remove any 'storage/' prefix if present
            $path = str_replace('storage/', '', $path);

            // Check if file exists
            if (!Storage::disk('public')->exists($path)) {
                return response()->json(['message' => 'Profile picture file not found'], 404);
            }

            // Get the full filesystem path
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
            'profile_picture' => 'required|image|mimes:jpg,jpeg,png|max:3072' // 3MB max
        ]);

        $patient = Auth::user()->patient;

        if (!$patient) {
            return response()->json(['message' => 'Patient profile not found'], 404);
        }

        try {
            // Upload the file using the HandlesFiles trait
            $uploaded = $patient->user->uploadFile($request->file('profile_picture'), 'profile_picture');

            if (!$uploaded) {
                throw new \Exception('Failed to upload profile picture');
            }

            return response()->json([
                'success' => true,
                'profile_picture_url' => $patient->user->getProfilePictureUrl(),
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




    // 2. Get doctors for a clinic with profile pictures



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










    public function getPrescriptions()
    {
        $patient = Auth::user()->patient;
        if (!$patient) {
            return response()->json(['message' => 'Patient profile not found'], 404);
        }

        // Alternative 1: If prescriptions are linked via appointments
        $prescriptions = $patient->appointments()
            ->with(['prescription']) // Ensure 'prescription' is a defined relationship in Appointment model
            ->whereHas('prescription') // Only appointments with prescriptions
            ->get()
            ->pluck('prescription') // Extract prescriptions
            ->filter(); // Remove null entries (if any)

        // Alternative 2: If prescriptions have a direct patient_id column
        // $prescriptions = Prescription::where('patient_id', $patient->id)
        //     ->orderBy('created_at', 'desc')
        //     ->get();

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
