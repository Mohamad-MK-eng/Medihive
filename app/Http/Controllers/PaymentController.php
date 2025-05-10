<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PaymentController extends Controller
{
    //







    public function recordPayment(Request $request){
        $validated =$request->validate([
'appointment_id'=> 'required|exists:appointments,id',
'amount'=>'required|numeric',
'method'=> 'required|in:cash,card,insurance'
        ]);
    }
}
