<?php

// app/Models/ClinicWallet.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicWallet extends Model
{
    protected $fillable = ['clinic_id', 'balance'];

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }


    public function wallet()
{
    return $this->hasOne(ClinicWallet::class);
}

public function payments()
{
    return $this->hasManyThrough(Payment::class, Appointment::class);
}


    public function transactions()
    {
        return $this->hasMany(ClinicWalletTransaction::class);
    }
}
