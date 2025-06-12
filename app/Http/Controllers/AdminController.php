<?php
namespace App\Http\Controllers;

use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Validator;

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


    // في كل شي بخص الصور عملتو image_path



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




 public function createClinic(Request $request)
    {
        // هون ما بعرف اذا بدك تحط انو يتأكد انه ادمن


        $validated = $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
            'image_path' => 'nullable|image|mimes:jpg,jpeg,png|max:2048'
        ]);

        $clinic = Clinic::create($validated);

        if ($request->hasFile('image_path')) {
            $clinic->uploadIcon($request->file('image_path'));
        }

        return response()->json($clinic, 201);
    }










 public function update(Request $request, $id)
    {
        $clinic = Clinic::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'location' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|nullable',
            'opening_time' => 'sometimes|date_format:H:i',
            'closing_time' => 'sometimes|date_format:H:i|after:opening_time',
            'icon' => 'sometimes|image|mimes:jpg,jpeg,png|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Handle icon update if present
        if ($request->hasFile('icon')) {
            try {
                $clinic->uploadIcon($request->file('icon'));
            } catch (\Exception $e) {
                return response()->json([
                    'error' => 'Failed to upload clinic icon',
                    'message' => $e->getMessage()
                ], 500);
            }
        }

        $clinic->update($validator->validated());

        return response()->json([
            'clinic' => $clinic,
            'icon_url' => $clinic->getIconUrl(),
            'message' => 'Clinic updated successfully'
        ]);
    }













// في كل شي بخص الصور عملتو image_path
   public function uploadClinicIcon(Request $request, $id)
    {
    $request->validate([
        'image_path' => 'required|image|mimes:jpg,jpeg,png|max:2048'
    ]);

    $clinic = Clinic::findOrFail($id);

    if ($clinic->uploadIcon($request->file('image_path'))) {
        return response()->json([
            'success' => true,
            'image_url' => $clinic->getIconUrl(),/////////
            'message' => 'Clinic image updated successfully'
        ]);
    }

    return response()->json([
        'error' => 'Invalid image file. Only JPG/JPEG/PNG files under 2MB are allowed'
    ], 400);
   }
}
