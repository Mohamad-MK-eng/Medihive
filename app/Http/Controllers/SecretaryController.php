<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\ClinicWallet;
use App\Models\ClinicWalletTransaction;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Secretary;
use App\Models\TimeSlot;
use App\Models\WalletTransaction;
use App\Notifications\AppointmentBooked;
use App\Notifications\DoctorAppointmentBooked;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Log;
use Notification;

class SecretaryController extends Controller
{





public function secretaryBookAppointment(Request $request)
{
    // Authorization check
    if (!Auth::user()->secretary) {
        return response()->json(['error' => 'Unauthorized'], 403);
    }

    // Validation
    $validated = $request->validate([
        'doctor_id' => 'required|exists:doctors,id',
        'slot_id' => 'required|exists:time_slots,id',
        'patient_id' => 'required|exists:patients,id',
        'amount' => 'required|numeric|min:0',
        'notes' => 'nullable|string',
    ]);

    return DB::transaction(function () use ($validated) {
        try {
            // Check slot availability
            $slot = TimeSlot::where('id', $validated['slot_id'])
                ->where('doctor_id', $validated['doctor_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($slot->is_booked) {
                return response()->json(['error' => 'Slot already booked'], 409);
            }

            // Get related models
            $doctor = Doctor::findOrFail($validated['doctor_id']);
            $patient = Patient::findOrFail($validated['patient_id']);

            // Fix: Properly format the appointment date
            $appointmentDate = Carbon::createFromFormat(
                'Y-m-d H:i:s',
                // Use only the date part from $slot->date and time from $slot->start_time
                Carbon::parse($slot->date)->format('Y-m-d') . ' ' . $slot->start_time
            );

            // Create appointment
            $appointment = Appointment::create([
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'clinic_id' => $doctor->clinic_id,
                'time_slot_id' => $slot->id,
                'appointment_date' => $appointmentDate,
                'status' => 'confirmed',
                'payment_status' => 'paid',
                'price' => $validated['amount'],
                'notes' => $validated['notes'] ?? null,
                'booked_by_secretary' => true,
            ]);

            // Mark slot as booked
            $slot->update(['is_booked' => true]);

            // Record payment
            $payment = Payment::create([
                'appointment_id' => $appointment->id,
                'patient_id' => $patient->id,
                'amount' => $validated['amount'],
                'method' => 'cash',
                'status' => 'paid',
                'secretary_id' => Auth::id(),
                'transaction_id' => 'CASH-' . now()->timestamp,
            ]);

            // Update clinic wallet
            $clinicWallet = ClinicWallet::firstOrCreate(['clinic_id' => $doctor->clinic_id]);
            $clinicWallet->increment('balance', $validated['amount']);

            ClinicWalletTransaction::create([
                'clinic_wallet_id' => $clinicWallet->id,
                'amount' => $validated['amount'],
                'type' => 'cash_payment',
                'reference' => 'APT-' . $appointment->id,
                'balance_before' => $clinicWallet->balance - $validated['amount'],
                'balance_after' => $clinicWallet->balance,
                'notes' => 'Secretary cash booking for appointment #' . $appointment->id,
            ]);

            return response()->json([
                'success' => true,
                'appointment_id' => $appointment->id,
                'payment_id' => $payment->id,
                'appointment_date' => $appointmentDate->format('Y-m-d H:i:s'),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Booking failed',
                'internal_error' => $e->getMessage(),
                'trace' => env('APP_DEBUG') ? $e->getTraceAsString() : null
            ], 500);
        }
    });
}


    // the cash payment

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
            'transaction_reference' => 'sometimes|string|max:255'
        ]);

        try {
            $appointment = $user->patient->appointments()
                ->findOrFail($validated['appointment_id']);

            $secretary = Secretary::first();

            $paymentData = [
                'appointment_id' => $appointment->id,
                'amount' => $validated['amount'],
                'method' => $validated['method'],
                'status' => 'paid',
                'patient_id' => $user->patient->id,
                'secretary_id' => $secretary->id ?? null,
                'transaction_reference' => $validated['transaction_reference'] ?? null
            ];

            $payment = Payment::create($paymentData);

            $totalPaid = $appointment->payments()->sum('amount');
            if ($appointment->price && $totalPaid >= $appointment->price) {
                $appointment->update(['payment_status' => 'paid']);
            }

            return response()->json([
                'payment' => $payment->load(['appointment']),
                'message' => 'Payment processed successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Payment failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }




    public function addToPatientWallet(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'amount' => 'required|numeric|min:0.01',
            'notes' => 'sometimes|string|max:255'
        ]);

        if (!Auth::user()->secretary) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return DB
            ::transaction(function () use ($validated) {
                $patient = Patient::find($validated['patient_id']);
                $secretary = Auth::user();

                $transaction = $patient->deposit(
                    $validated['amount'],
                    $validated['notes'] ?? 'Added by secretary',
                    $secretary->id
                );

                return response()->json([
                    'message' => 'Funds added successfully',
                    'new_balance' => $patient->fresh()->wallet_balance,
                    'transaction' => $transaction
                ]);
            });
    }








public function unblockPatient(Request $request)
{
    // Verify the authenticated user is a secretary
    if (!Auth::user()->hasRole('secretary')) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    $validated = $request->validate([
        'patient_id' => 'required|exists:patients,id',
    ]);

    $patient = Patient::findOrFail($validated['patient_id']);

    // Get all absent appointments
    $absentAppointments = $patient->appointments()
        ->where('status', 'absent')
        ->get();

    // Option 1: Change status to 'cancelled' (soft approach)
    foreach ($absentAppointments as $appointment) {
        $appointment->update([
            'status' => 'cancelled',
            'cancelled_by' => Auth::id(),
        ]);
    }
    return response()->json('message:block removed');
}


public function listBlockedPatients()
{
    // Get the threshold from config
    $threshold = config('app.absent_appointment_threshold', 3);

    // Get patients who meet or exceed the threshold
    $blockedPatients = Patient::withCount(['appointments as absent_count' => function($query) {
            $query->where('status', 'absent');
        }])
        ->having('absent_count', '>=', $threshold)
        ->with('user:id,first_name,last_name,email,phone')
        ->get();

    return response()->json([
        'blocked_patients' => $blockedPatients->map(function($patient) {
            return [
                'id' => $patient->id,
                'name' => $patient->user->first_name.' '.$patient->user->last_name,
                'email' => $patient->user->email,
                'phone' => $patient->user->phone,
                'absent_count' => $patient->absent_count
            ];
        })
    ]);
}



    public function getPatientWalletInfo($patientId)
    {
        $patient = Patient
            ::with(['user', 'walletTransactions' => function ($q) {
                $q->orderBy('created_at', 'desc')->limit(10);
            }])->findOrFail($patientId);

        return response()->json([
            'patient' => $patient,
            'wallet_balance' => $patient->wallet_balance,
            'wallet_activated' => !is_null($patient->wallet_activated_at),
            'recent_transactions' => $patient->walletTransactions
        ]);
    }
}
