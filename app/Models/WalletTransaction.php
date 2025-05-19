<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'amount',
        'type',
        'reference',
        'balance_before',
        'balance_after',
        'notes',
        'admin_id'
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
