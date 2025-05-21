<?php
namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\WalletTransaction;
use Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Charge;
use Stripe\Exception\ApiErrorException;

class PaymentController extends Controller
{
   public function recordPayment(Request $request)
{
    $validated = $this->validatePaymentRequest($request);

    return DB::transaction(function () use ($validated) {
        /** @var \App\Models\Appointment $appointment */
        $appointment = Appointment::with('patient')->findOrFail($validated['appointment_id']);

       switch ($validated['method']) {
                case 'cash':
                    return $this->handleCashPayment($appointment, $validated);
                case 'wallet':
                    return $this->handleWalletPayment($appointment, $validated);
                case 'card':
                    return $this->handleCardPayment($appointment, $validated);
                case 'insurance':
                    return $this->handleInsurancePayment($appointment, $validated);
                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid payment method'
                    ], 400);
            }
        });
    }

    protected function validatePaymentRequest(Request $request)
    {
        return $request->validate([
            'appointment_id' => 'required|exists:appointments,id',
            'amount' => 'required|numeric|min:0',
            'method' => 'required|in:cash,wallet,card,insurance',
              'service_id' => 'required|exists:services,id',
            'card_details' => 'required_if:method,card|array',
            'card_details.number' => 'required_if:method,card',
            'card_details.expiry' => 'required_if:method,card',
            'card_details.cvc' => 'required_if:method,card',
            'wallet_pin' => 'required_if:method,wallet|digits:4',
        ]);
    }

    protected function handleCashPayment(Appointment $appointment, array $data)
    {
        $payment = Payment::create([
            'appointment_id' => $appointment->id,
            'patient_id' => $appointment->patient_id,
            'service_id' => $appointment['service_id'],
            'amount' => $data['amount'],
            'method' => 'cash',
            'status' => 'pending',
            'transaction_id' => 'CASH-'.now()->format('YmdHis')
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Please complete cash payment at clinic reception',
            'payment' => $payment
        ]);
    }

    protected function handleWalletPayment(Appointment $appointment, array $data)
    {
        $patient = $appointment->patient;

        if (!$patient->wallet_activated_at) {
            return response()->json([
                'success' => false,
                'message' => 'Wallet not activated'
            ], 400);
        }

        if (!Hash::check($data['wallet_pin'], $patient->wallet_pin)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid wallet PIN'
            ], 400);
        }

        if ($patient->wallet_balance < $data['amount']) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient wallet balance'
            ], 400);
        }

        return DB::transaction(function () use ($patient, $appointment, $data) {
            $transaction = WalletTransaction::create([
                'patient_id' => $patient->id,
                'amount' => $data['amount'],
                'type' => 'payment',
                'reference' => 'APT-'.$appointment->id,
                'balance_before' => $patient->wallet_balance,
                'balance_after' => $patient->wallet_balance - $data['amount'],
                'notes' => 'Payment for appointment #'.$appointment->id
            ]);

            $patient->decrement('wallet_balance', $data['amount']);

            $payment = Payment::create([
                'appointment_id' => $appointment->id,
                'patient_id' => $patient->id,
                'amount' => $data['amount'],
                'method' => 'wallet',
                'status' => 'completed',
                'transaction_id' => 'WALLET-'.$transaction->id
            ]);

            $appointment->update(['payment_status' => 'paid']);

            return response()->json([
                'success' => true,
                'message' => 'Payment completed via wallet',
                'payment' => $payment,
                'wallet_balance' => $patient->fresh()->wallet_balance,
                'transaction' => $transaction
            ]);
        });
    }

    protected function handleCardPayment(Appointment $appointment, array $data)
    {
        try {
            Stripe::setApiKey(env('STRIPE_SECRET'));

            $paymentIntent = PaymentIntent::create([
                'amount' => $data['amount'] * 100,
                'currency' => 'usd',
                'payment_method_types' => ['card'],
                'description' => 'Appointment #'.$appointment->id,
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
                'message' => 'Payment processing failed: '.$e->getMessage()
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
            'transaction_id' => 'INS-'.now()->format('YmdHis')
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Insurance claim submitted',
            'payment' => $payment
        ]);
    }
}
