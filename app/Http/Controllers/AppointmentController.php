<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Doctor;
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
            'reason' => 'required|string|max:500',
            'notes' => 'nullable|string',
            'document_id' => 'nullable|exists:documents,id',
        ]);

        return DB::transaction(function () use ($validated, $patient) {
            try {
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
                    'reason' => $validated['reason'],
                    'notes' => $validated['notes'] ?? null,
                ]);

                Notification::sendNow($patient->user, new AppointmentBooked($appointment));
                Notification::sendNow($doctor->user, new \App\Notifications\DoctorAppointmentBooked($appointment));

                return response()->json([
                    'appointment' => $appointment->load(['doctor.user', 'clinic']),
                    'message' => 'Appointment booked successfully'
                ]);
            } catch (\Exception $e) {

                return response()->json(['error' => 'Appointment booking failed'], 500);
            }
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
            'consultation_fee' => $doctor->consultation_fee,
            'bio' => $doctor->bio,
            'schedule' => $schedule,
            'review_count' => $doctor->reviews->count(),
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

    $startDate = Carbon::today();
    $endDate = $startDate->copy()->addDays($period);
    $availableDays = [];

    while ($startDate->lte($endDate)) {
        $dayName = strtolower($startDate->englishDayOfWeek);
        $dateDigital = $startDate->format('Y-m-d');
        $dateWords = $startDate->format('d D');
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
                ->map(function ($slot) {
                    $start = Carbon::parse($slot->start_time);
                    return [
                        'id' => $slot->id,
                        'start_time' => $start->format('g:i A'),
                        'time_digital' => $start->format('H:i')

                    ];
                });

                // Track earliest available slot
                if (!$earliestDate || $startDate->lt($earliestDate)) {
                    $earliestDate = $startDate->copy();
                    $earliestSlot = $slots->first();
                }



            if ($slots->isNotEmpty()) {
                $availableDays[] = [
                'full_date' => $startDate->format('Y/m/d'),
                    'date_digital' => $dateDigital,
                    'date_words' => $dateWords,
                    'day_name' => $startDate->format('D'),
                      'day_number' => $dayNumber,
                    'month' => $monthName,
                ];
            }
        }
        $startDate->addDay();
    }


  $earliestResponse = null;
    if ($earliestDate && $earliestSlot) {
        $earliestResponse = [
            'full_date' => $earliestDate->format('Y/m/d'),
            'day_name' => $earliestDate->format('D'),
            'day_number' => $earliestDate->format('d'),
            'month' => $earliestDate->format('F'),
            'time' => $earliestSlot['start_time']
        ];
    }



    return response()->json([
        'doctor_id' => $doctor->id,
                'earliest_date' => $earliestResponse,
        'days' => $availableDays,

        'period' => $period,
        'available_days' => $availableDays
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
                'time' => Carbon::parse($slot->start_time)->format('g:i A')
            ];
        });

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

        $appointment = Appointment::findOrFail($appointmentId);
        $patient = $appointment->patient;

        return DB::transaction(function () use ($appointment, $patient, $validated) {
            $transaction = WalletTransaction::create([
                'patient_id' => $patient->id,
                'admin_id' => Auth::id(),
                'amount' => $validated['refund_amount'],
                'type' => 'refund',
                'reference' => 'REF-' . $appointment->id,
                'balance_before' => $patient->wallet_balance,
                'balance_after' => $patient->wallet_balance + $validated['refund_amount'],
                'notes' => $validated['notes'] ?? 'Refund for appointment #' . $appointment->id
            ]);

            $patient->increment('wallet_balance', $validated['refund_amount']);

            if (isset($validated['cancellation_fee'])) {

                WalletTransaction::create([
                    'patient_id' => $patient->id,
                    'admin_id' => Auth::id(),
                    'amount' => $validated['cancellation_fee'],
                    'type' => 'payment',
                    'reference' => 'FEE-' . $appointment->id,
                    'balance_before' => $patient->wallet_balance + $validated['refund_amount'],
                    'balance_after' => $patient->wallet_balance + $validated['refund_amount'] - $validated['cancellation_fee'],
                    'notes' => 'Cancellation fee for appointment #' . $appointment->id
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
