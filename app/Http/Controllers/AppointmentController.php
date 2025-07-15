<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\ClinicWallet;
use App\Models\ClinicWalletTransaction;
use App\Models\Doctor;
use App\Models\Payment;
use App\Models\TimeSlot;
use App\Models\WalletTransaction;
use App\Notifications\AppointmentBooked;
use App\Notifications\AppointmentCancelled;
use App\Notifications\AppointmentConfirmationNotification;
use App\Services\AppointmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Notification;
use App\Notifications\InvoicePaid;
use Hash;

class AppointmentController extends Controller
{
    protected $appointmentService;

    public function __construct(AppointmentService $appointmentService)
    {
        $this->appointmentService = $appointmentService;
    }

    public function bookAppointment(Request $request)
    {
        $patient = Auth::user()->patient;

        if (!$patient) {
            return response()->json(['message' => 'Authenticated user is not a patient'], 403);
        }

        $validated = $request->validate([
            'doctor_id' => 'required|exists:doctors,id',
            'slot_id' => 'required|exists:time_slots,id',
            'method' => 'required|in:cash,wallet',
            'wallet_pin'=> 'required_if:method,wallet|digits:4 ',
            'notes' => 'nullable|string',
            'document_id' => 'nullable|exists:documents,id',
        ]);

 $existingAppointment = Appointment::where('patient_id', $patient->id)
        ->where('doctor_id', $validated['doctor_id'])
        ->whereIn('status', ['confirmed', 'pending'])
        ->first();

    if ($existingAppointment) {
        return response()->json([
            'error' => 'You already have an existing appointment with this doctor',
            'existing_appointment' => $existingAppointment
        ], 409);
    }

    return DB::transaction(function () use ($validated, $patient) {
        try {
            // First check if the slot exists for this doctor
            $slotExists = TimeSlot::where('id', $validated['slot_id'])
                ->where('doctor_id', $validated['doctor_id'])
                ->exists();

            if (!$slotExists) {
                return response()->json([
                    'error' => 'The selected time slot is not available for this doctor'
                ], 404);
            }

            // Then proceed with the locked query
            $slot = TimeSlot::where('id', $validated['slot_id'])
                ->where('doctor_id', $validated['doctor_id'])
                ->lockForUpdate()
                ->first();

            if (!$slot) {
                return response()->json(['error' => 'Time slot not found'], 404);
            }

            if ($slot->is_booked) {
                return response()->json(['error' => 'This time slot has already been booked'], 409);
            }

               if ($slot->is_booked) {
                    return response()->json(['message' => 'This time slot has already been booked'], 409);
                }

                $doctor = Doctor::findOrFail($validated['doctor_id']);

                $appointment = Appointment::create([
                    'patient_id' => $patient->id,
                    'doctor_id' => $validated['doctor_id'],
                    'clinic_id' => $doctor->clinic_id,
                    'time_slot_id' => $slot->id,
                    'appointment_date' => Carbon::createFromFormat(
                        'Y-m-d H:i:s',
                        $slot->date->format('Y-m-d') . ' ' . $slot->start_time
                    ),
                    'status' => 'confirmed',
                    'document_id' => $validated['document_id'] ?? null,
                     'price' => $doctor->consultation_fee,

                    'reason' => $validated['reason'],
                    'notes' => $validated['notes'] ?? null,
                ]);


            if ($validated['method'] === 'wallet') {
                // Verify wallet is activated
                if (!$patient->wallet_activated_at) {
                    return response()->json([
                        'success' => false,
                        'error_code' => 'wallet_not_activated',
                        'message' => 'Please activate your wallet before making payments',
                    ], 400);
                }

                // Simple PIN verification without attempt tracking
                if (!Hash::check($validated['wallet_pin'], $patient->wallet_pin)) {
                    return response()->json([
                        'success' => false,
                        'error_code' => 'invalid_pin',
                        'message' => 'Incorrect PIN',
                    ], 401);
                }


                $paymentResult = $this->processWalletPayment($patient, $doctor->consultation_fee, $appointment);
                if ($paymentResult !== true) {
                    return $paymentResult; // Return error response if payment failed
                }
            }



                $slot->update(['is_booked' => true]);

            Notification::sendNow($patient->user, new AppointmentBooked($appointment));
            Notification::sendNow($doctor->user, new \App\Notifications\DoctorAppointmentBooked($appointment));

            return response()->json([
                'appointment' => $appointment->load(['doctor.user', 'clinic']),
                'payment' => [
                    'amount' => number_format($doctor->consultation_fee, 2),
                    'method' => $validated['method'],
                    'status' => $validated['method'] === 'wallet' ? 'paid' : 'pending'
                ],
                'message' => 'Appointment booked successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Appointment booking failed: ' . $e->getMessage()], 500);
        }
    });
}


public function processWalletPayment($patient, $amount, $appointment)
{
    // Check balance
    if ($patient->wallet_balance < $amount) {
        return response()->json([
            'success' => false,
            'error_code' => 'insufficient_balance',
            'message' => 'Your wallet balance is insufficient.',
         //   'current_balance' => number_format($patient->wallet_balance, 2),
         //   'required_amount' => number_format($amount, 2),
         //   'shortfall' => number_format($amount - $patient->wallet_balance, 2),
        ], 400);
    }

    return DB::transaction(function () use ($patient, $amount, $appointment) {
        // Deduct from patient wallet
        $patient->decrement('wallet_balance', $amount);

        // Add to clinic wallet
        $clinicWallet = ClinicWallet::firstOrCreate(['clinic_id' => $appointment->clinic_id]);
        $clinicWallet->increment('balance', $amount);

        // Create patient wallet transaction
        $patientTransaction = WalletTransaction::create([
            'patient_id' => $patient->id,
            'amount' => $amount,
            'type' => 'payment',
            'reference' => 'APT-' . $appointment->id,
            'balance_before' => $patient->wallet_balance + $amount,
            'balance_after' => $patient->wallet_balance,
            'notes' => 'Payment for appointment #' . $appointment->id
        ]);

        // Create clinic wallet transaction
        ClinicWalletTransaction::create([
            'clinic_wallet_id' => $clinicWallet->id,
            'amount' => $amount,
            'type' => 'payment',
            'reference' => 'APT-' . $appointment->id,
            'balance_before' => $clinicWallet->balance - $amount,
            'balance_after' => $clinicWallet->balance,
            'notes' => 'Payment from patient #' . $patient->id . ' for appointment #' . $appointment->id
        ]);

        // Create payment record
        Payment::create([
            'appointment_id' => $appointment->id,
            'patient_id' => $patient->id,
            'amount' => $amount,
            'method' => 'wallet',
            'status' => 'completed',
            'transaction_id' => 'WALLET-' . $patientTransaction->id,
            'paid_at' => now()
        ]);

        return true;
    });
}





