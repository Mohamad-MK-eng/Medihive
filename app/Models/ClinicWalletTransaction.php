<?php
// app/Models/ClinicWalletTransaction.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicWalletTransaction extends Model
{
    protected $fillable = [
        'clinic_wallet_id', 'amount', 'type', 'reference',
        'balance_before', 'balance_after', 'notes'
    ];

    public function wallet()
    {
        return $this->belongsTo(ClinicWallet::class);
    }
}
