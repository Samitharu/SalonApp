<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class StaffAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('staff');

        // Deactivated staff lose portal access immediately.
        if ($guard->check() && !$guard->user()->is_active) {
            $guard->logout();
        }

        if (!$guard->check()) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Your session has expired. Please sign in again.'], 401);
            }

            return redirect()->guest('/stylist/login');
        }

        return $next($request);
    }
}
