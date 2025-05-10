<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Clinic extends Model
{


    protected $fillable =[
'name',
'location',
'opening_time',
'closing_time'
    ];




    public function doctors() {
        return $this->hasMany(Doctor::class);
    }





    public function appointments(){
        return $this->hasMany(Appointment::class);
    }
}
