<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin'   => \App\Http\Middleware\AdminAuth::class,
            'staff'   => \App\Http\Middleware\StaffAuth::class,
            'cashier' => \App\Http\Middleware\CashierAccess::class,
            'perm'    => \App\Http\Middleware\RequirePermission::class,
            'portal'  => \App\Http\Middleware\RequirePortalPermission::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Uploads bigger than PHP's post_max_size arrive with no fields at all;
        // send the admin back with a readable message instead of a 413 page.
        $exceptions->render(function (\Illuminate\Http\Exceptions\PostTooLargeException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson()) {
                return null;
            }

            return back()->with('error', 'Those files are too large to upload together (limit '
                . round(upload_limit('total') / 1048576) . ' MB). Try fewer or smaller photos.');
        });
    })->create();
