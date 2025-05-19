<?php
namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\Payment;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function getWalletTransactions(Request $request)
    {
        $transactions = WalletTransaction::with(['patient.user', 'admin'])
            ->when($request->has('type'), function($q) use ($request) {
                return $q->where('type', $request->type);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($transactions);
    }

    public function getClinicIncomeReport(Request $request)
    {
        $validated = $request->validate([
            'from' => 'sometimes|date',
            'to' => 'sometimes|date|after_or_equal:from'
        ]);

        $query = Payment::query();

        if ($request->has('from')) {
            $query->where('created_at', '>=', $validated['from']);
        }

        if ($request->has('to')) {
            $query->where('created_at', '<=', $validated['to']);
        }

        $report = $query->selectRaw('
            method,
            SUM(amount) as total_amount,
            COUNT(*) as transaction_count
        ')
        ->groupBy('method')
        ->get();

        return response()->json($report);
    }

    public function getPatientWalletInfo($patientId)
    {
        $patient = Patient
        ::with(['user', 'walletTransactions' => function($q) {
            $q->orderBy('created_at', 'desc')->limit(10);
        }])->findOrFail($patientId);

        return response()->json([
            'patient' => $patient,
            'wallet_balance' => $patient->wallet_balance,
            'wallet_activated' => !is_null($patient->wallet_activated_at),
            'recent_transactions' => $patient->walletTransactions
        ]);
    }
}
