<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\Service;
use App\Models\SystemLog;
use RuntimeException;

/**
 * Extra charges (add-ons) on an appointment, shared by admin, stylist and cashier.
 */
class AppointmentCharges
{
    public const RULES = [
        'service_id' => 'nullable|integer|exists:services,id',
        'name'       => 'required_without:service_id|nullable|string|max:120',
        'price'      => 'required_without:service_id|nullable|numeric|min:0|max:1000000',
        'quantity'   => 'nullable|integer|min:1|max:20',
    ];

    public const MESSAGES = [
        'name.required_without'  => 'Choose an item or enter a name for the charge.',
        'price.required_without' => 'Enter the amount for this charge.',
    ];

    /** Paid or cancelled bills can no longer change. */
    public static function isLocked(Appointment $a): bool
    {
        return $a->payment_status === 'paid' || in_array($a->status, ['cancelled', 'no_show'], true);
    }

    /**
     * Items that can be added: add-on only services first, then regular services.
     */
    public static function catalog(): array
    {
        return Service::where('is_active', 1)
            ->orderByDesc('is_addon')
            ->orderBy('name')
            ->get(['id', 'name', 'price', 'is_addon'])
            ->map(fn ($s) => [
                'id'          => $s->id,
                'name'        => $s->name,
                'price'       => (float) $s->price,
                'price_label' => money($s->price),
                'addon'       => (bool) $s->is_addon,
            ])
            ->all();
    }

    /**
     * @throws RuntimeException when the bill is locked or the service is unavailable.
     */
    public static function add(Appointment $a, array $data, string $by, ?string $byName = null): AppointmentItem
    {
        if (static::isLocked($a)) {
            throw new RuntimeException($a->payment_status === 'paid'
                ? 'This bill is already paid, so extras can no longer be added.'
                : 'Extras can’t be added to a cancelled appointment.');
        }

        $name  = isset($data['name']) ? trim((string) $data['name']) : null;
        $price = isset($data['price']) && $data['price'] !== '' ? (float) $data['price'] : null;
        $serviceId = null;

        if (!empty($data['service_id'])) {
            $service = Service::where('is_active', 1)->find($data['service_id']);
            if (!$service) {
                throw new RuntimeException('That item is no longer available.');
            }
            $serviceId = $service->id;
            $name  = $name ?: $service->name;
            $price = $price ?? (float) $service->price;
        }

        $quantity = max(1, (int) ($data['quantity'] ?? 1));
        $price    = round((float) $price, 2);

        // Adding the same menu item again bumps the quantity instead of a new line.
        if ($serviceId) {
            $existing = $a->items()->where('service_id', $serviceId)->where('price', $price)->where('name', $name)->first();
            if ($existing) {
                $existing->update(['quantity' => min(99, $existing->quantity + $quantity)]);
                static::log('payment.extra_added', $a, $name, $price, $quantity);

                return $existing;
            }
        }

        $item = $a->items()->create([
            'service_id'    => $serviceId,
            'name'          => $name,
            'price'         => $price,
            'quantity'      => $quantity,
            'added_by'      => $by,
            'added_by_name' => $byName,
        ]);

        static::log('payment.extra_added', $a, $name, $price, $quantity);

        return $item;
    }

    /** Callers must 404 before calling remove() when this is false. */
    public static function belongsTo(Appointment $a, AppointmentItem $item): bool
    {
        return (int) $item->appointment_id === (int) $a->id;
    }

    /**
     * @throws RuntimeException when the bill is locked or the item isn't on it.
     */
    public static function remove(Appointment $a, AppointmentItem $item): void
    {
        if (!static::belongsTo($a, $item)) {
            throw new RuntimeException('That item is not on this bill.');
        }

        if (static::isLocked($a)) {
            throw new RuntimeException('This bill is already paid, so extras can no longer be removed.');
        }

        $item->delete();

        static::log('payment.extra_removed', $a, $item->name, (float) $item->price, (int) $item->quantity);
    }

    private static function log(string $action, Appointment $a, string $name, float $price, int $quantity): void
    {
        $line = ($quantity > 1 ? "{$quantity} × " : '') . $name . ' (' . money($price * $quantity) . ')';

        SystemLog::record(
            $action,
            ($action === 'payment.extra_added' ? 'Added ' : 'Removed ') . $line . ($action === 'payment.extra_added' ? ' to' : ' from') . " the bill for {$a->appointment_no}",
            $a,
            ['item' => $name, 'price' => $price, 'quantity' => $quantity],
        );
    }

    /**
     * Bill breakdown for JSON responses (stylist portal, cashier).
     */
    public static function summary(Appointment $a): array
    {
        $a->loadMissing('service');
        $a->load('items'); // always fresh after add/remove
        $base = (float) ($a->service->price ?? 0);

        return [
            'id'           => $a->id,
            'service_name' => $a->service->name ?? '—',
            'base_price'   => $base,
            'base_label'   => money($base),
            'offer_name'   => $a->discount() > 0 ? $a->offer_name : null,
            'discount_label' => $a->discount() > 0 ? '− ' . money($a->discount()) : null,
            'items'        => $a->items->map(fn ($i) => [
                'id'         => $i->id,
                'name'       => $i->name,
                'quantity'   => $i->quantity,
                'price'      => (float) $i->price,
                'line_total' => $i->lineTotal(),
                'line_label' => money($i->lineTotal()),
                'added_by'   => $i->added_by_name ?: ucfirst((string) $i->added_by),
            ])->values()->all(),
            'extras_total' => $a->extrasTotal(),
            'extras_label' => money($a->extrasTotal()),
            'total'        => $a->total(),
            'total_label'  => money($a->total()),
            'locked'       => static::isLocked($a),
        ];
    }
}
