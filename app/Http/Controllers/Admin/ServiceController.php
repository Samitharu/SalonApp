<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\SystemLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'category' => 'nullable|integer',
            'search'   => 'nullable|string|max:100',
        ]);

        $services = Service::with('category')
            ->withCount('appointments')
            ->when($request->filled('category'), fn ($q) => $q->where('service_category_id', $request->category))
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%' . trim($request->search) . '%'))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $categories = ServiceCategory::orderBy('name')->get(['id', 'name']);

        return view('admin.services.index', compact('services', 'categories'));
    }

    public function create()
    {
        return view('admin.services.form', [
            'service'    => new Service(['is_active' => true, 'duration_minutes' => 30]),
            'categories' => ServiceCategory::orderBy('name')->get(),
            'products'   => $this->productOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $recipe = $this->validatedRecipe($request);
        $data['slug'] = Service::uniqueSlug($data['name']);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('services', 'public');
        }

        $service = DB::transaction(function () use ($data, $recipe) {
            $service = Service::create($data);
            $service->products()->sync($recipe);

            return $service;
        });
        SystemLog::record('service.created', "Service \"{$service->name}\" created (" . money($service->price) . ", {$service->duration_minutes} min)", $service,
            $recipe ? ['products' => $this->recipeSummary($recipe)] : []);

        return redirect()->route('admin.services.index')->with('success', 'Service "' . $data['name'] . '" created.');
    }

    public function edit(Service $service)
    {
        $service->load('products');

        return view('admin.services.form', [
            'service'    => $service,
            'categories' => ServiceCategory::orderBy('name')->get(),
            'products'   => $this->productOptions($service->products->pluck('id')->all()),
        ]);
    }

    public function update(Request $request, Service $service)
    {
        $data = $this->validated($request);
        $recipe = $this->validatedRecipe($request);

        if ($data['name'] !== $service->name) {
            $data['slug'] = Service::uniqueSlug($data['name'], $service->id);
        }

        if ($request->boolean('remove_image') && $service->image) {
            Storage::disk('public')->delete($service->image);
            $data['image'] = null;
        }

        if ($request->hasFile('image')) {
            if ($service->image) {
                Storage::disk('public')->delete($service->image);
            }
            $data['image'] = $request->file('image')->store('services', 'public');
        }

        $before = $this->recipeSummary($service->products()->get()->mapWithKeys(fn ($p) => [$p->id => ['quantity' => (float) $p->pivot->quantity]])->all());
        DB::transaction(function () use ($service, $data, $recipe) {
            $service->update($data);
            $service->products()->sync($recipe);
        });

        $changed = SystemLog::changes($service, ['slug']);
        $after = $this->recipeSummary($recipe);
        if ($before !== $after) {
            $changed['products'] = ['from' => $before ?: null, 'to' => $after ?: null];
        }
        if ($changed) {
            SystemLog::record('service.updated', "Service \"{$service->name}\" updated: " . implode(', ', array_keys($changed)), $service, $changed);
        }

        return redirect()->route('admin.services.index')->with('success', 'Service "' . $service->name . '" updated.');
    }

    public function destroy(Service $service)
    {
        // Deleting a service would cascade-delete its booking history, so
        // services that have been booked are archived instead.
        if ($service->appointments()->exists()) {
            $service->update(['is_active' => false]);
            SystemLog::record('service.archived', "Service \"{$service->name}\" hidden (it has bookings, so it can't be deleted)", $service);

            return back()->with('success', 'Service "' . $service->name . '" has bookings, so it was hidden instead of deleted.');
        }

        if ($service->image) {
            Storage::disk('public')->delete($service->image);
        }

        SystemLog::record('service.deleted', "Service \"{$service->name}\" deleted", $service, ['price' => $service->price, 'duration' => $service->duration_minutes], 'warning');
        $service->delete();

        return back()->with('success', 'Service "' . $service->name . '" deleted.');
    }

    /** Active products, plus any archived ones this service still uses. */
    private function productOptions(array $linked = [])
    {
        return Product::where('is_active', 1)->when($linked, fn ($q) => $q->orWhereIn('id', $linked))->orderBy('name')->get();
    }

    /**
     * "Products used per client" rows → [product_id => ['quantity' => x]].
     * Fully blank rows are ignored; half-filled ones are errors.
     */
    private function validatedRecipe(Request $request): array
    {
        $rows = collect($request->input('products', []))
            ->filter(fn ($r) => is_array($r) && (trim((string) ($r['id'] ?? '')) !== '' || trim((string) ($r['qty'] ?? '')) !== ''))
            ->values()
            ->all();
        $request->merge(['products' => $rows]);

        $request->validate([
            'products'       => 'array|max:30',
            'products.*.id'  => ['required', 'integer', 'distinct', Rule::exists('products', 'id')],
            'products.*.qty' => 'required|numeric|min:0.001|max:99999',
        ], [
            'products.max'            => 'A service can use up to 30 products.',
            'products.*.id.required'  => 'Choose a product, or remove this row.',
            'products.*.id.distinct'  => 'This product is listed twice.',
            'products.*.id.exists'    => 'This product no longer exists.',
            'products.*.qty.required' => 'Enter how much one client uses.',
            'products.*.qty.min'      => 'Enter how much one client uses.',
        ]);

        return collect($rows)->mapWithKeys(fn ($r) => [(int) $r['id'] => ['quantity' => round((float) $r['qty'], 3)]])->all();
    }

    /** "Gold facial pack: 0.5 packet" lines for the System log. */
    private function recipeSummary(array $recipe): array
    {
        if (!$recipe) {
            return [];
        }
        $products = Product::whereIn('id', array_keys($recipe))->get()->keyBy('id');

        return collect($recipe)->map(fn ($r, $id) => isset($products[$id]) ? $products[$id]->displayName() . ': ' . $products[$id]->qty($r['quantity']) : null)
            ->filter()->sort()->values()->all();
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name'                => 'required|string|max:120',
            'service_category_id' => 'nullable|integer|exists:service_categories,id',
            'price'               => 'required|numeric|min:0|max:9999999',
            'duration_minutes'    => 'required|integer|min:5|max:720',
            'buffer_minutes'      => 'nullable|integer|in:0,5,10,15,20,30,45,60',
            'description'         => 'nullable|string|max:1000',
            'sort_order'          => 'nullable|integer|min:0|max:9999',
            'image'               => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'advance_type'        => 'nullable|in:none,fixed,percent',
            'advance_value'       => [
                'nullable', 'numeric', 'min:0',
                Rule::requiredIf(fn () => in_array($request->input('advance_type'), ['fixed', 'percent'], true)),
                $request->input('advance_type') === 'percent' ? 'max:100' : 'max:9999999',
            ],
        ], [
            'advance_value.required' => 'Enter the advance amount or percentage.',
            'advance_value.max'      => $request->input('advance_type') === 'percent' ? 'A percentage can’t be more than 100.' : 'That amount is too large.',
        ]);

        unset($data['image']);
        $data['advance_type'] = $data['advance_type'] ?? 'none';
        $data['advance_value'] = $data['advance_type'] === 'none' ? 0 : round((float) ($data['advance_value'] ?? 0), 2);
        if ($data['advance_type'] === 'fixed' && $data['advance_value'] > (float) $data['price']) {
            throw \Illuminate\Validation\ValidationException::withMessages(['advance_value' => 'The advance can’t be more than the price.']);
        }
        $data['is_active']  = $request->boolean('is_active');
        $data['is_addon']   = $request->boolean('is_addon');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['buffer_minutes'] = isset($data['buffer_minutes']) && $data['buffer_minutes'] !== '' ? (int) $data['buffer_minutes'] : null;

        return $data;
    }
}