    public function getClinicDoctors($clinicId)
    {
        $doctors = Doctor::where('clinic_id', $clinicId)


            ->with(['user:id,first_name,last_name,profile_picture'])
            ->get()
            ->map(function ($doctor) {
                return [
                    'id' => $doctor->id,
                    'first_name' => $doctor->user->first_name,
                    'last_name' => $doctor->user->last_name,
                    'specialty' => $doctor->specialty,
                    'profile_picture_url' => $doctor->user->getProfilePictureUrl()
                        ? asset('storage/' . $doctor->user->profile_picture)
                        : null,
                ];
            });

        return response()->json($doctors);
    }


    public function getDoctorDetails(Doctor $doctor)
    {
        $doctor->load(['reviews', 'schedules','user']);
$averageRating = $doctor->reviews->avg('rating');
        $schedule = $doctor->schedules->map(function ($schedule) {
            return [
                'day' => ucfirst($schedule->day),
                 'start_time' => Carbon::parse($schedule->start_time)->format('g:i A'),
                'end_time' => Carbon::parse($schedule->end_time)->format('g:i A')
            ];
        });

        return response()->json([
            'name'=> $doctor->user->first_name . ' ' . $doctor->user->last_name ,
            'specialty' => $doctor->specialty,

            'consultation_fee' => $doctor->consultation_fee,2,
            'bio' => $doctor->bio,
            'schedule' => $schedule,
            'review_count' => $doctor->reviews->count(),

            'method' => [
            'cash' => true,
            'wallet' => true
            ]
        ]);
    }







