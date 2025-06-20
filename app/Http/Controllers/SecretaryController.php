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
