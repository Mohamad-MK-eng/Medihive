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
            return response()->json(['error' => 'Authenticated user is not a patient'], 403);
        }

        $validated = $request->validate([
            'doctor_id' => 'required|exists:doctors,id',
            'slot_id' => 'required|exists:time_slots,id',
        //    'reason' => 'required|string|max:500',
            'method' => 'required|in:cash,wallet',
            'wallet_pin'=> 'required_if:method,wallet|digits:4 ',
          //  'notes' => 'nullable|string',
           // 'document_id' => 'nullable|exists:documents,id',
        ]);

        return DB::transaction(function () use ($validated, $patient) {
            try {
                $slot = TimeSlot::where('id', $validated['slot_id'])
                    ->where('doctor_id', $validated['doctor_id'])
                    ->lockForUpdate()  // here is the magic
                    ->firstOrFail();

                if ($slot->is_booked) {
                    return response()->json(['error' => 'This time slot has already been booked'], 409);
                }

                $doctor = Doctor::findOrFail($validated['doctor_id']);

$status = 'confirmed'; // Default status
            $paymentStatus = 'pending';


            $appointment_date = Carbon::createFromFormat(
    'Y-m-d H:i:s',
    $slot->date->format('Y-m-d') . ' ' . $slot->start_time,
    'Asia/Damascus'
);





                $appointment = Appointment::create([
                    'patient_id' => $patient->id,
                    'doctor_id' => $validated['doctor_id'],
                    'clinic_id' => $doctor->clinic_id,
                    'time_slot_id' => $slot->id,
                    'appointment_date' => $appointment_date,
                    'status' => $status,
                    'payment_status' => $paymentStatus,
                    'document_id' => $validated['document_id'] ?? null,
                     'price' => $doctor->consultation_fee,

                  //  'reason' => $validated['reason'],
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


   if ($validated['method'] === 'wallet') {
                // Verify wallet and process payment
                if (!$this->processWalletPayment($patient, $doctor->consultation_fee, $appointment)) {
                    throw new \Exception('Wallet payment failed');
                }
                $paymentStatus = 'paid';
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
    'success' => true,
    'message' => 'Operation Done Successfully',
    'appointment_details' => [
        'clinic' => $doctor->clinic->name,
        'doctor' => 'Dr. ' . $doctor->user->name,
        'date' => $appointment->appointment_date->format('D d F Y'),
        'note' => 'Stay tuned for any updates'
    ]
]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Appointment booking failed: ' . $e->getMessage()], 500);
        }
    });
}


protected function processWalletPayment($patient, $amount, $appointment)
{
    // Check balance
    if ($patient->wallet_balance < $amount) {
        return response()->json([
            'success' => false,
            'error_code' => 'insufficient_balance',
            'message' => 'Your wallet balance is insufficient.',
            'current_balance' => number_format($patient->wallet_balance, 2),
            'required_amount' => number_format($amount, 2),
            'shortfall' => number_format($amount - $patient->wallet_balance, 2),
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
            'rate' => $doctor->rating,
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




    public function getAvailableSlots($doctorId, $date)
{
    try {
        // Convert date to Carbon instance
        $dateCarbon = Carbon::createFromFormat('Y-m-d', $date);
        $now = Carbon::now();

        // Get available slots
        $slots = TimeSlot::where('doctor_id', $doctorId)
            ->where('date', $date)
            ->where('is_booked', false)
            ->where(function($query) use ($now, $date) {
                // Only show future slots
                $query->where('date', '>', $now->format('Y-m-d'))
                      ->orWhere(function($q) use ($now, $date) {
                          $q->where('date', $now->format('Y-m-d'))
                            ->where('start_time', '>', $now->format('H:i:s'));
                      });
            })
            ->orderBy('start_time')
            ->get();

        // Format the response to match your interface
        $formattedSlots = $slots->map(function($slot) {
            return [
                'id' => $slot->id,
                'time' => Carbon::parse($slot->start_time)->format('g:i A'), // Format as "10:00 AM"
                'is_booked' => $slot->is_booked
            ];
        });

        return response()->json([
            'date' => $dateCarbon->format('D j F'), // Format like "Sun 11 May"
            'available_times' => $formattedSlots,
            'earliest_time' => $slots->isNotEmpty()
                ? Carbon::parse($slots->first()->start_time)->format('g:i A')
                : null,
          //  'month_display' => $dateCarbon->format('Y, F') // "2025, May" as shown in your image
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Invalid date format or processing error',
            'message' => $e->getMessage()
        ], 400);
    }
}





public function getAvailableTimes(Doctor $doctor, $date)
{
    // Set the timezone to Asia/Damascus
    date_default_timezone_set('Asia/Damascus');
    Carbon::setTestNow(Carbon::now('Asia/Damascus'));

    $date = str_replace('date=', '', $date);

    try {
        $parsedDate = Carbon::parse($date)->timezone('Asia/Damascus')->format('Y-m-d');
    } catch (\Exception $e) {
        return response()->json(['message' => 'Invalid date format'], 400);
    }

    $now = Carbon::now('Asia/Damascus');

    $slots = TimeSlot::where('doctor_id', $doctor->id)
        ->where('date', $parsedDate)
        ->where('is_booked', false)
        ->where(function($query) use ($now, $parsedDate) {
            $query->where('date', '>', $now->format('Y-m-d'))
                  ->orWhere(function($q) use ($now, $parsedDate) {
                      $q->where('date', $parsedDate)
                        ->where('start_time', '>=', $now->format('H:i:s'));
                  });
        })
        ->orderBy('start_time')
        ->get()
        ->map(function ($slot) use ($now) {
            $time = Carbon::parse($slot->start_time)->format('g:i A');

            return [
                'slot_id' => $slot->id,
                'time' => $time,
            ];
        })->toArray();

    // Mark the earliest slot with asterisk if slots exist
    if (!empty($slots)) {
        $slots[0]['time'] = $slots[0]['time'] . '';
    }

    return response()->json([
        'times' => $slots,
       // 'date' => Carbon::parse($parsedDate)->timezone('Asia/Damascus')->format('D j F'), // "Sun 20 July"
        //'timezone' => 'Asia/Damascus',
       // 'current_time' => $now->format('g:i A') // For debugging
    ]);
}




public function getDoctorAvailableDaysWithSlots(Doctor $doctor, Request $request)
{
    $request->validate([
        'period' => 'sometimes|integer|min:1|max:30',
    ]);

    // Set timezone to Asia/Damascus
    date_default_timezone_set('Asia/Damascus');
    Carbon::setTestNow(Carbon::now('Asia/Damascus'));

    $period = $request->input('period', 7); // Default to 7 days
    $now = Carbon::now('Asia/Damascus');

    // Get doctor's working days
    $workingDays = $doctor->schedules()
        ->pluck('day')
        ->map(fn ($day) => strtolower($day))
        ->toArray();

    $startDate = Carbon::today('Asia/Damascus');
    $endDate = $startDate->copy()->addDays($period);
    $days = [];
    $earliestDateInfo = null;

    while ($startDate->lte($endDate)) {
        $dayName = strtolower($startDate->englishDayOfWeek);
        $dateDigital = $startDate->format('Y-m-d');

        if (in_array($dayName, $workingDays)) {
            $availableSlots = TimeSlot::where('doctor_id', $doctor->id)
                ->where('date', $dateDigital)
                ->where('is_booked', false)
                ->where(function($query) use ($now, $dateDigital) {
                    $query->where('date', '>', $now->format('Y-m-d'))
                          ->orWhere(function($q) use ($now, $dateDigital) {
                              $q->where('date', $now->format('Y-m-d'))
                                ->where('start_time', '>=', $now->format('H:i:s'));
                          });
                })
                ->orderBy('start_time')
                ->get();

            if ($availableSlots->isNotEmpty()) {
                $dayInfo = [
                    'full_date' => $startDate->format('Y-m-d'),
                    'day_name' => $startDate->format('D'),
                    'day_number' => $startDate->format('j'),
                    'month' => $startDate->format('F'),
                ];

                // Add time and slot_id if it's the earliest available date
                if (!$earliestDateInfo || $startDate->lt(Carbon::parse($earliestDateInfo['full_date']))) {
                    $firstSlot = $availableSlots->first();
                    $dayInfo['time'] = Carbon::parse($firstSlot->start_time)->format('g:i A');
                    $dayInfo['slot_id'] = $firstSlot->id;
                    $earliestDateInfo = $dayInfo;
                }

                $days[] = $dayInfo;
            }
        }
        $startDate->addDay();
    }

    // Remove time and slot_id from days array (keep only in earliest_date)
    $formattedDays = array_map(function($day) {
        return [
            'full_date' => $day['full_date'],
            'day_name' => $day['day_name'],
            'day_number' => $day['day_number'],
            'month' => $day['month']
        ];
    }, $days);

    return response()->json([
        'message' => '',
        'earliest_date' => $earliestDateInfo,
        'days' => $formattedDays
    ]);
}






/*

public function getAppointments(Request $request)
{
    $patient = Auth::user()->patient;

    // Set explicit timezone for all operations
    date_default_timezone_set('Asia/Damascus');
    $nowLocal = Carbon::now('Asia/Damascus');
    $nowUTC = Carbon::now('UTC');

    // First get ALL appointments with full details
    $allAppointments = $patient->appointments()
        ->with([
            'doctor.user:id,first_name,last_name,profile_picture',
            'clinic:id,name',
            'payments' => function($query) {
                $query->whereIn('status', ['completed', 'paid']);
            }
        ])
        ->orderBy('appointment_date', 'desc')
        ->get();

    // Filter upcoming appointments (confirmed and in future in LOCAL time)
    $upcomingAppointments = $allAppointments->filter(function($appointment) use ($nowLocal) {
        // Convert UTC appointment date to local timezone
        $localAppointmentTime = Carbon::parse($appointment->appointment_date)
            ->setTimezone('Asia/Damascus');

        return $appointment->status === 'confirmed' &&
               $localAppointmentTime->gte($nowLocal->startOfDay());
    })->map(function ($appointment) {
        $paymentStatus = $appointment->payments->isNotEmpty()
            ? 'confirmed'
            : 'pending';

   $doctorUser = $appointment->doctor->user;
        $profilePictureUrl = $doctorUser ? $doctorUser->getFileUrl('profile_picture') : null;
        return [
            'id' => $appointment->id,
            'date' => Carbon::parse($appointment->appointment_date)
                ->timezone('Asia/Damascus')
                ->format('Y-m-d h:i A'),
            'doctor_name' => 'Dr. ' . $appointment->doctor->user->first_name . ' ' .
                             $appointment->doctor->user->last_name,
            'doctor_profile_picture' => $profilePictureUrl,

            'clinic_name' => $appointment->clinic->name,
            'type' => $paymentStatus, //yes or no
            'price' => $appointment->price, // yes or no

        ];
    });



    return response()->json([
        'data' => $upcomingAppointments->values(),
    ]);
}
*/




/*
public function getAppointments(Request $request)
{
    $patient = Auth::user()->patient;
    $type = $request->query('type', 'upcoming'); // Default to upcoming if not specified
    $perPage = $request->query('per_page', 10); // Default to 10 items per page

    // Set explicit timezone for all operations
    date_default_timezone_set('Asia/Damascus');
    $nowLocal = Carbon::now('Asia/Damascus');

    // Base query with eager loading
    $query = $patient->appointments()
        ->with([
            'doctor.user:id,first_name,last_name,profile_picture',
            'clinic:id,name',
            'payments' => function($query) {
                $query->whereIn('status', ['completed', 'paid']);
            }
        ])
        ->orderBy('appointment_date', 'desc');

    if ($type === 'upcoming') {
        // Get upcoming appointments (confirmed and in future in LOCAL time)
        $appointments = $query->where('status', 'confirmed')
            ->whereDate('appointment_date', '>=', $nowLocal->startOfDay())
            ->get()
            ->map(function ($appointment) use ($nowLocal) {
                $localAppointmentTime = Carbon::parse($appointment->appointment_date)
                    ->setTimezone('Asia/Damascus');

                // Only include appointments that are still upcoming in local time
                if ($localAppointmentTime->gte($nowLocal)) {
                    $paymentStatus = $appointment->payments->isNotEmpty()
                        ? 'confirmed'
                        : 'pending';

                    $doctorUser = $appointment->doctor->user;
                    $profilePictureUrl = $doctorUser ? $doctorUser->getFileUrl('profile_picture') : null;

                    return [
                        'id' => $appointment->id,
                        'date' => $localAppointmentTime->format('Y-m-d h:i A'),
                        'doctor_name' => 'Dr. ' . $appointment->doctor->user->first_name . ' ' .
                                         $appointment->doctor->user->last_name,
                        'doctor_profile_picture' => $profilePictureUrl,
                        'clinic_name' => $appointment->clinic->name,
                        'type' => $paymentStatus,
                        'price' => $appointment->price,
                    ];
                }
                return null;
            })
            ->filter() // Remove null values
            ->values();

        return response()->json([
            'data' => $appointments,
        ]);
    }
    else if ($type === 'completed') {
        // Get completed appointments (paginated)
        $appointments = $query->where(function($q) use ($nowLocal) {
                $q->where('status', 'completed')
                  ->orWhereDate('appointment_date', '<', $nowLocal->startOfDay());
            })
            ->paginate($perPage)
            ->through(function ($appointment) {
                $paymentStatus = $appointment->payments->isNotEmpty()
                    ? 'confirmed'
                    : 'pending';

                $doctorUser = $appointment->doctor->user;
                $profilePictureUrl = $doctorUser ? $doctorUser->getFileUrl('profile_picture') : null;
                $localAppointmentTime = Carbon::parse($appointment->appointment_date)
                    ->setTimezone('Asia/Damascus');

                return [
                    'id' => $appointment->id,
                    'date' => $localAppointmentTime->format('Y-m-d h:i A'),
                    'doctor_name' => 'Dr. ' . $appointment->doctor->user->first_name . ' ' .
                                     $appointment->doctor->user->last_name,
                    'doctor_profile_picture' => $profilePictureUrl,
                    'clinic_name' => $appointment->clinic->name,
                    'type' => $paymentStatus,
                    'price' => $appointment->price,
                ];
            });

        return response()->json([
            'data' => $appointments->items(),
            'meta' => [
                'current_page' => $appointments->currentPage(),
                'last_page' => $appointments->lastPage(),
                'per_page' => $appointments->perPage(),
                'total' => $appointments->total(),
            ],
        ]);
    }

    return response()->json(['message' => 'Invalid appointment type'], 400);
}
*/



/*
public function getAppointments(Request $request)
{
    $patient = Auth::user()->patient;
    $type = $request->query('type', 'upcoming'); // Default to upcoming if not specified
    $perPage = $request->query('per_page', 6); // Default to 6 items per page to match your example

    // Set explicit timezone for all operations
    date_default_timezone_set('Asia/Damascus');
    $nowLocal = Carbon::now('Asia/Damascus');

    // Base query with eager loading
    $query = $patient->appointments()
        ->with([
            'doctor.user:id,first_name,last_name,profile_picture',
            'clinic:id,name',
            'payments' => function($query) {
                $query->whereIn('status', ['completed', 'paid']);
            }
        ])
        ->orderBy('appointment_date', 'desc');

    if ($type === 'upcoming') {
        // Get upcoming appointments (confirmed and in future in LOCAL time)
        $appointments = $query->where('status', 'confirmed')
            ->where('appointment_date', '>=', $nowLocal)
            ->get()
            ->map(function ($appointment) {
                $localAppointmentTime = Carbon::parse($appointment->appointment_date)
                    ->setTimezone('Asia/Damascus');

                $paymentStatus = $appointment->payments->isNotEmpty()
                    ? 'confirmed'
                    : 'pending';

                $doctorUser = $appointment->doctor->user;
                $profilePictureUrl = $doctorUser ? $doctorUser->getFileUrl('profile_picture') : null;

                return [
                    'id' => $appointment->id,
                    'date' => $localAppointmentTime->format('Y-m-d h:i A'),
                    'doctor_name' => 'Dr. ' . $appointment->doctor->user->first_name . ' ' .
                                     $appointment->doctor->user->last_name,
                    'doctor_profile_picture' => $profilePictureUrl,
                    'clinic_name' => $appointment->clinic->name,
                    'type' => $paymentStatus,
                    'price' => $appointment->price,
                ];
            });

        return response()->json([
            'data' => $appointments->values(),
        ]);
    }
    else if ($type === 'completed') {
        // Get completed appointments (either status=completed or date in past)
        $completedAppointments = $query->where(function($q) use ($nowLocal) {
                $q->where('status', 'completed')
                  ->orWhere('appointment_date', '<', $nowLocal);
            })
            ->paginate($perPage);

        // Transform the paginated results
        $transformedAppointments = $completedAppointments->map(function ($appointment) {
            $paymentStatus = $appointment->payments->isNotEmpty()
                ? 'confirmed'
                : 'pending';

            $doctorUser = $appointment->doctor->user;
            $profilePictureUrl = $doctorUser ? $doctorUser->getFileUrl('profile_picture') : null;
            $localAppointmentTime = Carbon::parse($appointment->appointment_date)
                ->setTimezone('Asia/Damascus');

            return [
                'id' => $appointment->id,
                'date' => $localAppointmentTime->format('Y-m-d h:i A'),
                'doctor_name' => 'Dr. ' . $appointment->doctor->user->first_name . ' ' .
                                 $appointment->doctor->user->last_name,
                'doctor_profile_picture' => $profilePictureUrl,
                'clinic_name' => $appointment->clinic->name,
                'type' => $paymentStatus,
                'price' => $appointment->price,
            ];
        });

        return response()->json([
            'data' => $transformedAppointments,
            'meta' => [
                'current_page' => $completedAppointments->currentPage(),
                'last_page' => $completedAppointments->lastPage(),
                'per_page' => $completedAppointments->perPage(),
                'total' => $completedAppointments->total(),
            ],
        ]);
    }

    return response()->json(['message' => 'Invalid appointment type'], 400);
}
*/




public function getAppointments(Request $request)
{
    $patient = Auth::user()->patient;
    $type = $request->query('type', 'upcoming');
    $perPage = $request->query('per_page', 8);

    date_default_timezone_set('Asia/Damascus');
    $nowLocal = Carbon::now('Asia/Damascus');

    $query = $patient->appointments()
        ->with([
            'doctor.user:id,first_name,last_name,profile_picture',
            'clinic:id,name',
            'payments' => function($query) {
                $query->whereIn('status', ['completed', 'paid']);
            }
        ])
        ->orderBy('appointment_date', 'desc');

    if ($type === 'upcoming') {
        $appointments = $query->where('status', 'confirmed')
            ->where('appointment_date', '>=', $nowLocal)
            ->get()
            ->map(function ($appointment) {
                // Determine payment status
                $paymentStatus = $appointment->payments->isNotEmpty()
                    ? 'paid'
                    : 'pending';

                $doctorUser = $appointment->doctor->user;
                $profilePictureUrl = $doctorUser ? $doctorUser->getFileUrl('profile_picture') : null;
                $localTime = Carbon::parse($appointment->appointment_date)
                    ->setTimezone('Asia/Damascus');

                return [
                    'id' => $appointment->id,
                    'date' => $localTime->format('Y-m-d h:i A'),
                    'doctor_id' => $appointment->doctor->id,
                    'first_name' =>  $appointment->doctor->user->first_name ,
                     'last_name' =>   $appointment->doctor->user->last_name,
                     'specialty' =>  $appointment->doctor->specialty,
                    'profile_picture_url' => $profilePictureUrl,
                    'clinic_name' => $appointment->clinic->name,
                    'type' => $paymentStatus,
                    'price' => $appointment->price,
                ];
            });

        return response()->json(['data' => $appointments->values()]);
    }
    else if ($type === 'completed') {
        // Get both explicitly completed and past appointments
        $completedAppointments = $query->where(function($q) use ($nowLocal) {
                $q->where('status', 'completed')
                  ->orWhere('appointment_date', '<', $nowLocal);
            })
            ->paginate($perPage)
            ->through(function ($appointment) use ($nowLocal) {
                // For appointments that are past but not marked completed
                if ($appointment->status !== 'completed' &&
                    $appointment->appointment_date < $nowLocal) {
                    $appointment->update(['status' => 'completed']);
                }

                $paymentStatus = $appointment->payments->isNotEmpty()
                    ? 'paid'
                    : 'pending';

                $doctorUser = $appointment->doctor->user;
                $profilePictureUrl = $doctorUser ? $doctorUser->getFileUrl('profile_picture') : null;
                $localTime = Carbon::parse($appointment->appointment_date)
                    ->setTimezone('Asia/Damascus');

                return [
                    'id' => $appointment->id,
                    'date' => $localTime->format('Y-m-d h:i A'),
                    'doctor_id' => $appointment->doctor->id,
                    'first_name' =>  $appointment->doctor->user->first_name ,
                     'last_name' =>   $appointment->doctor->user->last_name,
                     'specialty' =>  $appointment->doctor->specialty,
                    'profile_picture_url' => $profilePictureUrl,
                    'clinic_name' => $appointment->clinic->name,
                    'type' => $paymentStatus,
                    'price' => $appointment->price,
                ];
            });

        return response()->json([
            'data' => $completedAppointments->items(),
            'meta' => [
                'current_page' => $completedAppointments->currentPage(),
                'last_page' => $completedAppointments->lastPage(),
                'per_page' => $completedAppointments->perPage(),
                'total' => $completedAppointments->total(),
            ],
        ]);
    }

    return response()->json(['message' => 'Invalid appointment type'], 400);
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
