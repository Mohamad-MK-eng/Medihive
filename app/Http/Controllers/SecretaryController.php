<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
<<<<<<< HEAD
=======
use App\Models\Clinic;
use App\Models\ClinicWallet;
use App\Models\ClinicWalletTransaction;
use App\Models\Doctor;
>>>>>>> 0990b1cb7a8421c1b47e2ac2e468979376332b80
use App\Models\MedicalCenterWallet;
use App\Models\MedicalCenterWalletTransaction;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Secretary;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Validator;

class SecretaryController extends Controller
{


<<<<<<< HEAD




   public function makePayment(Request $request)
=======
    public function makePayment(Request $request)
>>>>>>> 0990b1cb7a8421c1b47e2ac2e468979376332b80
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
            'transaction_reference' => $validated['transaction_reference'] ?? null,
<<<<<<< HEAD
            'medical_center_wallet' => true // Mark as going to medical center
=======
            'medical_center_wallet' => true
>>>>>>> 0990b1cb7a8421c1b47e2ac2e468979376332b80
        ];

        $payment = Payment::create($paymentData);

<<<<<<< HEAD
        // Add to medical center wallet for cash payments
=======
>>>>>>> 0990b1cb7a8421c1b47e2ac2e468979376332b80
        if (in_array($validated['method'], ['cash', 'card', 'transfer'])) {
            $medicalCenterWallet = MedicalCenterWallet::firstOrCreate([], ['balance' => 0]);
            $medicalCenterWallet->increment('balance', $validated['amount']);

            MedicalCenterWalletTransaction::create([
                'medical_center_wallet_id' => $medicalCenterWallet->id,
                'clinic_id' => $appointment->clinic_id,
                'amount' => $validated['amount'],
                'type' => 'payment',
                'reference' => 'CASH-' . $payment->id,
                'balance_before' => $medicalCenterWallet->balance - $validated['amount'],
                'balance_after' => $medicalCenterWallet->balance,
                'notes' => 'Cash payment for appointment #' . $appointment->id
            ]);
<<<<<<< HEAD
        }

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


/*
    public function addToPatientWallet(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'amount' => 'required|numeric|min:0.01',
            'notes' => 'sometimes|string|max:255'
        ]);

        if (!Auth::user()->secretary) {
            return response()->json(['message' => 'Unauthorized'], 403);
=======
>>>>>>> 0990b1cb7a8421c1b47e2ac2e468979376332b80
        }

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
        'notes' => 'nullable|string|max:255'
    ]);

    if (!Auth::user()->secretary) {
        return response()->json([
            'status' => 'unauthorized',
            'message' => 'Unauthorized access'
        ], 403);
    }
*/

<<<<<<< HEAD
public function addToPatientWallet(Request $request)
{
    $validated = $request->validate([
        'patient_id' => 'required|exists:patients,id',
        'amount' => 'required|numeric|min:0.01',
        'notes' => 'nullable|string|max:255'
    ]);

    // Authorization check
    if (!Auth::user()->secretary) {
        return response()->json([
            'status' => 'unauthorized',
            'message' => 'Unauthorized access'
        ], 403);
    }

    $patient = Patient::findOrFail($validated['patient_id']);

    // Check wallet activation first
=======
    $patient = Patient::findOrFail($validated['patient_id']);

>>>>>>> 0990b1cb7a8421c1b47e2ac2e468979376332b80
    if (!$patient->wallet_pin || !$patient->wallet_activated_at) {
        return response()->json([
            'status' => 'wallet_not_activated',
            'message' => 'Cannot add funds - wallet is not activated',
            'current_balance' => $patient->wallet_balance
        ], 200);
    }
<<<<<<< HEAD

    // Only process deposit if wallet is activated
    $result = $patient->deposit(
        $validated['amount'],
        $validated['notes'] ?? 'Added by secretary',
        Auth::user()->id
    );

    // Simplify the success response since we've already validated
    return response()->json([
        'status' => 'success',
        'message' => 'Funds added successfully',
        'transaction' => $result['transaction'],
        'new_balance' => $result['new_balance']
    ]);
}
=======
>>>>>>> 0990b1cb7a8421c1b47e2ac2e468979376332b80

    $result = $patient->deposit(
        $validated['amount'],
        $validated['notes'] ?? 'Added by secretary',
        Auth::user()->id
    );

    return response()->json([
        'status' => 'success',
        'message' => 'Funds added successfully',
        'transaction' => $result['transaction'],
        'new_balance' => $result['new_balance']
    ]);
}


