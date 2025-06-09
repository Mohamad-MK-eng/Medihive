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









  public function getClinicImageUrl()
    {
        return $this->getFileUrl('description_picture', 'images/default-clinic.jpg');
    }

    // Upload clinic image
    public function uploadClinicImage($file)
    {
        return $this->uploadFile($file, 'description_picture', 'clinic_images');
    }






}
