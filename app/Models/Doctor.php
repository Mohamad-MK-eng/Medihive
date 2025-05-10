<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 *
 *
 * @property int $id
 * @property int $user_id
 * @property string $specialty
 * @property array<array-key, mixed> $workdays
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Appointment> $appointments
 * @property-read int|null $appointments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Prescription> $prescriptions
 * @property-read int|null $prescriptions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Service> $services
 * @property-read int|null $services_count
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Doctor newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Doctor newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Doctor query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Doctor whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Doctor whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Doctor whereSpecialty($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Doctor whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Doctor whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Doctor whereWorkdays($value)
 * @mixin \Eloquent
 */
class Doctor extends Model
{
    protected $fillable =[
 'user_id',
 'clinic_id',
 'specialty',
 'workdays'
    ];




    protected $casts =[
        'user_id',
        'clinic_id',
'workdays'=> 'array'
    ];



public function user(){
    return $this->belongsTo(User::class);
}


public function appointments(){
    return $this->hasMany(Appointment::class);
}



public function prescriptions(){
    return $this->hasManyThrough(Prescription::class,Appointment::class);
}


public function services(){
    return $this->belongsToMany(Service::class);
}


public function clinic(){
    return $this->belongsTo(Clinic::class);
}


public function availableTimeSlots(){
    return $this->hasMany(DoctorSchedule::class);
}

  // Helper methods
  public function isAvailable($date, $time)
  {
      return $this->workdays[$date] ?? false;
  }

  public function getAvailableServices()
  {
      return $this->services()->get();
  }



  public function schedules()
  {
      return $this->hasMany(DoctorSchedule::class);
  }


}
