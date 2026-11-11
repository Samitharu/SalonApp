<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Append-only system log: who did what, when, from where.
 *
 *   SystemLog::record('appointment.cancelled', 'Cancelled booking AB12CD34', $appointment);
 *
 * The category is the part of the action before the first dot. The actor is
 * worked out from the current route and guards unless one is passed in.
 */
class SystemLog extends Model
{
    public const UPDATED_AT = null;

    public const LEVELS = ['info', 'warning', 'error', 'critical'];

    public const CATEGORIES = [
        'auth'        => 'Sign-in',
        'appointment' => 'Bookings',
        'offer'       => 'Offers',
        'payment'     => 'Payments',
        'feedback'    => 'Feedback',
        'customer'    => 'Customers',
        'staff'       => 'Staff',
        'user'        => 'Admin users',
        'service'     => 'Services',
        'stock'       => 'Stock',
        'gallery'     => 'Gallery',
        'settings'    => 'Settings',
        'system'      => 'System',
    ];

    public const ACTOR_TYPES = ['admin', 'staff', 'cashier', 'customer', 'guest', 'system'];

    /** Never written to the log, even as "changed from/to". */
    private const HIDDEN_FIELDS = ['password', 'remember_token', 'table_qr_token', 'cashier_pin_hash', 'pin', 'updated_at', 'created_at'];

    protected $fillable = [
        'level', 'category', 'action', 'description',
        'actor_type', 'actor_id', 'actor_name',
        'subject_type', 'subject_id', 'properties',
        'ip_address', 'user_agent',
    ];

    protected $casts = [
        'properties' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Write one entry. Returns null (and never throws) if logging fails, so a
     * logging problem can't break a booking or a payment.
     *
     * @param array{0:string,1:?int,2:?string}|null $actor [type, id, name] to override detection
     */
    public static function record(
        string $action,
        string $description,
        ?Model $subject = null,
        array $properties = [],
        string $level = 'info',
        ?array $actor = null,
    ): ?self {
        try {
            [$actorType, $actorId, $actorName] = $actor ?? static::currentActor();
            $request = app()->runningInConsole() && !app()->runningUnitTests() ? null : request();

            return static::create([
                'level'        => in_array($level, static::LEVELS, true) ? $level : 'info',
                'category'     => Str::before($action, '.'),
                'action'       => Str::limit($action, 60, ''),
                'description'  => Str::limit($description, 497),
                'actor_type'   => $actorType,
                'actor_id'     => $actorId,
                'actor_name'   => $actorName ? Str::limit($actorName, 120, '') : null,
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id'   => $subject?->getKey(),
                'properties'   => static::scrub($properties) ?: null,
                'ip_address'   => $request?->ip(),
                'user_agent'   => $request ? Str::limit((string) $request->userAgent(), 252) : null,
            ]);
        } catch (Throwable $e) {
            // Write to the log file only; the database channel may be what failed.
            Log::channel('single')->warning('System log write failed: ' . $e->getMessage(), ['action' => $action]);

            return null;
        }
    }

    /**
     * Who is acting in this request: [type, id, name].
     * The route decides which guard counts, so an admin who is also signed
     * in to the stylist portal in the same browser is credited correctly.
     */
    public static function currentActor(): array
    {
        if (app()->runningInConsole() && !app()->runningUnitTests()) {
            return ['system', null, 'System'];
        }

        $route = (string) optional(request()->route())->getName();
        $admin = Auth::guard('admin')->user();
        $staff = Auth::guard('staff')->user();

        if (str_starts_with($route, 'stylist.') && $staff) {
            return ['staff', $staff->id, $staff->name];
        }
        if ((str_starts_with($route, 'admin.') || str_starts_with($route, 'cashier.')) && $admin) {
            return ['admin', $admin->id, $admin->name];
        }
        if (str_starts_with($route, 'cashier.')) {
            return ['cashier', null, 'Cashier station'];
        }

        return ['guest', null, null];
    }

    /** Use the customer as the actor when nobody is signed in (public pages). */
    public static function customerActor(?Customer $customer): ?array
    {
        if (!$customer || static::currentActor()[0] !== 'guest') {
            return null;
        }

        return ['customer', $customer->id, $customer->name];
    }

    /**
     * Fields changed by the last save, as ['field' => ['from' => …, 'to' => …]].
     * Call right after save()/update().
     */
    public static function changes(Model $model, array $ignore = []): array
    {
        $previous = $model->getPrevious();
        $out = [];

        foreach ($model->getChanges() as $field => $new) {
            if (in_array($field, self::HIDDEN_FIELDS, true) || in_array($field, $ignore, true)) {
                continue;
            }
            $out[$field] = ['from' => $previous[$field] ?? null, 'to' => $new];
        }

        return $out;
    }

    /** Remove secrets at any depth. */
    private static function scrub(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::HIDDEN_FIELDS, true)) {
                unset($data[$key]);
            } elseif (is_array($value)) {
                $data[$key] = static::scrub($value);
            }
        }

        return $data;
    }

    // ── Display helpers ─────────────────────────────────────────────────

    public function categoryLabel(): string
    {
        return static::CATEGORIES[$this->category] ?? ucfirst($this->category);
    }

    public function actorLabel(): string
    {
        return match ($this->actor_type) {
            'admin'    => ($this->actor_name ?: 'Admin') . ' (admin)',
            'staff'    => ($this->actor_name ?: 'Stylist') . ' (stylist)',
            'cashier'  => 'Cashier station',
            'customer' => ($this->actor_name ?: 'Customer') . ' (customer)',
            'system'   => 'System',
            default    => 'Visitor',
        };
    }
}
