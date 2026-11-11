<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The cashier station is open to signed-in admins, or to a device that has
 * been unlocked with the station PIN (set in Admin → Settings).
 */
class CashierAccess
{
    public const SESSION_KEY = 'cashier_unlocked_at';
    public const TTL_HOURS = 12;

    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('admin')->user()?->hasPermission('payments.take')) {
            return $next($request);
        }

        $unlockedAt = (int) $request->session()->get(self::SESSION_KEY, 0);

        if ($unlockedAt && $unlockedAt > now()->subHours(self::TTL_HOURS)->timestamp) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['error' => 'Cashier station is locked. Reload the page and enter the PIN.'], 401);
        }

        return redirect()->route('cashier.unlock');
    }
}
