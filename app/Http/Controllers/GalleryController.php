<?php

namespace App\Http\Controllers;

use App\Models\GalleryImage;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;

class GalleryController extends Controller
{
    public const PER_PAGE = 24;

    public function index(Request $request)
    {
        // Filter chips: only categories that have visible photos.
        $categories = ServiceCategory::where('is_active', 1)
            ->whereHas('galleryImages', fn ($q) => $q->visible())
            ->orderBy('sort_order')->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $current = $request->filled('category') ? $categories->firstWhere('slug', (string) $request->query('category')) : null;

        $photos = GalleryImage::visible()
            ->with('category:id,name')
            ->when($current, fn ($q) => $q->where('service_category_id', $current->id))
            ->ordered()
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('gallery', compact('categories', 'current', 'photos'));
    }
}
