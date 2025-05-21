<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\Payment;
use App\Models\Prescription;
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
        return response()->json($patient->load('user'));
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
        'blood_type' => 'sometimes|string|nullable',
        'emergency_contact' => 'sometimes|string|max:255'
        // Removed profile_picture from here
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    // Update user
    if ($request->has('first_name')) $user->first_name = $request->first_name;
    if ($request->has('last_name')) $user->last_name = $request->last_name;
    $user->save();

    // Update patient
    $patient->update($validator->validated());

    return response()->json([
        'patient' => $patient->fresh()->load('user'),
        'message' => 'Profile updated successfully'
    ]);
}















public function updateProfilePicture(Request $request)
{
    $request->validate([
        'profile_picture' => 'required|image|mimes:jpg,jpeg|max:2048'
    ]);

    $patient = Auth::user()->patient;

    if (!$patient) {
        return response()->json(['message' => 'Patient profile not found'], 404);
    }

    if ($path = $patient->uploadProfilePicture($request->file('profile_picture'))) {
        return response()->json([
            'success' => true,
            'url' => $patient->getProfilePictureUrl(),
            'message' => 'Profile picture updated successfully'
        ]);
    }

    return response()->json([
        'error' => 'Invalid image file. Only JPG/JPEG files under 2MB are allowed'
    ], 400);
}
















/* Get methods : : get clinics ,
get clinics doctors ,
get doctor schedules ,
get appointments ,
 get notifications : temporary disabled ,
get prescriptions ,
get medical history ,
get payment history


*/




    public function getClinics()
    {
        $clinics = Clinic::select('id', 'name', 'location', 'opening_time', 'closing_time')
            ->withCount('doctors')
            ->get();

        return response()->json($clinics);
    }

    public function getClinicDoctors($clinicId)
    {
        $doctors = Doctor::where('clinic_id', $clinicId)
            ->with(['user:id,first_name,last_name', 'schedules'])
            ->get(['id', 'user_id', 'specialty', 'clinic_id']);

        return response()->json($doctors);
    }









// Get clinics with specialties
public function getClinicsWithSpecialties()
{
    return Clinic::with('doctors.user:id,first_name,last_name')
        ->get(['id', 'name', 'location']);
}

// Get doctors with available slots for a clinic






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







// Book appointment from available slot
public function bookFromAvailableSlot(Request $request)
{
    $validated = $request->validate([
        'doctor_id' => 'required|exists:doctors,id',
        'date' => 'required|date|after:now',
        'slot_start' => 'required|date_format:g:i A',
        'slot_end' => 'required|date_format:g:i A',
        'reason' => 'required|string|max:500'
    ]);

    $appointmentDate = Carbon::createFromFormat(
        'Y-m-d g:i A',
        $validated['date'] . ' ' . $validated['slot_start']
    );

    $appointment = Appointment::create([
        'patient_id' => Auth::user()->patient->id,
        'doctor_id' => $validated['doctor_id'],
        'appointment_date' => $appointmentDate,
        'reason' => $validated['reason'],
        'status' => 'confirmed'
    ]);

    return response()->json([
        'message' => 'Appointment booked successfully',
        'appointment' => $appointment
    ]);
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























// Get available slots
public function getAvailableSlots($doctorId, $date) {
    $slots = TimeSlot::where('doctor_id', $doctorId)
        ->where('date', $date)
        ->where('is_booked', false)
        ->get();

    return response()->json($slots);
}

// Book appointment
public function bookAppointment(Request $request) {
    $validated = $request->validate([
        'slot_id' => 'required|exists:time_slots,id',
        'reason' => 'required|string|max:500'
    ]);

    DB::transaction(function () use ($validated) {
        $slot = TimeSlot::find($validated['slot_id']);
        $slot->update(['is_booked' => true]);

        Appointment::create([
            'patient_id' => Auth::id(),
            'doctor_id' => $slot->doctor_id,
            'appointment_date' => $slot->date,
            'start_time' => $slot->start_time,
            'end_time' => $slot->end_time,
            'status' => 'confirmed',
            'reason' => $validated['reason']
        ]);
    });

    return response()->json(['message' => 'Appointment booked successfully']);
}












    public function createAppointment(Request $request)
    {
        $validated = $request->validate([
            'doctor_id' => 'required|exists:doctors,id',
            'clinic_id' => 'required|exists:clinics,id',
            'appointment_date' => 'required|date|after:now',
            'reason' => 'required|string|max:500',
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
                'message' => 'Appointment booked successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Appointment booking failed',
                'error' => $e->getMessage()
            ], 500);
        }
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
        ]);

        /*  لا تقيم الكومنت لا تقيم الكومنت لا تقيم الكومنت
        $appointment->patient->notifications()->create([
            'title' => 'Appointment Cancelled',
            'body' => "Your appointment on {$appointment->appointment_date->format('M j, Y g:i A')} has been cancelled. Reason: {$validated['reason']}",
            'type' => 'appointment_update'
        ]);
*/
        return response()->json(['message' => 'Appointment cancelled successfully']);
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



// Add to PatientController
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
}




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


public function makePayment(Request $request)
{
    $user = Auth::user();

    if (!$user) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    if (!$user->patient) {
        return response()->json(['message' => 'Patient profile not found'], 404);
    }

    $validated = $request->validate([
        'appointment_id' => 'required|exists:appointments,id',
        'amount' => 'required|numeric|min:0',
        'method' => 'required|in:cash,card,insurance,transfer',
        'service_id' => 'required|exists:services,id',
        'transaction_reference' => 'sometimes|string|max:255'
    ]);

    try {
        $appointment = $user->patient->appointments()
            ->findOrFail($validated['appointment_id']);

        $secretary = Secretary::first(); // the first secretary at the moment

        $paymentData = [
            'appointment_id' => $appointment->id,
            'amount' => $validated['amount'],
            'method' => $validated['method'],
            'status' => 'paid',
            'patient_id' => $user->patient->id,
            'service_id' => $validated['service_id'],
            'secretary_id' => $secretary->id ?? null,
            'transaction_reference' => $validated['transaction_reference'] ?? null
        ];

        $payment = Payment::create($paymentData);

        // Update appointment payment status
        $totalPaid = $appointment->payments()->sum('amount');
        if ($appointment->price && $totalPaid >= $appointment->price) {
            $appointment->update(['payment_status' => 'paid']);
        }

        return response()->json([
            'payment' => $payment->load(['appointment', 'service']),
            'message' => 'Payment processed successfully'
        ], 201);

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Payment failed',
            'error' => $e->getMessage()
        ], 500);
    }
}






public function getNotifications()
{
    $notifications = Auth::user()->patient->notifications()
        ->orderBy('created_at', 'desc')
        ->paginate(15);

    return response()->json($notifications);
}

public function markNotificationAsRead($id)
{
    $notification = Auth::user()->patient->notifications()
        ->findOrFail($id);

    if (!$notification->read_at) {
        $notification->update(['read_at' => now()]);
        return response()->json(['message' => 'Notification marked as read']);
    }

    return response()->json(['message' => 'Notification was already read']);
}

public function markAllNotificationsAsRead()
{
    Auth::user()->patient->notifications()
        ->whereNull('read_at')
        ->update(['read_at' => now()]);

    return response()->json(['message' => 'All notifications marked as read']);
}









}


