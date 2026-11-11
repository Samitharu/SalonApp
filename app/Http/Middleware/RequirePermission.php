<?php

namespace App\Http\Middleware;

use App\Models\SystemLog;
use App\Support\Permissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route guard: ->middleware('perm:stock.manage'). Several names mean "any of".
 * Checked on every request, so changes to someone's access apply at once.
 */
class RequirePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $admin = $request->user('admin');

        if (!$admin || !$admin->hasAnyPermission($permissions)) {
            SystemLog::record('auth.permission_denied', ($admin->name ?? 'Someone') . ' was refused: ' . $request->method() . ' /' . $request->path(), null,
                ['needs' => $permissions], 'warning');

            abort(403, 'You don’t have the “' . Permissions::label($permissions[0]) . '” permission. Ask the salon owner if you need it.');
        }

        return $next($request);
    }
}
