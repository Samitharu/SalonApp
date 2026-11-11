<?php

namespace App\Http\Controllers;

use App\Services\OfferPricing;

class OfferController extends Controller
{
    public function index(OfferPricing $pricing)
    {
        return view('offers', ['offers' => $pricing->showing()]);
    }
}
