<?php

namespace App\Http\Controllers;

use App\Models\TimeSlot;
use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;

// AppointmentController.php (simplified and fixed)
namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Services\AppointmentService;
use App\Models\TimeSlot;
use Illuminate\Http\Request;
use Auth;
use Carbon\Carbon;
use DB;

class AppointmentController extends Controller
{
    protected $appointmentService;

    public function __construct(AppointmentService $appointmentService)
    {
        $this->appointmentService = $appointmentService;
    }

    // In Doctor model





public function bookAppointment(Request $request)
{
    $validated = $request->validate([
        'doctor_id' => 'required|exists:doctors,id',
        'slot_id' => 'required|exists:time_slots,id',
        'reason' => 'required|string|max:500'
    ]);

    return DB::transaction(function () use ($validated) {
        $slot = TimeSlot::findOrFail($validated['slot_id']);


        $slot->update(['is_booked' => true]);

        $appointment = Appointment::create([
            'patient_id' => Auth::id(),
            'doctor_id' => $validated['doctor_id'],
            'appointment_date' => $slot->date.' '.$slot->start_time,
            'end_time' => $slot->end_time,
            'status' => 'confirmed',
            'reason' => $validated['reason']
        ]);

        return response()->json([
            'appointment' => $appointment->load('doctor', 'clinic'),
            'message' => 'Appointment booked successfully'
        ]);
    });
}


}
