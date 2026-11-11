<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\SystemLog;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Stock: products the salon uses on clients, restocking, stock counts and history.
 */
class ProductController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => 'nullable|string|max:100',
            'status' => 'nullable|in:low,out',
            'archived' => 'nullable|boolean',
        ]);
        $archived = $request->boolean('archived');

        $products = Product::withCount('services')
            ->where('is_active', !$archived)
            ->when($filters['search'] ?? null, function ($q, $s) {
                $s = '%' . trim($s) . '%';
                $q->where(fn ($q) => $q->where('name', 'like', $s)->orWhere('brand', 'like', $s));
            })
            ->when(($filters['status'] ?? null) === 'out', fn ($q) => $q->where('stock', '<=', 0))
            ->when(($filters['status'] ?? null) === 'low', fn ($q) => $q->lowStock())
            // Problems first, then alphabetical.
            ->orderByRaw('CASE WHEN stock <= 0 THEN 0 WHEN low_stock_level IS NOT NULL AND stock <= low_stock_level THEN 1 ELSE 2 END')
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        $active = Product::where('is_active', 1);
        $summary = [
            'products' => (clone $active)->count(),
            'low'      => Product::lowStock()->where('stock', '>', 0)->count(),
            'out'      => (clone $active)->where('stock', '<=', 0)->count(),
            'value'    => (float) (clone $active)->where('stock', '>', 0)->whereNotNull('cost_per_unit')->sum(DB::raw('stock * cost_per_unit')),
            'archived' => Product::where('is_active', 0)->count(),
        ];

        return view('admin.products.index', compact('products', 'summary', 'filters', 'archived'));
    }

    public function create()
    {
        return view('admin.products.form', ['product' => new Product(['unit' => 'packet', 'uses_per_unit' => 1, 'is_active' => true])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $extra = $request->validate([
            'opening_stock' => 'nullable|numeric|min:0|max:9999999',
            'opening_cost'  => 'nullable|numeric|min:0|max:99999999',
        ]);

        $product = DB::transaction(function () use ($data, $extra) {
            $product = Product::create($data);
            InventoryService::open($product, (float) ($extra['opening_stock'] ?? 0), isset($extra['opening_cost']) ? (float) $extra['opening_cost'] : null);

            return $product;
        });

        SystemLog::record('stock.product_created', "Product {$product->displayName()} added with " . $product->qty($product->stock), $product,
            ['unit' => $product->unit, 'uses_per_unit' => $product->uses_per_unit, 'opening_stock' => $product->stock]);

        return redirect()->route('admin.products.index')->with('success', "{$product->name} added.");
    }

    public function show(Product $product)
    {
        $movements = $product->movements()->with('appointment:id,appointment_no')->latest('id')->paginate(40);
        $product->load(['services' => fn ($q) => $q->orderBy('name')]);

        $since = now()->subDays(30);
        $usedLast30 = -(float) $product->movements()->whereIn('type', ['usage', 'return'])->where('created_at', '>=', $since)->sum('quantity');

        return view('admin.products.show', compact('product', 'movements', 'usedLast30'));
    }

    public function edit(Product $product)
    {
        return view('admin.products.form', compact('product'));
    }

    public function update(Request $request, Product $product)
    {
        $product->update($this->validated($request, $product));

        if ($changed = SystemLog::changes($product)) {
            SystemLog::record('stock.product_updated', "Product {$product->displayName()} updated: " . implode(', ', array_keys($changed)), $product, $changed);
        }

        return redirect()->route('admin.products.index')->with('success', "{$product->name} updated.");
    }

    /**
     * Products with history are archived (so past usage still makes sense);
     * a product only ever given an opening stock can be deleted.
     */
    public function destroy(Product $product)
    {
        if ($product->movements()->where('type', '!=', 'opening')->exists()) {
            $product->update(['is_active' => false]);
            SystemLog::record('stock.product_archived', "Product {$product->displayName()} archived", $product);

            return back()->with('success', "{$product->name} has stock history, so it was archived instead of deleted. Find it under “Archived”.");
        }

        $links = $product->services()->count();
        SystemLog::record('stock.product_deleted', "Product {$product->displayName()} deleted" . ($links ? " (removed from {$links} services)" : ''), $product, [], 'warning');
        $product->delete();

        return back()->with('success', "{$product->name} deleted.");
    }

    public function restore(Product $product)
    {
        $product->update(['is_active' => true]);
        SystemLog::record('stock.product_updated', "Product {$product->displayName()} restored from the archive", $product);

        return back()->with('success', "{$product->name} is active again.");
    }

    // ── Restock ────────────────────────────────────────────────────────

    public function restockForm(Request $request)
    {
        $products = Product::where('is_active', 1)->orderBy('name')->get();
        $selected = $request->filled('product') ? $products->firstWhere('id', (int) $request->query('product')) : null;

        return view('admin.products.restock', compact('products', 'selected'));
    }

    public function restock(Request $request)
    {
        $data = $request->validate([
            'product_id'  => ['required', 'integer', Rule::exists('products', 'id')->where('is_active', 1)],
            'quantity'    => 'required|numeric|min:0.001|max:9999999',
            'total_cost'  => 'nullable|numeric|min:0|max:99999999',
            'supplier'    => 'nullable|string|max:120',
            'reference'   => 'nullable|string|max:60',
            'occurred_on' => 'nullable|date|before_or_equal:today|after:' . now()->subYears(2)->toDateString(),
            'note'        => 'nullable|string|max:255',
        ], [
            'product_id.required'        => 'Choose the product you bought.',
            'quantity.min'               => 'Enter how much you bought.',
            'occurred_on.before_or_equal' => 'The purchase date can’t be in the future.',
        ]);

        $product = Product::findOrFail($data['product_id']);
        InventoryService::restock($product, (float) $data['quantity'], isset($data['total_cost']) ? (float) $data['total_cost'] : null, $data);

        $message = 'Added ' . $product->qty((float) $data['quantity']) . " of {$product->name}. Stock is now " . $product->qty($product->stock) . '.';

        return $request->input('next') === 'another'
            ? redirect()->route('admin.products.restock')->with('success', $message)
            : redirect()->route('admin.products.index')->with('success', $message);
    }

    // ── Stock count / adjustment ───────────────────────────────────────

    public function adjustForm(Product $product)
    {
        return view('admin.products.adjust', compact('product'));
    }

    public function adjust(Request $request, Product $product)
    {
        $data = $request->validate([
            'counted' => 'required|numeric|min:0|max:9999999',
            'reason'  => ['required', Rule::in(array_keys(StockMovement::REASONS))],
            'note'    => 'nullable|string|max:255|required_if:reason,other',
        ], [
            'counted.required' => 'Enter how much is in stock now.',
            'note.required_if' => 'Add a short note explaining the change.',
        ]);

        $movement = InventoryService::adjust($product, (float) $data['counted'], $data['reason'], $data['note'] ?? null);

        return redirect()->route('admin.products.show', $product)->with('success', $movement
            ? "Stock of {$product->name} set to " . $product->qty($product->stock) . '.'
            : 'That’s the same as the current stock, so nothing changed.');
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $data = $request->validate([
            'name'            => ['required', 'string', 'max:120', Rule::unique('products')->where(fn ($q) => $q->where('brand', $request->input('brand')))->ignore($product?->id)],
            'brand'           => 'nullable|string|max:120',
            'unit'            => ['required', Rule::in(array_keys(Product::UNITS))],
            'uses_per_unit'   => 'required|numeric|min:1|max:1000',
            'low_stock_level' => 'nullable|numeric|min:0|max:9999999',
            'notes'           => 'nullable|string|max:1000',
        ], [
            'name.unique'       => 'This product (with this brand) already exists.',
            'uses_per_unit.min' => 'Use 1 when one unit is used per client (or when you measure in ml or g).',
        ]);

        $data['is_active'] = $product ? $request->boolean('is_active', true) : true;

        return $data;
    }
}
