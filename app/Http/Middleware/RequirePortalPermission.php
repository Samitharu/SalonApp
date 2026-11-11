<?php

namespace App\Http\Middleware;

use App\Support\Permissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Route guard for the stylist portal: ->middleware('portal:portal.extras'). */
class RequirePortalPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        abort_unless($request->user('staff')?->portalCan($permission), 403,
            'You don’t have permission to “' . strtolower(Permissions::PORTAL[$permission][0]) . '”. Ask an admin if you need it.');

        return $next($request);
    }
}
