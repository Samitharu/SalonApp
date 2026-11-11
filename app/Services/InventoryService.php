<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\SystemLog;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The only place stock changes. Each change locks the product row, updates
 * the cached balance and writes a StockMovement, so the balance can always
 * be explained line by line.
 *
 * Usage is tied to appointments: completing a visit uses the products its
 * service (and any extras) need; un-completing it returns them. The sync is
 * idempotent — it compares what the visit should have used with what was
 * already recorded — so it can never deduct twice.
 */
class InventoryService
{
    private const EPSILON = 0.0005;

    public static function open(Product $product, float $qty, ?float $totalCost = null): ?StockMovement
    {
        if ($qty <= self::EPSILON) {
            return null;
        }

        return static::move($product, 'opening', $qty, static::costs($qty, $totalCost) + ['occurred_on' => now()->toDateString()]);
    }

    /**
     * @param array{supplier?: ?string, reference?: ?string, occurred_on?: ?string, note?: ?string} $meta
     */
    public static function restock(Product $product, float $qty, ?float $totalCost, array $meta = []): StockMovement
    {
        if ($qty <= self::EPSILON) {
            throw new RuntimeException('Enter how much was bought.');
        }

        $movement = static::move($product, 'purchase', $qty, static::costs($qty, $totalCost) + [
            'supplier'    => $meta['supplier'] ?? null,
            'reference'   => $meta['reference'] ?? null,
            'occurred_on' => $meta['occurred_on'] ?? now()->toDateString(),
            'note'        => $meta['note'] ?? null,
        ]);

        SystemLog::record('stock.restocked', "Restocked {$product->displayName()}: +" . $product->qty($qty)
            . ($totalCost ? ' for ' . money($totalCost) : '') . ' (now ' . $product->qty($product->stock) . ')', $product,
            ['quantity' => $qty, 'total_cost' => $totalCost, 'supplier' => $meta['supplier'] ?? null, 'reference' => $meta['reference'] ?? null]);

        return $movement;
    }

    /** Set stock to what's actually on the shelf. Returns null when nothing changed. */
    public static function adjust(Product $product, float $counted, string $reason, ?string $note = null): ?StockMovement
    {
        $before = (float) $product->fresh()->stock;
        $delta = round($counted - $before, 3);
        if (abs($delta) < self::EPSILON) {
            return null;
        }

        $movement = static::move($product, 'adjustment', $delta, ['reason' => $reason, 'note' => $note, 'occurred_on' => now()->toDateString()]);

        SystemLog::record('stock.adjusted', "Stock of {$product->displayName()} adjusted from " . Product::number($before) . ' to ' . $product->qty($counted)
            . ' (' . (StockMovement::REASONS[$reason] ?? $reason) . ')', $product,
            ['from' => $before, 'to' => $counted, 'reason' => $reason, 'note' => $note], $delta < 0 ? 'warning' : 'info');

        return $movement;
    }

    /**
     * Bring an appointment's recorded usage in line with what it should have
     * used: its service plus extras when completed, nothing otherwise.
     * Never throws — a stock problem must not stop a payment or a completion.
     */
    public static function syncAppointment(Appointment $appointment): void
    {
        try {
            DB::transaction(fn () => static::doSync($appointment));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** product_id => quantity this appointment should have used. */
    public static function requiredFor(Appointment $appointment): array
    {
        if ($appointment->status !== 'completed') {
            return [];
        }

        $lines = [[$appointment->service_id, 1]];
        foreach (AppointmentItem::where('appointment_id', $appointment->id)->whereNotNull('service_id')->get(['service_id', 'quantity']) as $item) {
            $lines[] = [$item->service_id, max(1, (int) $item->quantity)];
        }

        $recipes = DB::table('product_service')->whereIn('service_id', array_unique(array_column($lines, 0)))
            ->get(['service_id', 'product_id', 'quantity'])->groupBy('service_id');

        $need = [];
        foreach ($lines as [$serviceId, $times]) {
            foreach ($recipes[$serviceId] ?? [] as $r) {
                $need[$r->product_id] = round(($need[$r->product_id] ?? 0) + (float) $r->quantity * $times, 3);
            }
        }

        return $need;
    }

    private static function doSync(Appointment $appointment): void
    {
        $need = static::requiredFor($appointment);

        $used = StockMovement::where('appointment_id', $appointment->id)
            ->whereIn('type', ['usage', 'return'])
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity) as net')
            ->pluck('net', 'product_id')
            ->map(fn ($net) => -(float) $net);

        $ids = array_unique(array_merge(array_keys($need), $used->keys()->all()));
        if (!$ids) {
            return;
        }

        $products = Product::whereIn('id', $ids)->get()->keyBy('id');
        foreach ($ids as $id) {
            $delta = round(($need[$id] ?? 0) - (float) ($used[$id] ?? 0), 3);
            if (abs($delta) < self::EPSILON || !isset($products[$id])) {
                continue;
            }

            static::move($products[$id], $delta > 0 ? 'usage' : 'return', -$delta, [
                'appointment_id' => $appointment->id,
                'reference'      => $appointment->appointment_no,
                'occurred_on'    => now()->toDateString(),
            ]);
        }
    }

    private static function move(Product $product, string $type, float $qty, array $attrs = []): StockMovement
    {
        return DB::transaction(function () use ($product, $type, $qty, $attrs) {
            $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $before = (float) $locked->stock;
            $after = round($before + $qty, 3);

            $locked->stock = $after;
            if ($type === 'purchase' || $type === 'opening') {
                if (!empty($attrs['unit_cost'])) {
                    $locked->cost_per_unit = $attrs['unit_cost'];
                }
            }
            $locked->save();

            [$byType, $byId, $byName] = SystemLog::currentActor();
            $movement = $locked->movements()->create($attrs + [
                'type'            => $type,
                'quantity'        => round($qty, 3),
                'balance_after'   => $after,
                'created_by_type' => $byType,
                'created_by_id'   => $byId,
                'created_by_name' => $byName,
            ]);

            // Keep the caller's model in step with the database.
            $product->setRawAttributes($locked->getAttributes(), true);

            // Crossing into low/out of stock leaves a warning in the System log.
            $was = $locked->stockStatus($before);
            $now = $locked->stockStatus($after);
            if ($qty < 0 && $now !== 'ok' && $was !== $now && $locked->is_active) {
                SystemLog::record('stock.low', $now === 'out'
                    ? "{$locked->displayName()} is out of stock (" . $locked->qty($after) . ')'
                    : "{$locked->displayName()} is running low (" . $locked->qty($after) . ' left)', $locked,
                    ['stock' => $after, 'alert_level' => $locked->low_stock_level], 'warning');
            }

            return $movement;
        });
    }

    private static function costs(float $qty, ?float $totalCost): array
    {
        if (!$totalCost || $totalCost <= 0) {
            return [];
        }

        return ['total_cost' => round($totalCost, 2), 'unit_cost' => round($totalCost / $qty, 4)];
    }
}
