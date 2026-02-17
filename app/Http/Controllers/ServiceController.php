<?php

namespace App\Http\Controllers;

use App\Models\ServiceCategory;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
   public function index()
{
    $categories = ServiceCategory::with('services')->get();

    $uncategorized = \App\Models\Service::whereNull('service_category_id')->get();

    return view('services.index', compact('categories', 'uncategorized'));
}
}
