<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\WalletTransaction;
use App\Notifications\PaymentConfirmationNotification;
use App\Notifications\PaymentConfirmed;
use Auth;
use Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Notification;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Charge;
use Stripe\Exception\ApiErrorException;

class PaymentController extends Controller
{

    const MAX_PIN_ATTEMPTS = 3;

    const WALLET_NOT_ACTIVATED = 'wallet_not_activated';
    const INVALID_PIN = 'invalid_pin';
    const INSUFFICIENT_BALANCE = 'insufficient_balance';



public function PaymentInfo(Request $request)
{
    $validated = $request->validate([
        'appointment_id' => 'required|exists:appointments,id'
    ]);

    $appointment = Appointment::with(['patient', 'payments'])->findOrFail($validated['appointment_id']);

    // Ensure payments is treated as a collection
    $payments = $appointment->payments()->get();

    return response()->json([
        'success' => true,
        'appointment' => [
            'id' => $appointment->id,
            'patient' => $appointment->patient ? $appointment->patient->only(['id', 'name']) : null,
            'amount_due' => $appointment->amount_due ?? 0,
            'payment_status' => $appointment->payment_status,
            'existing_payments' => $payments->map(function($payment) {
                return [
                    'method' => $payment->method,
                    'amount' => $payment->amount,
                    'status' => $payment->status,
                    'created_at' => $payment->created_at
                ];
            })->all() // Convert to array
        ],
        'payment_options' => [
            'wallet' => $appointment->patient && $appointment->patient->wallet_activated_at !== null,
        ],
        'wallet_balance' => $appointment->patient ? ($appointment->patient->wallet_balance ?? 0) : 0
    ]);
}





protected function validatePaymentRequest(Request $request)
{
    return $request->validate([
        'appointment_id' => 'required|exists:appointments,id',
        'method' => 'required|in:cash,wallet,card,insurance',
        'amount' => 'required|numeric|min:0',
        'wallet_pin' => 'required_if:method,wallet|digits:4',
        // For card payments (optional parameters)
        'card_last_four' => 'sometimes|required_if:method,card|digits:4',
        'card_brand' => 'sometimes|required_if:method,card|string',
        // For insurance (optional)
        'insurance_provider' => 'sometimes|required_if:method,insurance|string'
    ]);
}

    protected function handleCashPayment(Appointment $appointment, array $data)
    {
        $payment = Payment::create([
            'appointment_id' => $appointment->id,
            'patient_id' => $appointment->patient_id,
            'amount' => $data['amount'],
            'method' => 'cash',
            'status' => 'pending',
            'transaction_id' => 'CASH-' . now()->format('YmdHis')
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Please complete cash payment at clinic reception',
            'payment' => $payment
        ]);
    }

    public function handleWalletPayment(Appointment $appointment, array $data)
    {


        $patient = Auth::user()->patient;

        if (!$patient || !$patient->user) {
            return response()->json([
                'success' => false,
                'message' => 'Patient user account not found'
            ], 404);
        }


        if (!$patient->wallet_activated_at) {
            return response()->json([
                'success' => false,
                'error_code' => self::WALLET_NOT_ACTIVATED,
                'message' => 'Please activate your wallet before making payments',
            ], 400);
        }

        if (!Hash::check($data['wallet_pin'], $patient->wallet_pin)) {
            $attemptsLeft = self::MAX_PIN_ATTEMPTS - ($patient->pin_attempts + 1);

            $patient->increment('pin_attempts');
            if ($patient->pin_attempts >= self::MAX_PIN_ATTEMPTS) {
                $patient->update(['wallet_locked_until' => now()->addHours(2)]);
                return response()->json([
                    'success' => false,
                    'error_code' => 'too_many_attempts',
                    'message' => 'Wallet temporarily locked. Try again after 2 hours.',
                    'unlock_time' => now()->addHours(2)->toIso8601String()
                ], 429);
            }



            return response()->json([
                'success' => false,
                'error_code' => self::INVALID_PIN,
                'message' => 'Incorrect PIN. ',
                'attempts_remaining' => $attemptsLeft,
                'security_tip' => 'Never share your PIN with anyone'
            ], 401);
        }






        if ($patient->wallet_balance < $data['amount']) {

            $shortfall = $data['amount'] - $patient->wallet_balance;


            return response()->json([
                'success' => false,
                'error_code' => self::INSUFFICIENT_BALANCE,
                'message' => 'Your wallet balance is insufficient.',
                'current_balance' => number_format($patient->wallet_balance, 2),
                'required_amount' => number_format($data['amount'], 2),
                'shortfall' => number_format($shortfall, 2),
            ], 400);
        }

        return DB::transaction(function () use ($patient, $appointment, $data) {
            $transaction = WalletTransaction::create([
                'patient_id' => $patient->id,
                'amount' => $data['amount'],
                'type' => 'payment',
                'reference' => 'APT-' . $appointment->id,
                'balance_before' => $patient->wallet_balance,
                'balance_after' => $patient->wallet_balance - $data['amount'],
                'notes' => 'Payment for appointment #' . $appointment->id
            ]);

            $patient->decrement('wallet_balance', $data['amount']);

            $payment = Payment::create([
                'appointment_id' => $appointment->id,
                'patient_id' => $patient->id,
                'amount' => $data['amount'],
                'method' => 'wallet',
                'status' => 'paid',
                'transaction_id' => 'WALLET-' . $transaction->id
            ]);
            $patient = $payment->patient;

            $appointment->update(['payment_status' => 'paid']);

            $patient->user->notify(new PaymentConfirmationNotification($payment));


            return response()->json([
                'success' => true,
                'message' => 'Payment completed via wallet',
                'payment' => $payment,
                'wallet_balance' => $patient->fresh()->wallet_balance,
                'transaction' => $transaction,


            ]);
        });
    }

