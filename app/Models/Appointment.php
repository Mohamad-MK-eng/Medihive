<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

/**
 * @property int $id
 * @property int $patient_id
 * @property int $doctor_id
 * @property int $clinic_id
 * @property int $time_slot_id
 * @property \Illuminate\Support\Carbon $appointment_date
 * @property string $status
 * @property float $price
 * @property int $service_id
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property \Illuminate\Support\Carbon|null $previous_date
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \App\Models\Doctor $doctor
 * @property-read \App\Models\Patient $patient
 * @property-read \App\Models\Payment|null $payment
 * @property-read \App\Models\Prescription|null $prescription
 */
class Appointment extends Model
{
    use Notifiable;




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
        'previous_date',
        'rescheduled_by',
        'method'
    ];

    protected $casts = [
        'patient_id' => 'integer',
        'doctor_id' => 'integer',
        'clinic_id' => 'integer',
        'time_slot_id' => 'integer',
        'service_id' => 'integer',
        'appointment_date' => 'datetime',
        'end_time' => 'datetime:H:i:s',
        'cancelled_at' => 'datetime',
        'previous_date' => 'datetime',
        'price' => 'float',
        'rescheduled_by' => 'integer',
    ];



    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }


    public function prescription()
    {
        return $this->hasOne(Prescription::class);
    }


    public function payments()
    {
        return $this->hasOne(Payment::class);
    }



    public function clinic()
    {
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
