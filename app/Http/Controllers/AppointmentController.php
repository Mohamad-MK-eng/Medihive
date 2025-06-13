<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\TimeSlot;
use App\Models\WalletTransaction;
use App\Services\AppointmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AppointmentController extends Controller
{
    protected $appointmentService;

    public function __construct(AppointmentService $appointmentService)
    {
        $this->appointmentService = $appointmentService;
    }

    public function bookAppointment(Request $request)
    {
        // Get authenticated user's patient record
        $patient = Auth::user()->patient; // assumes hasOne relationship

        if (!$patient) {
            return response()->json(['error' => 'Authenticated user is not a patient'], 403);
        }

        $validated = $request->validate([
            'doctor_id'    => 'required|exists:doctors,id',
            'slot_id'      => 'required|exists:time_slots,id',
            'reason'       => 'required|string|max:500',
            'notes'        => 'nullable|string',
            'document_id'  => 'nullable|exists:documents,id',
        ]);

        return DB::transaction(function () use ($validated, $patient) {
            $slot = TimeSlot::where('id', $validated['slot_id'])
                ->where('doctor_id', $validated['doctor_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($slot->is_booked) {
                return response()->json(['error' => 'This time slot has already been booked'], 409);
            }

            $doctor = Doctor::findOrFail($validated['doctor_id']);

            $slot->update(['is_booked' => true]);

            $appointment = Appointment::create([
                'patient_id'       => $patient->id,
                'doctor_id'        => $validated['doctor_id'],
                'clinic_id'        => $doctor->clinic_id,
                'time_slot_id'     => $slot->id,
                'appointment_date' => Carbon::parse($slot->date->format('Y-m-d') . ' ' . $slot->start_time),
                'status'           => 'confirmed',
                'document_id'      => $validated['document_id'] ?? null,
                'reason'           => $validated['reason'],
                'notes'            => $validated['notes'] ?? null,
            ]);

            try {
                $appointment->patient->notifications()->create([
                    'title' => 'Appointment Confirmed',
                    'body'  => "Your appointment with Dr. {$doctor->user->last_name} on {$slot->date->format('M j, Y')} at {$slot->start_time} has been confirmed",
                    'type'  => 'appointment_confirmation'
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to create notification', [
                    'error'          => $e->getMessage(),
                    'appointment_id' => $appointment->id
                ]);
            }

            return response()->json([
                'appointment' => $appointment->load(['doctor.user', 'clinic']),
                'message'     => 'Appointment booked successfully'
            ]);
        });
    }























    public function getClinicDoctors($clinicId)
{
    $doctors = Doctor::where('clinic_id', $clinicId)
        ->with(['user:id,first_name,last_name,profile_picture'])
        ->get()
        ->map(function($doctor) {
            return [
                'id' => $doctor->id,
                'first_name' => $doctor->user->first_name,
                'last_name' => $doctor->user->last_name,
                'specialty' => $doctor->specialty, // Using the direct field
                'profile_picture_url' => $doctor->user->profile_picture
                    ? asset('storage/'.$doctor->user->profile_picture)
                    : null,
            ];
        });

    return response()->json($doctors);
}

// 3. Get full doctor details
public function getDoctorDetails($doctorId)
{
    $doctor = Doctor::with([
            'user:id,first_name,last_name,email,profile_picture',
            'clinic:id,name' // removed location from here
        ])
        ->findOrFail($doctorId);

    return response()->json([
        'id' => $doctor->id,
        'first_name' => $doctor->user->first_name,
        'last_name' => $doctor->user->last_name,
        'email' => $doctor->user->email,
        'specialty' => $doctor->specialty,
        //'profile_picture_url' => $doctor->user->getProfilePictureUrl(),
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
    try {
        $patient = Auth::user()->patient;
        if (!$patient) {
            return response()->json(['message' => 'Patient profile not found'], 404);
        }

        $appointment = $patient->appointments()->findOrFail($id);

        // Updated validation to match request field names
        $validated = $request->validate([
            'doctor_id' => 'sometimes|exists:doctors,id',
            'time_slot_id' => 'sometimes|exists:time_slots,id', // Changed from slot_id
            'reason' => 'sometimes|string|max:500|nullable',
        ], [
            'doctor_id.exists' => 'The selected doctor does not exist',
            'time_slot_id.exists' => 'The selected time slot does not exist' // Updated
        ]);

        // Manual field updates - simplified since names now match
        if (isset($validated['doctor_id'])) {
            $appointment->doctor_id = $validated['doctor_id'];
        }

        if (isset($validated['time_slot_id'])) {
            $appointment->time_slot_id = $validated['time_slot_id'];
        }

        if (array_key_exists('reason', $validated)) {
            $appointment->reason = $validated['reason'];
        }

        if (!$appointment->save()) {
            return response()->json(['message' => 'Failed to save changes'], 500);
        }

        return response()->json($appointment->fresh()->load('doctor.user'));

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Update failed',
            'error' => $e->getMessage()
        ], 500);
    }
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





















    public function rescheduleAppointment(Request $request, $id)
    {
        $validated = $request->validate([
            'new_date' => 'required|date|after:now',
            'reason' => 'sometimes|string|nullable'
        ]);

        if (!Auth::user()->secretary) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $appointment = Appointment::findOrFail($id);

        if ($appointment->status === 'cancelled') {
            return response()->json(['message' => 'Cannot reschedule cancelled appointments'], 400);
        }

        $conflict = Appointment::where('doctor_id', $appointment->doctor_id)
            ->where('appointment_date', $validated['new_date'])
            ->exists();

        if ($conflict) {
            return response()->json(['message' => 'Doctor not available at this time'], 409);
        }

        $originalDate = $appointment->appointment_date;

        $appointment->update([
            'appointment_date' => $validated['new_date'],
            'previous_date' => $originalDate,
            'reschedule_reason' => $validated['reason'] ?? null
        ]);

        return response()->json([
            'message' => 'Appointment rescheduled successfully',
            'appointment' => $appointment->load(['patient.user', 'doctor.user'])
        ]);
    }






    public function processRefund(Request $request, $appointmentId)
    {
        $validated = $request->validate([
            'refund_amount' => 'required|numeric|min:0.01',
            'cancellation_fee' => 'sometimes|numeric|min:0',
            'notes' => 'sometimes|string|max:255'
        ]);

        if (!Auth::user()->secretary) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $appointment = Appointment::findOrFail($appointmentId);
        $patient = $appointment->patient;

        return DB::transaction(function () use ($appointment, $patient, $validated) {
            $transaction = WalletTransaction::create([
                'patient_id' => $patient->id,
                'admin_id' => Auth::id(),
                'amount' => $validated['refund_amount'],
                'type' => 'refund',
                'reference' => 'REF-'.$appointment->id,
                'balance_before' => $patient->wallet_balance,
                'balance_after' => $patient->wallet_balance + $validated['refund_amount'],
                'notes' => $validated['notes'] ?? 'Refund for appointment #'.$appointment->id
            ]);

            $patient->increment('wallet_balance', $validated['refund_amount']);

            if (isset($validated['cancellation_fee'])){

                WalletTransaction::create([
                    'patient_id' => $patient->id,
                    'admin_id' => Auth::id(),
                    'amount' => $validated['cancellation_fee'],
                    'type' => 'payment',
                    'reference' => 'FEE-'.$appointment->id,
                    'balance_before' => $patient->wallet_balance + $validated['refund_amount'],
                    'balance_after' => $patient->wallet_balance + $validated['refund_amount'] - $validated['cancellation_fee'],
                    'notes' => 'Cancellation fee for appointment #'.$appointment->id
                ]);

                $patient->decrement('wallet_balance', $validated['cancellation_fee']);
            }

            return response()->json([
                'message' => 'Refund processed successfully',
                'new_balance' => $patient->fresh()->wallet_balance,
                'refund_amount' => $validated['refund_amount'],
                'cancellation_fee' => $validated['cancellation_fee'] ?? 0
            ]);
        });
    }


}
