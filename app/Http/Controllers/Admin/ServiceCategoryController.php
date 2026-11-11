<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceCategory;
use App\Models\SystemLog;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ServiceCategoryController extends Controller
{
    public const IMAGE_MAX_KB = 8192;
    public const IMAGE_WIDTH = 2000;

    public function index()
    {
        $categories = ServiceCategory::withCount('services')
            ->withCount(['services as bookable_count' => fn ($q) => $q->bookable()])
            ->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.categories.index', compact('categories'));
    }

    public function create()
    {
        return view('admin.categories.form', ['category' => new ServiceCategory(['is_active' => true])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $data['slug'] = $this->uniqueSlug($data['name']);
        if ($request->hasFile('image')) {
            $data['image'] = ImageService::store($request->file('image'), 'categories', self::IMAGE_WIDTH)['path'];
        }

        $category = ServiceCategory::create($data);
        SystemLog::record('service.category_created', "Category \"{$category->name}\" created", $category);

        return redirect()->route('admin.categories.index')->with('success', 'Category "' . $data['name'] . '" created.');
    }

    public function edit(ServiceCategory $category)
    {
        return view('admin.categories.form', compact('category'));
    }

    public function update(Request $request, ServiceCategory $category)
    {
        $data = $this->validated($request, $category);

        if ($data['name'] !== $category->name) {
            $data['slug'] = $this->uniqueSlug($data['name'], $category->id);
        }

        $old = $category->image;
        if ($request->boolean('remove_image')) {
            $data['image'] = null;
        }
        if ($request->hasFile('image')) {
            $data['image'] = ImageService::store($request->file('image'), 'categories', self::IMAGE_WIDTH)['path'];
        }

        $category->update($data);
        if ($old && $old !== $category->image) {
            ImageService::delete($old);
        }

        if ($changed = SystemLog::changes($category, ['slug'])) {
            SystemLog::record('service.category_updated', "Category \"{$category->name}\" updated", $category, $changed);
        }

        return redirect()->route('admin.categories.index')->with('success', 'Category "' . $category->name . '" updated.');
    }

    public function destroy(ServiceCategory $category)
    {
        // Keep the services and photos; they simply become uncategorised.
        $count = $category->services()->update(['service_category_id' => null]);
        ImageService::delete($category->image);
        SystemLog::record('service.category_deleted', "Category \"{$category->name}\" deleted ({$count} services now uncategorised)", $category, level: 'warning');
        $category->delete();

        return back()->with('success', 'Category "' . $category->name . '" deleted. Its services are now uncategorised.');
    }

    private function validated(Request $request, ?ServiceCategory $category = null): array
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:100', Rule::unique('service_categories', 'name')->ignore($category?->id)],
            'tagline'     => 'nullable|string|max:150',
            'icon'        => ['nullable', Rule::in(array_keys(ServiceCategory::ICONS))],
            'description' => 'nullable|string|max:500',
            'sort_order'  => 'nullable|integer|min:0|max:9999',
            'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:' . self::IMAGE_MAX_KB,
        ], [
            'image.max' => 'The photo must be ' . (self::IMAGE_MAX_KB / 1024) . ' MB or smaller.',
        ]);

        unset($data['image']);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        // The form always sends is_active (hidden 0 + checkbox); default to shown otherwise.
        $data['is_active'] = $request->has('is_active') ? $request->boolean('is_active') : ($category?->is_active ?? true);

        return $data;
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $i = 2;

        while (ServiceCategory::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
