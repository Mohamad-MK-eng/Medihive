<?php

// app/Http/Controllers/SecretaryController.php
namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Notifications\AppointmentRescheduled;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SecretaryController extends Controller
{
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



        $conflict =Appointment::where('doctor_id',$appointment->doctor_id)
        ->where('appointment_date',$validated['new_date'])->exists();
        if($conflict){
            return response()->json(['message'=>'Doctor not available at this time'],409);

        }

        $originalDate = $appointment->appointment_date;

        $appointment->update([
            'appointment_date' => $validated['new_date'],
            'previous_date' => $originalDate,
            'rescheduled_by' => Auth::id(),
            'reschedule_reason' => $validated['reason'] ?? null
        ]);



        $appointment->refresh();


        return response()->json([
            'message' => 'Appointment rescheduled successfully',
            'appointment' => $appointment->load(['patient.user','doctor.user'])

        ]);
    }


}