    public function getClinicDoctorsWithSlots($clinicId, Request $request)
    {
        $request->validate([
            'date' => 'sometimes|date'
        ]);

        $date = $request->input('date')
            ? Carbon::parse($request->date)->format('Y-m-d')
            : now()->addDays(30)->format('Y-m-d');

        $doctors = Doctor::with(['user:id,first_name,last_name', 'timeSlots' => function ($query) use ($date) {
            $query->where('date', $date)
                ->where('is_booked', false)
                ->orderBy('start_time');
        }])
            ->where('clinic_id', $clinicId)
            ->get()
            ->map(function ($doctor) use ($date) {
                return [
                    'id' => $doctor->id,
                    'name' => $doctor->user->first_name . ' ' . $doctor->user->last_name,
                    'specialty' => $doctor->specialty,
                    'available_slots' => $doctor->timeSlots->map(function ($slot) {
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






















public function getDoctorAvailableDaysWithSlots(Doctor $doctor, Request $request)
{

    TimeSlot::cleanupOldSlots();

    $request->validate([
        'period' => 'sometimes|integer|min:1|max:30',
    ]);

    $period = $request->input('period', 7);
    $now = Carbon::now();
    $earliestDate = null;
    $earliestSlot = null;

    // Get doctor's working days
    $workingDays = $doctor->schedules()
        ->pluck('day')
        ->map(fn ($day) => strtolower($day))
        ->toArray();



         if (empty($workingDays)) {
        return response()->json([
            'doctor_id' => $doctor->id,
            'message' => 'Doctor has no working schedule',
            'earliest_date' => null,
            'days' => []
        ]);
    }




    $startDate = Carbon::today();
    $endDate = $startDate->copy()->addDays($period);
    $availableDays = [];

    while ($startDate->lte($endDate)) {
        $dayName = strtolower($startDate->englishDayOfWeek);
        $dateDigital = $startDate->format('Y-m-d');
     //   $dateWords = $startDate->format('d D');
        $monthName = $startDate->format('F');
        $dayNumber = $startDate->format('d');

        if (in_array($dayName, $workingDays)) {
          $slots = TimeSlot::where('doctor_id', $doctor->id)
            ->where('date', $dateDigital)
            ->where('is_booked', false)
            ->orderBy('start_time')
            ->get()
            ->filter(function ($slot) use ($now, $dateDigital) {
                $slotDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $dateDigital . ' ' . $slot->start_time);
                return $slotDateTime->gt($now);
            })
            ->unique(function ($item) {
                return $item->date.$item->start_time.$item->end_time;
            })
            ->map(function ($slot) {
                return [
                    'id' => $slot->id,
                    'start_time' => $slot->formatted_start_time,
                    'time_digital' => $slot->start_time_digital
                ];
            })
            ->values();

            if ($slots->isNotEmpty()) {
                $availableDays[] = [
                    'full_date' => $startDate->format('Y-m-d'),
                    'date_digital' => $dateDigital,
                //    'date_words' => $dateWords,
                    'day_name' => $startDate->format('D'),
                    'day_number' => $dayNumber,
                    'month' => $monthName,
                   'slots' => $slots // Include slots in the response
                ];

                // Track earliest available slot only if we haven't found one yet
                // or if this date is earlier than the current earliest
                if (!$earliestDate || $startDate->lt($earliestDate)) {
                    $earliestDate = $startDate->copy();
                    $earliestSlot = $slots->first();
                }
            }
        }
        $startDate->addDay();
    }

    $earliestResponse = null;
    if ($earliestDate && $earliestSlot) {
        $earliestResponse = [
            'full_date' => $earliestDate->format('Y-m-d'),
//'date_words' => $earliestDate->format('d D'),
            'day_number' => $earliestDate->format('d'),
            'day_name' =>$earliestDate->format('D'),
            'month' => $earliestDate->format('F'),
            'time' => $earliestSlot['start_time'],
            'slot_id' => $earliestSlot['id']
        ];
    }

    return response()->json([
        'doctor_id' => $doctor->id,
        'earliest_date' => $earliestResponse,
        'days' => $availableDays,
    ]);
}

public function getAvailableTimes(Doctor $doctor, $date)
{
    // Clean the date parameter if it comes as 'date=2025-05-15'
    $date = str_replace('date=', '', $date);

    // Validate the date
    try {
        $parsedDate = Carbon::parse($date)->format('Y-m-d');
    } catch (\Exception $e) {
        return response()->json(['message' => 'Invalid date format. Use YYYY-MM-DD'], 400);
    }

    $slots = TimeSlot::where('doctor_id', $doctor->id)
        ->where('date', $parsedDate)
        ->where('is_booked', false)
        ->orderBy('start_time')
        ->get()
        ->map(function ($slot) {
            return [
                'slot_id' => $slot->id,
                'time' => Carbon::parse($slot->start_time)->format('g:i A')
            ];
        })->unique('times')->values();

    return response()->json([
        'message' => '',
        'times' => $slots
    ]);
}



    public function getAppointments(Request $request)
    {
        $patient = Auth::user()->patient;

        $appointments = $patient->appointments()
            ->with(['doctor.user:id,first_name,last_name', 'clinic:id,name'])
            ->when($request->has('status'), function ($query) use ($request) {
                return $query->where('status', $request->status);
            })
            ->when($request->has('upcoming'), function ($query) {
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
    public function getAvailableSlots($doctorId, $date)
    {
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
        /*  if (now()->diffInHours($appointment->appointment_date) < $hoursBeforeCancellation) {
            return response()->json([
                'message' => "Appointments must be cancelled at least {$hoursBeforeCancellation} hours in advance"
            ], 403);
        } */

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

        $patient = Auth::user()->patient;



        Notification::sendNow($patient->user, new AppointmentCancelled($appointment));

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


        $appointment->patient->user->notify(new \App\Notifications\AppointmentRescheduled($appointment, $originalDate));

        // Notify doctor
        $appointment->doctor->user->notify(new \App\Notifications\AppointmentRescheduled($appointment, $originalDate));




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

    // Find the appointment with necessary relationships
    $appointment = Appointment::with(['patient', 'clinic.wallet'])
        ->findOrFail($appointmentId);

    // Get or create the clinic wallet
    $clinicWallet = $appointment->clinic->wallet()->firstOrCreate([], ['balance' => 0]);

    // Verify patient exists
    if (!$appointment->patient) {
        return response()->json(['message' => 'Patient not found for this appointment'], 404);
    }

    return DB::transaction(function () use ($appointment, $clinicWallet, $validated) {
        $patient = $appointment->patient;

        // Check if clinic has enough balance for refund
        if ($clinicWallet->balance < $validated['refund_amount']) {
            return response()->json([
                'message' => 'Clinic does not have sufficient funds for this refund',
                'clinic_balance' => $clinicWallet->balance,
                'required_amount' => $validated['refund_amount']
            ], 400);
        }

        // Calculate balances before changes
        $patientBalanceBefore = $patient->wallet_balance;
        $clinicBalanceBefore = $clinicWallet->balance;

        // Refund to patient
        $patient->increment('wallet_balance', $validated['refund_amount']);

        // Deduct from clinic wallet
        $clinicWallet->decrement('balance', $validated['refund_amount']);

        // Create patient wallet transaction
        WalletTransaction::create([
            'patient_id' => $patient->id,
            'admin_id' => Auth::id(),
            'amount' => $validated['refund_amount'],
            'type' => 'refund',
            'reference' => 'REF-' . $appointment->id,
            'balance_before' => $patientBalanceBefore,
            'balance_after' => $patientBalanceBefore + $validated['refund_amount'],
            'notes' => $validated['notes'] ?? 'Refund for appointment #' . $appointment->id
        ]);

        // Create clinic wallet transaction
        ClinicWalletTransaction::create([
            'clinic_wallet_id' => $clinicWallet->id,
            'amount' => $validated['refund_amount'],
            'type' => 'refund',
            'reference' => 'REF-' . $appointment->id,
            'balance_before' => $clinicBalanceBefore,
            'balance_after' => $clinicBalanceBefore - $validated['refund_amount'],
            'notes' => 'Refund to patient #' . $patient->id . ' for appointment #' . $appointment->id
        ]);

        // Handle cancellation fee if applicable
        if (isset($validated['cancellation_fee']) && $validated['cancellation_fee'] > 0) {
            // Get fresh balances after refund
            $patientBalanceBeforeFee = $patient->fresh()->wallet_balance;
            $clinicBalanceBeforeFee = $clinicWallet->fresh()->balance;

            // Deduct fee from patient
            $patient->decrement('wallet_balance', $validated['cancellation_fee']);

            // Add fee to clinic
            $clinicWallet->increment('balance', $validated['cancellation_fee']);

            // Patient fee transaction
            WalletTransaction::create([
                'patient_id' => $patient->id,
                'admin_id' => Auth::id(),
                'amount' => $validated['cancellation_fee'],
                'type' => 'fee',
                'reference' => 'FEE-' . $appointment->id,
                'balance_before' => $patientBalanceBeforeFee,
                'balance_after' => $patientBalanceBeforeFee - $validated['cancellation_fee'],
                'notes' => 'Cancellation fee for appointment #' . $appointment->id
            ]);

            // Clinic fee transaction
            ClinicWalletTransaction::create([
                'clinic_wallet_id' => $clinicWallet->id,
                'amount' => $validated['cancellation_fee'],
                'type' => 'fee',
                'reference' => 'FEE-' . $appointment->id,
                'balance_before' => $clinicBalanceBeforeFee,
                'balance_after' => $clinicBalanceBeforeFee + $validated['cancellation_fee'],
                'notes' => 'Cancellation fee from patient #' . $patient->id . ' for appointment #' . $appointment->id
            ]);
        }

        return response()->json([
            'message' => 'Refund processed successfully',
            'patient_new_balance' => $patient->fresh()->wallet_balance,
            'clinic_new_balance' => $clinicWallet->fresh()->balance,
            'refund_amount' => $validated['refund_amount'],
            'cancellation_fee' => $validated['cancellation_fee'] ?? 0
        ]);
    });
}



}