    /*huge response :
    return response()->json([
    'success' => true,
    'message' => 'Payment processed successfully',
    'payment_id' => $payment->id,
    'transaction' => [
        'reference' => $transaction->reference,
        'timestamp' => now()->toIso8601String(),
        'amount' => [
            'value' => $payment->amount,
            'currency' => 'USD',
            'formatted' => '$'.number_format($payment->amount, 2)
        ],
        'balance' => [
            'previous' => [
                'value' => $transaction->balance_before,
                'formatted' => '$'.number_format($transaction->balance_before, 2)
            ],
            'current' => [
                'value' => $transaction->balance_after,
                'formatted' => '$'.number_format($transaction->balance_after, 2)
            ]
        ]
    ],
    'appointment' => [
        'id' => $appointment->id,
        'date' => $appointment->appointment_date->format('c'),
        'doctor' => $appointment->doctor->user->name,
        'clinic' => $appointment->clinic->name
    ],
    'receipt_url' => url('/receipts/'.$payment->id),
    'next_steps' => [
        'view_appointment' => url('/appointments/'.$appointment->id),
        'download_receipt' => url('/receipts/'.$payment->id.'/pdf')
    ]
]);



*/

    protected function handleCardPayment(Appointment $appointment, array $data)
    {
        try {
            Stripe::setApiKey(env('STRIPE_SECRET'));

            $paymentIntent = PaymentIntent::create([
                'amount' => $data['amount'] * 100,
                'currency' => 'usd',
                'payment_method_types' => ['card'],
                'description' => 'Appointment #' . $appointment->id,
            ]);

            $payment = Payment::create([
                'appointment_id' => $appointment->id,
                'patient_id' => $appointment->patient_id,
                'amount' => $data['amount'],
                'method' => 'card',
                'status' => 'requires_payment_method',
                'transaction_id' => $paymentIntent->id,
                'metadata' => [
                    'client_secret' => $paymentIntent->client_secret
                ]
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment intent created',
                'payment' => $payment,
                'client_secret' => $paymentIntent->client_secret,
                'requires_action' => true
            ]);
        } catch (ApiErrorException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment processing failed: ' . $e->getMessage()
            ], 400);
        }
    }

    protected function handleInsurancePayment(Appointment $appointment, array $data)
    {
        if (!$appointment->patient->insurance_coverage) {
            return response()->json([
                'success' => false,
                'message' => 'Patient has no active insurance'
            ], 400);
        }

        $payment = Payment::create([
            'appointment_id' => $appointment->id,
            'patient_id' => $appointment->patient_id,
            'amount' => $data['amount'],
            'method' => 'insurance',
            'status' => 'pending',
            'transaction_id' => 'INS-' . now()->format('YmdHis')
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Insurance claim submitted',
            'payment' => $payment
        ]);
    }
}
