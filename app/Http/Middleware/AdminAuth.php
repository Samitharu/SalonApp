<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminAuth
{
    public const SESSION_KEY = 'admin_password_fingerprint';

    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('admin');
        $admin = $guard->user();

        // A deactivated user, or one whose password was changed elsewhere, is signed out at once.
        if ($admin) {
            $seen = $request->session()->get(self::SESSION_KEY);

            if (!$admin->is_active || ($seen && !hash_equals($seen, static::fingerprint($admin)))) {
                $request->session()->forget(self::SESSION_KEY);
                $guard->logout();
            } else {
                $request->session()->put(self::SESSION_KEY, static::fingerprint($admin));
            }
        }

        if (!$guard->check()) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Your session has expired. Please sign in again.'], 401);
            }

            return redirect()->guest('/admin/login');
        }

        return $next($request);
    }

    public static function fingerprint(Admin $admin): string
    {
        return hash('sha256', $admin->getAuthPassword());
    }
}
