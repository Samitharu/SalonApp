<?php

namespace App\Http\Controllers;

use App\Models\BusinessHour;
use App\Models\Feedback;
use App\Models\GalleryImage;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Slider;
use App\Models\Staff;
use App\Services\OfferPricing;

class HomeController extends Controller
{
    public function index()
    {
        $slides   = Slider::where('is_active', 1)->orderBy('sort_order')->orderBy('id')->take(5)->get();
        $services = Service::with('category')->bookable()->orderBy('sort_order')->orderBy('name')->take(6)->get();
        $staff    = Staff::where('is_active', 1)->orderBy('name')->take(8)->get(['id', 'name', 'title', 'bio', 'photo']);
        $hours    = BusinessHour::orderBy('day_of_week')->get()->keyBy('day_of_week');
        $reviews  = Feedback::published()->whereNotNull('comment')->where('comment', '!=', '')->with(['customer:id,name', 'service:id,name'])->latest()->take(6)->get();

        // Each category's packages load with the page so switching categories needs no round trip.
        $categories = ServiceCategory::showcase()
            ->with(['services' => fn ($q) => $q->bookable()->orderBy('sort_order')->orderBy('name')])
            ->take(8)->get();
        $photos     = GalleryImage::visible()->ordered()->take(8)->get();

        // ?category=bridal opens the homepage on that category (shareable; works without JS).
        $selected = $categories->firstWhere('slug', (string) request()->query('category'));

        // "Hair | Skin | Nails | Bridal" from the categories on show.
        $categoryLine = $categories->take(5)->pluck('name')
            ->map(fn ($n) => trim(preg_replace('/\b(services?|treatments?)\b/i', '', $n)) ?: $n)
            ->implode('  |  ');

        $offers = app(OfferPricing::class)->showing();

        return view('home', compact('slides', 'categories', 'services', 'staff', 'hours', 'reviews', 'photos', 'categoryLine', 'selected', 'offers'));
    }
}
