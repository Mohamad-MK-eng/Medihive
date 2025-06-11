<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Storage;

class Clinic extends Model
{


    protected $fillable =[
'name',
'location',
'decritption',
'opening_time',
'closing_time',
'image_path'
    ];




    public function doctors() {
        return $this->hasMany(Doctor::class);
    }





    public function appointments(){
        return $this->hasMany(Appointment::class);
    }






 public function getIconUrl()
    {
        if (!$this->image_path) {
            return asset('storage/Clinic_Icons/default.jpg');
        }

        // Manually construct the URL to ensure consistency
        return url('storage/Clinic_Icons/' . basename($this->image_path));
    }

    public function uploadIcon($iconFile)
    {
        if ($iconFile) {
            // Delete old icon if exists
            if ($this->image_path) {
                Storage::disk('public')->delete($this->image_path);
            }

            // Store in specialty_icons directory
            $path = $iconFile->store('Clinic_Icons', 'public');
            $this->image_path = $path;
            return $this->save();
        }
        return false;
    }



}
