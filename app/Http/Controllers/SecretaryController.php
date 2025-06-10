<?php
namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Secretary;
use App\Models\WalletTransaction;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SecretaryController extends Controller
{









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
            'rescheduled_by' => Auth::id(),
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
                    'type' => 'fee',
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
















    public function getPatientWalletInfo($patientId)
    {
        $patient = Patient
        ::with(['user', 'walletTransactions' => function($q) {
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
