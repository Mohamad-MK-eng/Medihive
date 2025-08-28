<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Doctor;
use App\Models\Offer;
use Illuminate\Routing\Controller;

class HomeController extends Controller{

public function __construct(){
$this->middleware(['auth','verified']) ;


}



}

