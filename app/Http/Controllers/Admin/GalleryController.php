<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GalleryImage;
use App\Models\ServiceCategory;
use App\Models\SystemLog;
use App\Services\ImageService;
use Illuminate\Http\Request;

class GalleryController extends Controller
{
    public const MAX_FILES = 12;
    public const MAX_KB = 8192;
    public const FULL_WIDTH = 1800;
    public const THUMB_WIDTH = 640;

    public function index(Request $request)
    {
        $categories = ServiceCategory::orderBy('sort_order')->orderBy('name')->get(['id', 'name']);
        $filter = $request->query('category');

        $images = GalleryImage::with('category:id,name')
            ->when($filter === 'none', fn ($q) => $q->whereNull('service_category_id'))
            ->when(ctype_digit((string) $filter), fn ($q) => $q->where('service_category_id', (int) $filter))
            ->ordered()
            ->paginate(36)
            ->withQueryString();

        $counts = GalleryImage::selectRaw('service_category_id, count(*) as n')->groupBy('service_category_id')->pluck('n', 'service_category_id');

        return view('admin.gallery.index', compact('categories', 'images', 'filter', 'counts'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'images'              => 'required|array|min:1|max:' . self::MAX_FILES,
            'images.*'            => 'required|image|mimes:jpg,jpeg,png,webp|max:' . self::MAX_KB . '|dimensions:max_width=10000,max_height=10000',
            'caption'             => 'nullable|string|max:150',
            'service_category_id' => 'nullable|integer|exists:service_categories,id',
        ], [
            'images.required' => 'Choose at least one photo.',
            'images.max'      => 'Upload up to ' . self::MAX_FILES . ' photos at a time.',
            'images.*.image'  => 'Each file must be a photo (JPG, PNG or WebP).',
            'images.*.mimes'  => 'Each file must be a photo (JPG, PNG or WebP).',
            'images.*.max'    => 'Each photo must be ' . (self::MAX_KB / 1024) . ' MB or smaller.',
        ]);

        $created = 0;
        foreach ($request->file('images') as $file) {
            $stored = ImageService::store($file, 'gallery', self::FULL_WIDTH, self::THUMB_WIDTH);
            GalleryImage::create([
                'image'               => $stored['path'],
                'thumb'               => $stored['thumb'],
                'width'               => $stored['width'],
                'height'              => $stored['height'],
                'caption'             => $data['caption'] ?? null,
                'service_category_id' => $data['service_category_id'] ?? null,
            ]);
            $created++;
        }

        $category = isset($data['service_category_id']) ? ServiceCategory::find($data['service_category_id']) : null;
        SystemLog::record('gallery.uploaded', $created . ' ' . str('photo')->plural($created) . ' added to the gallery' . ($category ? " ({$category->name})" : ''), null,
            ['count' => $created, 'category' => $category?->name]);

        return redirect()->route('admin.gallery.index')->with('success', $created . ' ' . str('photo')->plural($created) . ' uploaded.');
    }

    public function edit(GalleryImage $image)
    {
        $categories = ServiceCategory::orderBy('sort_order')->orderBy('name')->get(['id', 'name']);

        return view('admin.gallery.edit', compact('image', 'categories'));
    }

    public function update(Request $request, GalleryImage $image)
    {
        $data = $request->validate([
            'caption'             => 'nullable|string|max:150',
            'service_category_id' => 'nullable|integer|exists:service_categories,id',
            'sort_order'          => 'nullable|integer|min:0|max:9999',
        ]);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_active'] = $request->boolean('is_active');

        $image->update($data);
        if ($changed = SystemLog::changes($image)) {
            SystemLog::record('gallery.updated', 'Gallery photo #' . $image->id . ' updated', $image, $changed);
        }

        return redirect()->route('admin.gallery.index')->with('success', 'Photo updated.');
    }

    public function destroy(GalleryImage $image)
    {
        ImageService::delete($image->image, $image->thumb);
        SystemLog::record('gallery.deleted', 'Gallery photo #' . $image->id . ' deleted' . ($image->caption ? " (\"{$image->caption}\")" : ''), $image, level: 'warning');
        $image->delete();

        return back()->with('success', 'Photo deleted.');
    }
}
