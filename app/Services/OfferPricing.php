<?php

namespace App\Services;

use App\Models\Offer;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Which offer a service gets on a date. Holds what it loads for the request,
 * because a page of packages asks once per package.
 */
class OfferPricing
{
    private array $running = [];
    private ?Collection $showing = null;
    private ?Collection $ahead = null;

    public function flush(): void
    {
        $this->running = [];
        $this->showing = null;
        $this->ahead = null;
    }

    public function running(Carbon|string|null $date = null): Collection
    {
        $date = Carbon::parse($date ?? now())->toDateString();

        return $this->running[$date] ??= Offer::runningOn($date)->with('services:services.id')->get();
    }

    /** Offers to advertise today, soonest to end first, with the services customers can book. */
    public function showing(): Collection
    {
        return $this->showing ??= Offer::runningOn(now())
            ->with(['services' => fn ($q) => $q->bookable()->orderBy('name')])
            ->orderBy('ends_on')
            ->get()
            ->filter(fn (Offer $o) => $o->coversAll() || $o->services->isNotEmpty())
            ->values();
    }

    /** The offer that saves the customer most, if any. Add-ons are never discounted. */
    public function best(Service $service, Carbon|string|null $date = null): ?Offer
    {
        if ($service->is_addon) {
            return null;
        }

        return $this->running($date)
            ->filter(fn (Offer $o) => $o->coversAll() || $o->services->contains('id', $service->id))
            ->filter(fn (Offer $o) => $o->discountOn((float) $service->price) > 0)
            ->sortByDesc(fn (Offer $o) => $o->discountOn((float) $service->price))
            ->first();
    }

    /**
     * Offers a service gets from today up to a date, so the booking page can
     * price each date as the customer picks it.
     */
    public function windows(Service $service, Carbon|string $until): array
    {
        $this->ahead ??= Offer::where('is_active', true)
            ->whereDate('ends_on', '>=', now()->toDateString())
            ->whereDate('starts_on', '<=', Carbon::parse($until)->toDateString())
            ->with('services:services.id')
            ->orderBy('starts_on')
            ->get();

        return $this->ahead
            ->filter(fn (Offer $o) => !$service->is_addon && ($o->coversAll() || $o->services->contains('id', $service->id)))
            ->map(function (Offer $o) use ($service) {
                $amount = $o->discountOn((float) $service->price);

                return [
                    'from'   => $o->starts_on->toDateString(),
                    'to'     => $o->ends_on->toDateString(),
                    'name'   => $o->name,
                    'label'  => $o->label(),
                    'period' => $o->periodLabel(),
                    'amount' => $amount,
                    'price'  => money((float) $service->price - $amount),
                ];
            })
            ->filter(fn (array $w) => $w['amount'] > 0)
            ->values()
            ->all();
    }
}
