<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // Camera is used by the stylist and cashier QR scanners on this origin only.
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(), payment=()');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Authenticated areas must never be served from a shared cache.
        if ($request->is('admin', 'admin/*', 'stylist/*', 'cashier', 'cashier/*', 'booking/manage*')) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
