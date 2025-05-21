<?php
namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\Payment;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class WalletController extends Controller
{
    public function getBalance()
    {
        $patient = Auth::user()->patient;
        return response()->json([
            'balance' => $patient->wallet_balance,
            'wallet_activated' => !is_null($patient->wallet_activated_at)
        ]);
    }

    public function addFunds(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'amount' => 'required|numeric|min:0.01',
            'notes' => 'sometimes|string|max:255'
        ]);

        if (!Auth::user()->hasRole('secretary') && !Auth::user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return DB::transaction(function () use ($validated) {
            $patient = Patient::find($validated['patient_id']);
            $admin = Auth::user();

            $transaction = $patient->deposit(
                $validated['amount'],
                $validated['notes'] ?? 'Added by staff',
                $admin->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Funds added successfully',
                'transaction' => $transaction,
                'new_balance' => $patient->fresh()->wallet_balance
            ]);
        });
    }

    public function getTransactions($patientId = null)
    {
        // If patientId is provided, verify the requester has permission
        if ($patientId) {
            if (!Auth::user()->hasRole('admin') &&
                (!Auth::user()->patient || Auth::user()->patient->id != $patientId)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $patient = Patient::findOrFail($patientId);
        } else {
            // Get transactions for current patient
            $patient = Auth::user()->patient;
            if (!$patient) {
                return response()->json(['message' => 'Patient profile not found'], 404);
            }
        }

        $transactions = $patient->walletTransactions()
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json($transactions);
    }

    public function setupWallet(Request $request)
    {
        $validated = $request->validate([
            'pin' => 'required|digits:4|confirmed',
            'pin_confirmation' => 'required'
        ]);

        $user = Auth::user();
        if (!$user->patient) {
            return response()->json([
                'success' => false,
                'message' => 'Patient record not found'
            ], 404);
        }

        $patient = $user->patient;

        if ($patient->wallet_pin) {
            return response()->json([
                'success' => false,
                'message' => 'Wallet already activated'
            ], 400);
        }

        $patient->update([
            'wallet_pin' => Hash::make($validated['pin']),
            'wallet_activated_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Wallet activated successfully'
        ]);
    }

    public function transferToClinic(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'appointment_id' => 'required|exists:appointments,id',
              'service_id' => 'required|exists:services,id',
            'pin' => 'required|digits:4'
        ]);

        $patient = Auth::user()->patient;
        if (!$patient) {
            return response()->json(['message' => 'Patient profile not found'], 404);
        }

        if (!Hash::check($validated['pin'], $patient->wallet_pin)) {
            return response()->json(['message' => 'Invalid wallet PIN'], 401);
        }

        if ($patient->wallet_balance < $validated['amount']) {
            return response()->json(['message' => 'Insufficient balance'], 400);
        }

        return DB::transaction(function () use ($patient, $validated) {
            $transaction = WalletTransaction::create([
                'patient_id' => $patient->id,
                'amount' => $validated['amount'],
                'type' => 'payment',
                'reference' => 'APT-'.$validated['appointment_id'],
                'balance_before' => $patient->wallet_balance,
                'balance_after' => $patient->wallet_balance - $validated['amount'],
                'notes' => 'Payment for appointment #'.$validated['appointment_id']
            ]);

            $patient->decrement('wallet_balance', $validated['amount']);

            $payment = Payment::create([
                'appointment_id' => $validated['appointment_id'],
                'patient_id' => $patient->id,
                'service_id' => $validated['service_id'],
                'amount' => $validated['amount'],
                'method' => 'wallet',
                'status' => 'completed',
                'transaction_id' => 'WALLET-'.$transaction->id
            ]);

            return response()->json([
                'message' => 'Payment successful',
                'new_balance' => $patient->fresh()->wallet_balance,
                'payment' => $payment
            ]);
        });
    }
}
