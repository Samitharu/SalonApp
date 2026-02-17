<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\Slider;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
    {
        $sliders = Slider::where('is_active', 1)->orderBy('sort_order')->get();
        $services = Service::where('is_active', 1)->take(6)->get();

        return view('home', compact('sliders', 'services'));
    }
}
