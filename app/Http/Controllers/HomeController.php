<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Doctor;
use App\Models\Offer;
use App\Models\Specialty;
class HomeController extends Controller
{




    // HomeController.php
public function getHomeData() {
    return response()->json([
        'offers' => Offer::active()->get(),
        'specialties' => Specialty::with('doctors')->get(),
        'top_doctors' => Doctor::with('user')
            ->orderBy('rating', 'desc')
            ->limit(5)
            ->get()
    ]);
}
}