public function unblockPatient(Request $request)
{
    if (!Auth::user()->hasRole('secretary')) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    $validated = $request->validate([
        'patient_id' => 'required|exists:patients,id',
    ]);

    $patient = Patient::findOrFail($validated['patient_id']);


    $absentAppointments = $patient->appointments()
        ->where('status', 'absent')
        ->get();

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
    $threshold = config('app.absent_appointment_threshold', 3);

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


<<<<<<< HEAD






public function getAppointments(Request $request)
{
    // Verify secretary
=======
public function getAppointments(Request $request)
{
>>>>>>> 0990b1cb7a8421c1b47e2ac2e468979376332b80
    if (!Auth::user()->secretary) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    $type = $request->query('type', 'upcoming');
    $perPage = $request->query('per_page', 10);
    $clinicId = $request->query('clinic_id');
    $doctorId = $request->query('doctor_id');

    $query = Appointment::with([
        'patient.user:id,first_name,last_name',
        'doctor' => function($query) {
            $query->withTrashed()->with(['user' => function($q) {
                $q->withTrashed()->select('id', 'first_name', 'last_name');
            }]);
        },
        'clinic:id,name',
        'payments'
    ]);

<<<<<<< HEAD
    // Apply filters
=======
>>>>>>> 0990b1cb7a8421c1b47e2ac2e468979376332b80
    if ($clinicId) {
        $query->where('clinic_id', $clinicId);
    }
    if ($doctorId) {
        $query->where('doctor_id', $doctorId);
    }

    // Filter by appointment type
    switch ($type) {
        case 'upcoming':
            $query->where('status', 'confirmed')
                ->where('appointment_date', '>=', now());
            break;
        case 'completed':
            $query->where('status', 'completed');
            break;
        case 'cancelled':
            $query->where('status', 'cancelled');
            break;
        case 'absent':
            $query->where('status', 'absent');
            break;
        default:
            return response()->json(['message' => 'Invalid appointment type'], 400);
    }

    $appointments = $query->orderBy('appointment_date', 'desc')
        ->paginate($perPage);

    return response()->json([
        'data' => $appointments->items(),
        'meta' => [
            'current_page' => $appointments->currentPage(),
            'per_page' => $appointments->perPage(),
            'total' => $appointments->total(),
        ]
    ]);
}


<<<<<<< HEAD

/*





=======
>>>>>>> 0990b1cb7a8421c1b47e2ac2e468979376332b80
public function createPatient(Request $request)
{
    if (!Auth::user()->secretary) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    $validated = $request->validate([
        'first_name' => 'required|string|max:255',
        'last_name' => 'required|string|max:255',
        'email' => 'required|string|email|max:255|unique:users',
        'phone' => 'required|string|max:20',
        'gender' => 'required|in:male,female,other',
        'date_of_birth' => 'required|date',
        'address' => 'nullable|string',
        '_type' => 'nullable|string|max:3',
    ]);

    try {
        DB::beginTransaction();

<<<<<<< HEAD
        // Get or create patient role (same as register method)
=======
>>>>>>> 0990b1cb7a8421c1b47e2ac2e468979376332b80
        $patientRole = Role::firstOrCreate(
            ['name' => 'patient'],
            ['description' => 'Patient user']
        );

<<<<<<< HEAD
        // Create user with role_id
=======
>>>>>>> 0990b1cb7a8421c1b47e2ac2e468979376332b80
        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'gender' => $validated['gender'],
            'password' => bcrypt('temporary_password'),
<<<<<<< HEAD
            'role_id' => $patientRole->id, // Add this line
        ]);

        // Create patient
=======
            'role_id' => $patientRole->id,
        ]);

>>>>>>> 0990b1cb7a8421c1b47e2ac2e468979376332b80
        $patient = Patient::create([
            'user_id' => $user->id,
            'date_of_birth' => $validated['date_of_birth'],
            'address' => $validated['address'],
            'blood_type' => $validated['blood_type'],
        ]);

        DB::commit();

        return response()->json([
            'message' => 'Patient created successfully',
            'patient' => $patient->load('user')
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'Failed to create patient',
            'error' => $e->getMessage()
        ], 500);
    }
}


<<<<<<< HEAD
*/

=======
// cash method here alaa
public function bookAppointment(Request $request)
{
    if (!Auth::user()->secretary) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    $validated = $request->validate([
        'patient_id' => 'required|exists:patients,id',
        'doctor_id' => 'required|exists:doctors,id',
        'clinic_id' => 'required|exists:clinics,id',
        'time_slot_id' => 'required|exists:time_slots,id',
        'appointment_date' => 'required|date|after:now',
        'reason' => 'nullable|string',
        'price' => 'required|numeric|min:0',
        'payment_method' => 'required|in:cash,card,insurance,wallet',
    ]);

    try {
        DB::beginTransaction();

        $slot = TimeSlot::where('id', $validated['time_slot_id'])
            ->where('doctor_id', $validated['doctor_id'])
            ->lockForUpdate()
            ->firstOrFail();

        if ($slot->is_booked) {
            $existingAppointment = Appointment::where('time_slot_id', $slot->id)
                ->whereIn('status', ['confirmed', 'completed'])
                ->first();

            if ($existingAppointment) {
                return response()->json(['error' => 'This time slot has already been booked'], 409);
            } else {
                $slot->update(['is_booked' => false]);
            }
        }


 $doctorExists = Doctor::withTrashed()->where('id', $request->doctor_id)->exists();

    if (!$doctorExists) {
        return response()->json([
            'error' => 'doctor_not_found',
            'message' => 'The selected doctor is not available for appointments'
        ], 422);
    }


        $appointment = Appointment::create([
            'patient_id' => $validated['patient_id'],
            'doctor_id' => $validated['doctor_id'],
            'clinic_id' => $validated['clinic_id'],
            'time_slot_id' => $validated['time_slot_id'],
            'appointment_date' => $validated['appointment_date'],
            'reason' => $validated['reason'],
            'price' => $validated['price'],
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        $slot->update(['is_booked' => true]);

        $medicalCenterWallet = MedicalCenterWallet::firstOrCreate([], ['balance' => 0]);

        if ($validated['payment_method'] === 'wallet') {
            $patient = Patient::findOrFail($validated['patient_id']);

            if ($patient->wallet_balance < $validated['price']) {
                throw new \Exception('Insufficient wallet balance');
            }

            $patient->decrement('wallet_balance', $validated['price']);

            WalletTransaction::create([
                'patient_id' => $patient->id,
                'amount' => $validated['price'],
                'type' => 'payment',
                'reference' => 'APT-' . $appointment->id,
                'balance_before' => $patient->wallet_balance + $validated['price'],
                'balance_after' => $patient->wallet_balance,
                'notes' => 'Payment for appointment #' . $appointment->id
            ]);
        }

        $medicalCenterWallet->increment('balance', $validated['price']);

        MedicalCenterWalletTransaction::create([
            'medical_wallet_id' => $medicalCenterWallet->id,
            'amount' => $validated['price'],
            'type' => 'payment',
            'reference' => 'APT-' . $appointment->id,
            'balance_before' => $medicalCenterWallet->balance - $validated['price'],
            'balance_after' => $medicalCenterWallet->balance,
            'notes' => 'Payment ('.$validated['payment_method'].') from patient #' . $validated['patient_id'] . ' for appointment #' . $appointment->id,
            'clinic_id' => $validated['clinic_id']
        ]);

        $payment = Payment::create([
            'appointment_id' => $appointment->id,
            'patient_id' => $validated['patient_id'],
            'amount' => $validated['price'],
            'method' => $validated['payment_method'],
            'status' => 'paid',
            'secretary_id' => Auth::user()->secretary->id,
            'medical_center_wallet' => true,
            'transaction_id' => $validated['payment_method'] === 'wallet'
                ? 'WALLET-' . $appointment->id
                : strtoupper($validated['payment_method']) . '-' . $appointment->id,
            'paid_at' => now()
        ]);

        DB::commit();

        return response()->json([
            'message' => 'Appointment booked and payment processed',
            'appointment' => $appointment,
            'payment' => $payment
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'Failed to book appointment',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function cancelAppointment(Request $request, $appointmentId)
{
    if (!Auth::user()->secretary) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    $appointment = Appointment::with(['payments', 'patient'])->findOrFail($appointmentId);

    if ($appointment->status === 'cancelled') {
        return response()->json(['message' => 'Appointment already cancelled'], 400);
    }

    try {
        DB::beginTransaction();

        $slot = TimeSlot::where('id', $appointment->time_slot_id)
            ->lockForUpdate()
            ->first();

        if ($slot) {
            $slot->update(['is_booked' => false]);
        }

        $appointment->update([
            'status' => 'cancelled',
            'cancelled_by' => Auth::id(),
            'cancelled_at' => now(),
        ]);

        $refundResult = $this->processRefundForCancellation($appointment);

        DB::commit();

        return response()->json([
            'message' => 'Appointment cancelled successfully',
            'refund_details' => $refundResult,
            'appointment' => $appointment
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'Failed to cancel appointment',
            'error' => $e->getMessage()
        ], 500);
    }
}

protected function processRefundForCancellation(Appointment $appointment)
{
    $payment = $appointment->payments->first();
    if (!$payment || $payment->status !== 'completed') {
        return null;
    }

    $now = now();
    $createdAt = $appointment->created_at;
    $hoursSinceBooking = $createdAt->diffInHours($now);

    $refundAmount = $hoursSinceBooking <= 24
        ? $appointment->price
        : $appointment->price * 0.7;

    if ($payment->method === 'wallet') {
        try {
            DB::transaction(function () use ($appointment, $payment, $refundAmount) {
                // Lock and get the medical center wallet
                $medicalCenterWallet = MedicalCenterWallet::lockForUpdate()
                    ->firstOrCreate([], ['balance' => 0]);

                // Refund to patient's wallet
                $appointment->patient->increment('wallet_balance', $refundAmount);

                // Verify medical center wallet has sufficient funds
                if ($medicalCenterWallet->balance < $refundAmount) {
                    throw new \Exception('Medical center wallet has insufficient funds');
                }

                // Deduct from medical center wallet with verification
                $updated = MedicalCenterWallet::where('id', $medicalCenterWallet->id)
                    ->where('balance', '>=', $refundAmount)
                    ->decrement('balance', $refundAmount);

                if (!$updated) {
                    throw new \Exception('Failed to deduct from medical center wallet');
                }

                // Record transactions
                WalletTransaction::create([
                    'patient_id' => $appointment->patient->id,
                    'amount' => $refundAmount,
                    'type' => 'refund',
                    'notes' => 'Refund for cancelled appointment #' . $appointment->id,
                    'balance_before' => $appointment->patient->wallet_balance - $refundAmount,
                    'balance_after' => $appointment->patient->wallet_balance,
                ]);

                MedicalCenterWalletTransaction::create([
                    'medical_center_wallet_id' => $medicalCenterWallet->id,
                    'clinic_id' => $appointment->clinic_id,
                    'amount' => $refundAmount,
                    'type' => 'refund',
                    'notes' => 'Refund for cancelled appointment #' . $appointment->id,
                    'balance_before' => $medicalCenterWallet->balance + $refundAmount,
                    'balance_after' => $medicalCenterWallet->balance,
                ]);

                $payment->update([
                    'status' => 'refunded',
                    'refunded_at' => now(),
                    'refund_amount' => $refundAmount
                ]);
            });

            return [
                'amount' => $refundAmount,
                'method' => 'wallet',
                'original_payment_id' => $payment->id
            ];

        } catch (\Exception $e) {
            \Log::error("Refund failed for appointment {$appointment->id}: " . $e->getMessage());
            throw $e;
        }
    } else {
        // For cash/card payments, create a refund record
        $refundPayment = Payment::create([
            'appointment_id' => $appointment->id,
            'patient_id' => $appointment->patient->id,
            'amount' => -$refundAmount,
            'method' => 'refund',
            'status' => 'completed',
            'secretary_id' => Auth::user()->secretary->id,
            'medical_center_wallet' => true,
        ]);

        return [
            'amount' => $refundAmount,
            'method' => $payment->method,
            'original_payment_id' => $payment->id,
            'refund_payment_id' => $refundPayment->id
        ];
    }
>>>>>>> 0990b1cb7a8421c1b47e2ac2e468979376332b80
}






<<<<<<< HEAD
=======

// another try for the webiar




 public function getPatients(Request $request)
    {
        if (!Auth::user()->secretary) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = Patient::with(['user:id,first_name,last_name,email,phone']);

        // Search functionality
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->whereHas('user', function($userQuery) use ($search) {
                    $userQuery->where('first_name', 'like', "%{$search}%")
                             ->orWhere('last_name', 'like', "%{$search}%")
                             ->orWhere('email', 'like', "%{$search}%")
                             ->orWhere('phone', 'like', "%{$search}%");
                })->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        $patients = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'patients' => $patients->map(function($patient) {
                return [
                    'id' => $patient->id,
                    'name' => $patient->user->first_name . ' ' . $patient->user->last_name,
                    'email' => $patient->user->email,
                    'phone' => $patient->phone_number ?: $patient->user->phone,
                    'wallet_balance' => $patient->wallet_balance,
                    'wallet_activated' => !is_null($patient->wallet_activated_at),
                    'created_at' => $patient->created_at->format('Y-m-d')
                ];
            }),
            'pagination' => [
                'current_page' => $patients->currentPage(),
                'total_pages' => $patients->lastPage(),
                'total_items' => $patients->total()
            ]
        ]);
    }





    public function secretaryBookAppointment(Request $request)
    {
        if (!Auth::user()->secretary) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'patient_id' => 'required_without:new_patient|exists:patients,id',
            'new_patient' => 'required_without:patient_id|array',
            'new_patient.first_name' => 'required_with:new_patient|string|max:255',
            'new_patient.last_name' => 'required_with:new_patient|string|max:255',
            'new_patient.email' => 'required_with:new_patient|email|unique:users,email',
            'new_patient.phone' => 'required_with:new_patient|string|max:20',
            'new_patient.date_of_birth' => 'required_with:new_patient|date',
            'new_patient.gender' => 'required_with:new_patient|in:male,female,other',
            'new_patient.address' => 'nullable|string',
            'doctor_id' => 'required|exists:doctors,id',
            'clinic_id' => 'required|exists:clinics,id',
            'time_slot_id' => 'required|exists:time_slots,id',
            'reason' => 'required|string|max:500',
            'payment_method' => 'required|in:cash,card,insurance,wallet',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $secretary = Auth::user()->secretary;

            if ($request->has('new_patient')) {
                $newPatientData = $request->new_patient;

                $patientRole = Role::firstOrCreate(
                    ['name' => 'patient'],
                    ['description' => 'Patient user']
                );

                $user = User::create([
                    'first_name' => $newPatientData['first_name'],
                    'last_name' => $newPatientData['last_name'],
                    'email' => $newPatientData['email'],
                    'phone' => $newPatientData['phone'],
                    'password' => bcrypt('temporary_password'),
                    'role_id' => $patientRole->id,
                ]);

                $patient = Patient::create([
                    'user_id' => $user->id,
                    'date_of_birth' => $newPatientData['date_of_birth'],
                    'gender' => $newPatientData['gender'],
                    'address' => $newPatientData['address'] ?? null,
                    'phone_number' => $newPatientData['phone'],
                ]);

                $patientId = $patient->id;
            } else {
                $patientId = $request->patient_id;
                $patient = Patient::findOrFail($patientId);
            }

            $slot = TimeSlot::where('id', $request->time_slot_id)
                ->where('doctor_id', $request->doctor_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($slot->is_booked) {
                return response()->json([
                    'message' => 'This time slot is no longer available'
                ], 409);
            }

            $doctor = Doctor::findOrFail($request->doctor_id);

            $appointment = Appointment::create([
                'patient_id' => $patientId,
                'doctor_id' => $request->doctor_id,
                'clinic_id' => $request->clinic_id,
                'time_slot_id' => $request->time_slot_id,
                'appointment_date' => $slot->date->format('Y-m-d') . ' ' . $slot->start_time,
                'reason' => $request->reason,
                'notes' => $request->notes,
                'price' => $doctor->consultation_fee,
                'status' => 'confirmed',
                'booked_by_secretary' => true,
                'secretary_id' => $secretary->id
            ]);

            $slot->update(['is_booked' => true]);

            $paymentResult = $this->processAppointmentPayment(
                $appointment,
                $patient,
                $request->payment_method,
                $secretary->id
            );

            DB::commit();

            return response()->json([
                'message' => 'Appointment booked successfully',
                'appointment' => $appointment->load(['patient.user', 'doctor.user', 'clinic']),
                'payment' => $paymentResult,
                'new_patient_created' => $request->has('new_patient')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to book appointment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    protected function processAppointmentPayment($appointment, $patient, $paymentMethod, $secretaryId)
    {
        $medicalCenterWallet = MedicalCenterWallet::firstOrCreate([], ['balance' => 0]);

        if ($paymentMethod === 'wallet') {
            if (!$patient->wallet_activated_at) {
                throw new \Exception('Patient wallet is not activated');
            }

            if ($patient->wallet_balance < $appointment->price) {
                throw new \Exception('Insufficient wallet balance');
            }

            $patient->decrement('wallet_balance', $appointment->price);

            WalletTransaction::create([
                'patient_id' => $patient->id,
                'amount' => $appointment->price,
                'type' => 'payment',
                'reference' => 'APT-' . $appointment->id,
                'balance_before' => $patient->wallet_balance + $appointment->price,
                'balance_after' => $patient->wallet_balance,
                'notes' => 'Payment for appointment #' . $appointment->id
            ]);
        }

        $medicalCenterWallet->increment('balance', $appointment->price);

        MedicalCenterWalletTransaction::create([
            'medical_wallet_id' => $medicalCenterWallet->id,
            'clinic_id' => $appointment->clinic_id,
            'amount' => $appointment->price,
            'type' => 'payment',
            'reference' => 'APT-' . $appointment->id,
            'balance_before' => $medicalCenterWallet->balance - $appointment->price,
            'balance_after' => $medicalCenterWallet->balance,
            'notes' => 'Payment (' . $paymentMethod . ') for appointment #' . $appointment->id
        ]);

        $payment = Payment::create([
            'appointment_id' => $appointment->id,
            'patient_id' => $patient->id,
            'amount' => $appointment->price,
            'method' => $paymentMethod,
            'status' => 'paid',
            'secretary_id' => $secretaryId,
            'medical_center_wallet' => true,
            'transaction_id' => $paymentMethod === 'wallet'
                ? 'WALLET-' . $appointment->id
                : strtoupper($paymentMethod) . '-' . $appointment->id,
            'paid_at' => now()
        ]);

        return $payment;
    }


}

>>>>>>> 0990b1cb7a8421c1b47e2ac2e468979376332b80
