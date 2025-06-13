<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 *
 *
 * @property int $id
 * @property int $patient_id
 * @property int $doctor_id
 * @property string $appointment_date
 * @property string $status
 * @property string $price
 * @property int $service_id
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Doctor $doctor
 * @property-read \App\Models\Patient $patient
 * @property-read \App\Models\Payment|null $payment
 * @property-read \App\Models\Prescription|null $prescription
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appointment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appointment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appointment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appointment whereAppointmentDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appointment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appointment whereDoctorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appointment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appointment whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appointment wherePatientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appointment wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appointment whereServiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appointment whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Appointment whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Appointment extends Model
{
  protected $fillable = [
    'patient_id',
    'doctor_id',
    'clinic_id',
    'time_slot_id',
    'appointment_date',
    'end_time',
    'reason',
    'status',
    'service_id',
    'price',
    'notes',
    'cancelled_at',
    'previous_date'

];


    protected $casts =[
        'patient_id',
'doctor_id',
'clinic_id',
'appointment_date',
'end_time' => 'datetime',
        'appointment_date' =>'datetime',
        'cancelled_at' =>'datetime',
        'previous_date'=> 'datetime',
        'status'=> 'string',
        'service_id',
'price' => 'float',
'cancelled_at',
'previous_date',
'rescheduled_by'
    ];



public function patient(){
    return $this->belongsTo(Patient::class);

}

public function doctor(){
    return $this->belongsTo(Doctor::class);

}


public function prescription(){
    return $this->hasOne(Prescription::class);
}


public function payments(){
    return $this->hasOne(Payment::class);
}



public function clinic(){
    return $this->belongsTo(Clinic::class);
}




 // Helper methods
 public function isUpcoming()
 {
     return $this->appointment_date >= now();
 }

 public function isCompleted()
 {
     return $this->status === 'completed';
 }




// Relationship to secretary who rescheduled
public function rescheduledBy()
{
    return $this->belongsTo(User::class, 'rescheduled_by');
}



}
