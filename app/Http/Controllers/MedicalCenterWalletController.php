<?php

// app/Http/Controllers/MedicalCenterWalletController.php
namespace App\Http\Controllers;

use App\Models\MedicalCenterWallet;
use App\Models\MedicalCenterWalletTransaction;
use Illuminate\Http\Request;

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
        $query = MedicalCenterWalletTransaction::with('clinic')
            ->latest();

        // Add filters if needed
        if ($request->has('clinic_id')) {
            $query->where('clinic_id', $request->clinic_id);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        return response()->json($query->paginate(10));
    }
}
