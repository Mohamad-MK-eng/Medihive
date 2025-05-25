<?php

namespace App\Http\Controllers;

use App\Models\Specialty;
use Illuminate\Http\Request;
use Storage;

class SpecialtyController extends Controller
{
    // Get all specialties with their icons
    public function index()
    {
        $specialties = Specialty::all()->map(function($specialty) {  // Fixed variable name
            return [
                'id' => $specialty->id,
                'name' => $specialty->name,
                'description' => $specialty->description,
                'icon_url' => $specialty->getIconUrl()
            ];
        });

        return response()->json($specialties);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
            'icon' => 'nullable|image|mimes:jpg,jpeg,png|max:2048'
        ]);

        $specialty = Specialty::create($validated);

        if ($request->hasFile('icon')) {
            $specialty->uploadIcon($request->file('icon'));
        }

        return response()->json($specialty, 201);
    }

    // Upload specialty icon
    public function uploadIcon(Request $request, $id)
    {
        $request->validate([
            'icon' => 'required|image|mimes:jpg,jpeg,png|max:2048'
        ]);

        $specialty = Specialty::findOrFail($id);

        if ($specialty->uploadIcon($request->file('icon'))) {
            return response()->json([
                'success' => true,
                'icon_url' => $specialty->getIconUrl(),
                'message' => 'Specialty icon updated successfully'
            ]);
        }

        return response()->json([
            'error' => 'Invalid image file. Only JPG/JPEG/PNG files under 2MB are allowed'
        ], 400);
    }
public function getIcon($id)
{
    $specialty = Specialty::findOrFail($id);

    if (!$specialty->image_path) {
        return response()->json(['message' => 'No icon set for this specialty'], 404);
    }

    // Get the full path on disk
    $filePath = storage_path('app/public/' . $specialty->image_path);

    if (!file_exists($filePath)) {
        return response()->json(['message' => 'Icon file not found'], 404);
    }

    // Return the file directly
    return response()->file($filePath);
}

}
