<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\SystemLog;
use App\Services\OfferPricing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OfferController extends Controller
{
    private const STATUS_ORDER = ['running' => 0, 'upcoming' => 1, 'paused' => 2, 'ended' => 3];

    public function index()
    {
        $counted = fn ($q) => $q->whereNotIn('status', ['cancelled', 'no_show']);

        $offers = Offer::withCount(['services', 'appointments as bookings_count' => $counted])
            ->withSum(['appointments as discount_given' => $counted], 'discount_amount')
            ->orderByDesc('ends_on')
            ->get()
            ->sortBy(fn (Offer $o) => self::STATUS_ORDER[$o->status()])
            ->values();

        return view('admin.offers.index', compact('offers'));
    }

    public function create()
    {
        return view('admin.offers.form', ['offer' => new Offer()] + $this->formData());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $offer = DB::transaction(function () use ($data) {
            $offer = Offer::create($data);
            $offer->services()->sync($data['applies_to'] === 'selected' ? $data['services'] : []);

            return $offer;
        });
        app(OfferPricing::class)->flush();

        SystemLog::record('offer.created', "Offer \"{$offer->name}\" created: {$offer->label()}, {$offer->periodLabel()}", $offer,
            ['discount' => $offer->label(), 'period' => $offer->periodLabel(), 'applies_to' => $offer->applies_to]);

        return redirect()->route('admin.offers.index')->with('success', "“{$offer->name}” saved. It applies to visits from {$offer->periodLabel()}.");
    }

    public function edit(Offer $offer)
    {
        return view('admin.offers.form', ['offer' => $offer->load('services:services.id')] + $this->formData());
    }

    public function update(Request $request, Offer $offer)
    {
        $data = $this->validated($request, $offer);

        $synced = DB::transaction(function () use ($offer, $data) {
            $offer->update($data);

            return $offer->services()->sync($data['applies_to'] === 'selected' ? $data['services'] : []);
        });
        app(OfferPricing::class)->flush();

        $changed = SystemLog::changes($offer);
        if ($synced['attached'] || $synced['detached']) {
            $changed['services'] = ['added' => count($synced['attached']), 'removed' => count($synced['detached'])];
        }
        if ($changed) {
            SystemLog::record('offer.updated', "Offer \"{$offer->name}\" updated: " . implode(', ', array_keys($changed)), $offer, $changed);
        }

        return redirect()->route('admin.offers.index')->with('success', "“{$offer->name}” updated. Bookings already made keep their price.");
    }

    public function destroy(Offer $offer)
    {
        SystemLog::record('offer.deleted', "Offer \"{$offer->name}\" deleted ({$offer->label()}, {$offer->periodLabel()})", $offer, level: 'warning');
        $offer->delete();

        return back()->with('success', "“{$offer->name}” was deleted. Bookings already made keep their price.");
    }

    /** Bookable services under their categories, for ticking. */
    private function formData(): array
    {
        $groups = ServiceCategory::with(['services' => fn ($q) => $q->bookable()->orderBy('name')])
            ->orderBy('sort_order')->orderBy('name')->get()
            ->filter(fn ($c) => $c->services->isNotEmpty())
            ->mapWithKeys(fn ($c) => [$c->name => $c->services]);

        $other = Service::bookable()->whereNull('service_category_id')->orderBy('name')->get();
        if ($other->isNotEmpty()) {
            $groups[$groups->isEmpty() ? 'Services' : 'Other'] = $other;
        }

        return ['groups' => $groups];
    }

    private function validated(Request $request, ?Offer $offer = null): array
    {
        $data = $request->validate([
            'name'           => 'required|string|max:120',
            'description'    => 'nullable|string|max:500',
            'discount_type'  => 'required|in:percent,fixed',
            'discount_value' => 'required|numeric|gt:0|max:9999999',
            'starts_on'      => 'required|date_format:Y-m-d',
            // A new offer has to be able to run. An old one can still be corrected.
            'ends_on'        => array_merge(['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'], $offer ? [] : ['after_or_equal:today']),
            'applies_to'     => 'required|in:all,selected',
            'services'       => 'required_if:applies_to,selected|array',
            'services.*'     => ['integer', Rule::exists('services', 'id')->where('is_addon', 0)],
        ], [
            'discount_value.gt'         => 'Enter a discount above zero.',
            'ends_on.after_or_equal'    => 'The last day can’t be before the first day or in the past.',
            'services.required_if'      => 'Tick at least one service, or apply the offer to all services.',
        ]);

        if ($data['discount_type'] === 'percent' && (float) $data['discount_value'] > 100) {
            throw ValidationException::withMessages(['discount_value' => 'A percentage discount can’t be more than 100%.']);
        }

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
