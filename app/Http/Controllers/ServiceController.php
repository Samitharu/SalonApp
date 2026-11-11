<?php

namespace App\Http\Controllers;

use App\Models\GalleryImage;
use App\Models\Service;
use App\Models\ServiceCategory;

class ServiceController extends Controller
{
    public function index()
    {
        // All filtering happens client-side, so the whole menu loads once.
        $categories = ServiceCategory::orderBy('sort_order')->orderBy('name')->get();

        $services = Service::with('category')
            ->bookable()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Add-on only items are listed separately with prices but no Book button.
        $addons = Service::where('is_active', 1)->where('is_addon', 1)->orderBy('sort_order')->orderBy('name')->get();

        $categories = $categories->filter(fn ($c) => $services->contains('service_category_id', $c->id))->values();

        return view('services.index', compact('categories', 'services', 'addons'));
    }

    /** One category: its hero photo, packages and a few gallery photos. */
    public function category(ServiceCategory $category)
    {
        abort_unless($category->is_active, 404);

        $packages = $category->services()->bookable()->orderBy('sort_order')->orderBy('name')->get();
        abort_if($packages->isEmpty(), 404);

        $others = ServiceCategory::showcase()->get(['id', 'name', 'slug']);
        $photos = GalleryImage::visible()->where('service_category_id', $category->id)->ordered()->take(8)->get();

        return view('services.category', compact('category', 'packages', 'others', 'photos'));
    }
}
