<?php

namespace App\Http\Controllers;

use App\Models\TimeSlot;
use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;

// AppointmentController.php (simplified and fixed)
namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Services\AppointmentService;
use App\Models\TimeSlot;
use Illuminate\Http\Request;
use Auth;
use Carbon\Carbon;
use DB;
use Log;

class AppointmentController extends Controller
{
    protected $appointmentService;

    public function __construct(AppointmentService $appointmentService)
    {
        $this->appointmentService = $appointmentService;
    }








public function bookAppointment(Request $request)
{
    $validated = $request->validate([
        'doctor_id' => 'required|exists:doctors,id',
        'slot_id' => 'required|exists:time_slots,id',
        'reason' => 'required|string|max:500',
        'notes' => 'nullable|string'
    ]);

    return DB::transaction(function () use ($validated) {
        // Lock the slot and verify it belongs to the doctor
        $slot = TimeSlot::where('id', $validated['slot_id'])
                      ->where('doctor_id', $validated['doctor_id'])
                      ->lockForUpdate()
                      ->firstOrFail();

        if ($slot->is_booked) {
            return response()->json([
                'error' => 'This time slot has already been booked'
            ], 409);
        }

        $doctor = Doctor::findOrFail($validated['doctor_id']);

        $slot->update(['is_booked' => true]);

        $appointment = Appointment::create([
            'patient_id' => Auth::id(),
            'doctor_id' => $validated['doctor_id'],
            'clinic_id' => $doctor->clinic_id,
            'time_slot_id' => $slot->id,
            'appointment_date' => $slot->date.' '.$slot->start_time,
            'end_time' => $slot->end_time,
            'status' => 'confirmed',
            'reason' => $validated['reason'],
            'notes' => $validated['notes'] ?? null
        ]);


try {
    $appointment->patient->notifications()->create(['title' => 'Appointment Confirmed',
            'body' => "Your appointment with Dr. {$doctor->user->last_name} on {$slot->date->format('M j, Y')} at {$slot->start_time} has been confirmed",
            'type' => 'appointment_confirmation']);
} catch (\Exception $e) {
    Log::error('Failed to create notification', [
        'error' => $e->getMessage(),
        'appointment_id' => $appointment->id
    ]);
}
        return response()->json([
            'appointment' => $appointment->load(['doctor.user', 'clinic']),
            'message' => 'Appointment booked successfully'
        ]);
    });
}








}
