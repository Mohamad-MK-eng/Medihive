<?php
namespace App\Http\Controllers;

use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;

class AdminController extends Controller
{


    // not tested yet until another time
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




























    // optional and not tested yet :







     public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
            'icon' => 'nullable|image|mimes:jpg,jpeg,png|max:2048'
        ]);

        $specialty = Clinic::create($validated);

        if ($request->hasFile('icon')) {
            $specialty->uploadIcon($request->file('icon'));
        }

        return response()->json($specialty, 201);
    }

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







      // Upload clinic image
    public function uploadImage(Request $request, $id)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png|max:2048'
        ]);

        $clinic = Clinic::findOrFail($id);

        if ($clinic->uploadImage($request->file('image'))) {
            return response()->json([
                'success' => true,
                'image_url' => $clinic->getClinicImageUrl(),
                'message' => 'Clinic image updated successfully'
            ]);
        }

        return response()->json([
            'error' => 'Invalid image file. Only JPG/JPEG/PNG files under 2MB are allowed'
        ], 400);
    }
}
