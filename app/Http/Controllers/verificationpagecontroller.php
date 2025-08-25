<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class VerificationPageController extends Controller
{
    public function show(Request $request)
    {
        $email = $request->query('email');

        // Validate that email is provided
        if (!$email) {
            return redirect('/')->with('error', 'Email parameter is required');
        }

        return view('auth.verify-email', [
            'email' => $email
        ]);
    }
}
