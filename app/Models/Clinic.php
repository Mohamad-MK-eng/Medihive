<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Storage;

class Clinic extends Model
{


    protected $fillable = [
        'name',
        'location',
        'description',
        'opening_time',
        'closing_time',
        'image_path'
    ];


    protected $fileHandlingConfig = [
        'image_path' => [
            'directory' => 'clinic_images',
            'allowed_types' => ['jpg', 'jpeg', 'png', 'svg'],
            'max_size' => 5120, // 5MB
            'default' => 'default-clinic.jpg'
        ]
    ];




    public function doctors()
    {
        return $this->hasMany(Doctor::class);
    }





    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }


public function wallet()
{
    return $this->hasOne(ClinicWallet::class);
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
        try {
            // Delete old icon if exists
            if ($this->image_path) {
                Storage::disk('public')->delete($this->image_path);
            }

            // Store in clinic_icons directory
            $path = $iconFile->store('clinic_icons', 'public');
            $this->image_path = $path;
            $this->save();

            return true;
        } catch (\Exception $e) {
            logger()->error('Clinic icon upload failed: ' . $e->getMessage());
            return false;
        }
    }
}
