<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 *
 *
 * @property int $id
 * @property int $patient_id
 * @property int $secretary_id
 * @property int $appointment_id
 * @property int $service_id
 * @property string $amount
 * @property string $status
 * @property string $method
 * @property string|null $paid_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Appointment $appointment
 * @property-read \App\Models\Patient $patient
 * @property-read \App\Models\Appointment $secretary
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereAppointmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment wherePaidAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment wherePatientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereSecretaryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereServiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Payment extends Model
{
    protected $fillable =[
'patient_id',

'secretary_id',
'appointment_id',
'amount',
'status',
'method',
'paid_at'
    ];

    protected $casts =[

'secretary_id',
'appointment_id',
'amount',
'paid_at'

    ];

public function patient(){
    return $this->belongsTo(Patient::class);
}


public function secretary(){

    return $this->belongsTo(Secretary::class);
}


public function appointment(){
    return $this->belongsTo(Appointment::class);
}









    // Helper methods
    public function isPaid()
    {
        return $this->status === 'paid';
    }

    public function getPaymentMethod()
    {
        return $this->method;
    }
}

