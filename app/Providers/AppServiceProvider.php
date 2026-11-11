<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Schema::defaultStringLength(191);
        $this->app->singleton(\App\Services\OfferPricing::class);
    }

    public function boot(): void
    {
        // Behind ngrok / a load balancer: honour X-Forwarded-Proto so assets
        // and links use https:// (otherwise browsers block them as mixed content).
        if ($proxies = config('app.trusted_proxies')) {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        // @perm('stock.manage') … @endperm in admin views.
        Blade::if('perm', fn (string ...$permissions) => admin_can(...$permissions));

        Paginator::defaultView('partials.pagination');
        Paginator::defaultSimpleView('partials.pagination');

        RateLimiter::for('login', function (Request $request) {
            $key = strtolower((string) ($request->input('email') ?? $request->input('username') ?? 'pin'));

            return [
                Limit::perMinute(5)->by($key . '|' . $request->ip()),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });

        RateLimiter::for('booking', fn (Request $r) => [
            Limit::perMinute(6)->by($r->ip()),
            Limit::perDay(40)->by($r->ip()),
        ]);

        RateLimiter::for('slots', fn (Request $r) => Limit::perMinute(90)->by($r->ip()));
        RateLimiter::for('lookup', fn (Request $r) => Limit::perMinute(10)->by($r->ip()));
        RateLimiter::for('lookup-staff', fn (Request $r) => Limit::perMinute(60)->by($r->ip()));
    }
}
