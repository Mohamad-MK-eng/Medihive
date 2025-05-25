<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ClinicController extends Controller
{
    // Get all clinics with their images
    public function index()
    {
        $clinics = Clinic::all()->map(function($clinic) {
            return [
                'id' => $clinic->id,
                'name' => $clinic->name,
                'location' => $clinic->location,
                'opening_time' => $clinic->opening_time,
                'closing_time' => $clinic->closing_time,
                'image_url' => $clinic->getClinicImageUrl()
            ];
        });

        return response()->json($clinics);
    }

    // Upload clinic image
    public function uploadImage(Request $request, $id)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png|max:2048'
        ]);

        $clinic = Clinic::findOrFail($id);

        if ($clinic->uploadClinicImage($request->file('image'))) {
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

    // Get clinic image
    public function getImage($id)
    {
        $clinic = Clinic::findOrFail($id);

        if (!$clinic->description_picture) {
            return response()->json(['message' => 'No image set for this clinic'], 404);
        }

        try {
            $filename = basename($clinic->description_picture);
            $relativePath = 'clinic_images/' . $filename;

            if (!Storage::disk('public')->exists($relativePath)) {
                return response()->json(['message' => 'Image file not found'], 404);
            }

            $fullPath = storage_path('app/public/' . $relativePath);
            return response()->file($fullPath);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving clinic image',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
