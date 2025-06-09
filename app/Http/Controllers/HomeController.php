<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Doctor;
use App\Models\Offer;

class HomeController extends Controller
{
    public function getHomeScreen(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'offers' => $this->getActiveOffers(),
            'top_doctors' => $this->getTopRatedDoctors(),
            'quick_actions' => $this->getQuickActions($user)
        ]);
    }

    protected function getActiveOffers()
    {
        return Offer::active()->get()->map(function ($offer) {
            return [
                'id' => $offer->id,
                'title' => $offer->title,
                'description' => $offer->description,
                'discount' => $offer->discount_percentage,
                'image' => asset($offer->image_path)
            ];
        });
    }


    protected function getTopRatedDoctors()
    {
        return Doctor::with(['user', 'specialty'])
            ->withAvg('reviews', 'rating')
            ->orderBy('reviews_avg_rating', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($doctor) {
                return [
                    'id' => $doctor->id,
                    'name' => $doctor->user->full_name,
                    'specialty' => $doctor->specialty,
                    'experience' => $doctor->years_of_experience,
                    'rating' => round($doctor->reviews_avg_rating, 1),
                    'photo' => $doctor->user->profile_photo_url
                ];
            });
    }

    protected function getQuickActions($user)
    {
        return [
            [
                'title' => 'Book Appointment',
                'icon' => 'calendar-plus',
                'route' => '/appointments/book'
            ],
            [
                'title' => 'Medical Records',
                'icon' => 'file-medical',
                'route' => '/medical-records'
            ],
            [
                'title' => 'Lab Results',
                'icon' => 'flask',
                'route' => '/lab-results'
            ]
        ];
    }
}
