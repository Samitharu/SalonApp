<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Slider;
use App\Models\SystemLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Homepage hero content. The first active slide (by sort order) is shown
 * as the hero on the homepage.
 */
class SliderController extends Controller
{
    public function index()
    {
        $sliders = Slider::orderBy('sort_order')->orderBy('id')->get();

        return view('admin.sliders.index', compact('sliders'));
    }

    public function create()
    {
        return view('admin.sliders.form', ['slider' => new Slider(['is_active' => true, 'button_link' => '/booking', 'button_text' => 'Book now'])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('sliders', 'public');
        }

        $slider = Slider::create($data);
        SystemLog::record('settings.slide_created', "Homepage slide \"{$slider->title}\" created", $slider);

        return redirect()->route('admin.sliders.index')->with('success', 'Hero slide created.');
    }

    public function edit(Slider $slider)
    {
        return view('admin.sliders.form', compact('slider'));
    }

    public function update(Request $request, Slider $slider)
    {
        $data = $this->validated($request);

        if ($request->boolean('remove_image') && $slider->image) {
            Storage::disk('public')->delete($slider->image);
            $data['image'] = null;
        }

        if ($request->hasFile('image')) {
            if ($slider->image) {
                Storage::disk('public')->delete($slider->image);
            }
            $data['image'] = $request->file('image')->store('sliders', 'public');
        }

        $slider->update($data);
        if ($changed = SystemLog::changes($slider)) {
            SystemLog::record('settings.slide_updated', "Homepage slide \"{$slider->title}\" updated", $slider, $changed);
        }

        return redirect()->route('admin.sliders.index')->with('success', 'Hero slide updated.');
    }

    public function destroy(Slider $slider)
    {
        if ($slider->image) {
            Storage::disk('public')->delete($slider->image);
        }

        SystemLog::record('settings.slide_deleted', "Homepage slide \"{$slider->title}\" deleted", $slider);
        $slider->delete();

        return back()->with('success', 'Hero slide deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title'       => 'required|string|max:150',
            'subtitle'    => 'nullable|string|max:200',
            'button_text' => 'nullable|string|max:30',
            // Internal paths ("/booking") or https links only — blocks javascript: URLs.
            'button_link' => ['nullable', 'string', 'max:255', 'regex:#^(/[^\s]*|https://[^\s]+)$#'],
            'sort_order'  => 'nullable|integer|min:0|max:9999',
            'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:3072',
        ], [
            'button_link.regex' => 'Use a site path like /booking or a full https:// link.',
        ]);

        unset($data['image']);
        $data['is_active']  = $request->boolean('is_active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }
}
