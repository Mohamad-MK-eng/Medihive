<?php

// app/Http/Controllers/MedicalCenterWalletController.php
namespace App\Http\Controllers;

use App\Models\MedicalCenterWallet;
use App\Models\MedicalCenterWalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class MedicalCenterWalletController extends Controller
{

    public function show()
    {
        $wallet = MedicalCenterWallet::firstOrCreate([]);
        $transactions = MedicalCenterWalletTransaction::with('clinic')
            ->latest()
            ->paginate(10);

        return response()->json([
            'balance' => $wallet->balance,
            'transactions' => $transactions
        ]);
    }


    public function transactions(Request $request)
{
    $query = MedicalCenterWalletTransaction::with('clinic')->latest();

    // Filter by transaction type (refund or appointment_payment(cash)  or wallet_payment)
    if ($request->has('type')) {
        $validTypes = ['refund', 'appointment_payment', 'wallet_payment'];
        if (in_array($request->type, $validTypes)) {
            $query->where('type', $request->type);
        }
    }

    // Optionally, keep clinic filtering if needed (but not required)
    if ($request->has('clinic_id')) {
        $query->where('clinic_id', $request->clinic_id);
    }

    return response()->json($query->paginate(10));
}



}
