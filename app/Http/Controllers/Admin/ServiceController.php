<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ServiceController extends Controller
{
    public function index()
    {
        $services = Service::latest()->paginate(10);
        return view('admin.services.index', compact('services'));
    }

    public function create()
    {
        $categories = ServiceCategory::pluck('name', 'id');
        return view('admin.services.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|max:120',
            'price' => 'required|numeric',
            'duration_minutes' => 'required|integer|min:5',
        ]);

        Service::create([
            'service_category_id' => $request->service_category_id,
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'price' => $request->price,
            'duration_minutes' => $request->duration_minutes,
            'description' => $request->description,
        ]);

        return redirect()->route('admin.services.index')
            ->with('success', 'Service created');
    }

    public function edit(Service $service)
    {
        $categories = ServiceCategory::pluck('name', 'id');
        return view('admin.services.edit', compact('service', 'categories'));
    }

    public function update(Request $request, Service $service)
    {
        $request->validate([
            'name' => 'required|max:120',
        ]);

        $service->update($request->all());

        return redirect()->route('admin.services.index')
            ->with('success', 'Updated');
    }

    public function destroy(Service $service)
    {
        $service->delete();
        return back()->with('success', 'Deleted');
    }
}
