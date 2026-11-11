@php
    $salon = setting('salon_name', 'Salon');
    $admin = auth('admin')->user();
    $pendingBadge = admin_can('appointments.view') ? \App\Models\Appointment::where('status', 'pending')->whereDate('appointment_date', '>=', now()->toDateString())->count() : 0;
    $lowStockBadge = admin_can('stock.view') ? \App\Models\Product::lowStock()->count() : 0;
    $nav = function (string $pattern) { return request()->routeIs($pattern) ? 'aria-current=page' : ''; };
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => trim($__env->yieldContent('title', 'Admin')) . ' · ' . $salon . ' Admin'])
    <meta name="robots" content="noindex, nofollow">
    @stack('head')
</head>
<body>
<a class="skip-link" href="#content">Skip to content</a>

<div class="app-shell">
    <aside class="sidebar" id="sidebar" data-drawer aria-label="Admin navigation">
        <div class="sidebar-brand">
            <span class="brand-mark" aria-hidden="true">{{ mb_substr($salon, 0, 1) }}</span>
            <div class="truncate">
                <span class="brand-name truncate">{{ $salon }}</span>
                <small>Admin</small>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section">Overview</div>
            <a href="{{ route('admin.dashboard') }}" class="nav-item" {{ $nav('admin.dashboard') }}><i class="fa-solid fa-house" aria-hidden="true"></i> Dashboard</a>
            @perm('appointments.view')<a href="{{ route('admin.schedule') }}" class="nav-item" {{ $nav('admin.schedule') }}><i class="fa-solid fa-calendar-day" aria-hidden="true"></i> Schedule</a>@endperm
            @perm('reports.view')<a href="{{ route('admin.reports') }}" class="nav-item" {{ $nav('admin.reports*') }}><i class="fa-solid fa-chart-column" aria-hidden="true"></i> Reports</a>@endperm

            @perm('appointments.view', 'customers.view', 'feedback.manage')<div class="nav-section">Bookings</div>@endperm
            @perm('appointments.view')
            <a href="{{ route('admin.appointments.index') }}" class="nav-item" {{ $nav('admin.appointments.*') }}>
                <i class="fa-solid fa-calendar-check" aria-hidden="true"></i> Appointments
                @if($pendingBadge)<span class="count" title="{{ $pendingBadge }} pending">{{ $pendingBadge }}</span>@endif
            </a>
            @endperm
            @perm('customers.view')<a href="{{ route('admin.customers.index') }}" class="nav-item" {{ $nav('admin.customers.*') }}><i class="fa-solid fa-user-group" aria-hidden="true"></i> Customers</a>@endperm
            @perm('feedback.manage')<a href="{{ route('admin.feedback.index') }}" class="nav-item" {{ $nav('admin.feedback.*') }}><i class="fa-regular fa-star" aria-hidden="true"></i> Feedback</a>@endperm

            @perm('services.manage', 'offers.manage', 'stock.view')<div class="nav-section">Catalog</div>@endperm
            @perm('services.manage')
            <a href="{{ route('admin.services.index') }}" class="nav-item" {{ $nav('admin.services.*') }}><i class="fa-solid fa-scissors" aria-hidden="true"></i> Services</a>
            <a href="{{ route('admin.categories.index') }}" class="nav-item" {{ $nav('admin.categories.*') }}><i class="fa-solid fa-layer-group" aria-hidden="true"></i> Categories</a>
            @endperm
            @perm('offers.manage')<a href="{{ route('admin.offers.index') }}" class="nav-item" {{ $nav('admin.offers.*') }}><i class="fa-solid fa-tags" aria-hidden="true"></i> Offers</a>@endperm
            @perm('stock.view')
            <a href="{{ route('admin.products.index') }}" class="nav-item" {{ $nav('admin.products.*') }}>
                <i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i> Stock
                @if($lowStockBadge)<span class="count count-warn" title="{{ $lowStockBadge }} low or out of stock">{{ $lowStockBadge }}</span>@endif
            </a>
            @endperm

            @perm('staff.manage', 'users.manage')<div class="nav-section">Team</div>@endperm
            @perm('staff.manage')<a href="{{ route('admin.employees.index') }}" class="nav-item" {{ $nav('admin.employees.*') }}><i class="fa-solid fa-id-badge" aria-hidden="true"></i> Staff</a>@endperm
            @perm('users.manage')<a href="{{ route('admin.users.index') }}" class="nav-item" {{ $nav('admin.users.*') }}><i class="fa-solid fa-user-shield" aria-hidden="true"></i> Admin users</a>@endperm

            @perm('hours.manage', 'website.manage', 'settings.manage', 'logs.view')<div class="nav-section">Business</div>@endperm
            @perm('hours.manage')<a href="{{ route('admin.hours.index') }}" class="nav-item" {{ $nav('admin.hours.*') }}><i class="fa-regular fa-clock" aria-hidden="true"></i> Opening hours</a>@endperm
            @perm('website.manage')
            <a href="{{ route('admin.sliders.index') }}" class="nav-item" {{ $nav('admin.sliders.*') }}><i class="fa-regular fa-image" aria-hidden="true"></i> Homepage hero</a>
            <a href="{{ route('admin.gallery.index') }}" class="nav-item" {{ $nav('admin.gallery.*') }}><i class="fa-regular fa-images" aria-hidden="true"></i> Gallery</a>
            @endperm
            @perm('settings.manage')<a href="{{ route('admin.settings.index') }}" class="nav-item" {{ $nav('admin.settings.*') }}><i class="fa-solid fa-gear" aria-hidden="true"></i> Settings</a>@endperm
            @perm('logs.view')<a href="{{ route('admin.logs.index') }}" class="nav-item" {{ $nav('admin.logs.*') }}><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> System log</a>@endperm

            <div class="nav-section">Stations</div>
            @perm('payments.take')<a href="{{ route('cashier.index') }}" class="nav-item" target="_blank" rel="noopener"><i class="fa-solid fa-cash-register" aria-hidden="true"></i> Cashier <i class="fa-solid fa-arrow-up-right-from-square ext" aria-hidden="true"></i></a>@endperm
            <a href="{{ route('stylist.login') }}" class="nav-item" target="_blank" rel="noopener"><i class="fa-solid fa-mobile-screen" aria-hidden="true"></i> Stylist portal <i class="fa-solid fa-arrow-up-right-from-square ext" aria-hidden="true"></i></a>
        </nav>

        <div class="sidebar-foot">
            <a href="{{ route('admin.account.edit') }}" class="user-chip">
                <span class="avatar avatar-sm" aria-hidden="true">{{ initials($admin->name ?? 'A') }}</span>
                <span class="grow">
                    <span class="name truncate" style="display:block;">{{ $admin->name ?? 'Admin' }}</span>
                    <span class="role">{{ $admin->roleLabel() }} · My account</span>
                </span>
            </a>
        </div>
    </aside>
    <div class="sidebar-backdrop" id="sidebar-backdrop" data-drawer-backdrop="sidebar"></div>

    <div class="main">
        <header class="topbar">
            <button type="button" class="btn btn-ghost btn-icon sidebar-toggle" data-toggle-target="sidebar" aria-controls="sidebar" aria-expanded="false" aria-label="Open navigation">
                <i class="fa-solid fa-bars" aria-hidden="true"></i>
            </button>
            <div class="grow">
                @hasSection('crumb')<div class="topbar-crumb">@yield('crumb')</div>@endif
                <div class="topbar-title truncate">@yield('title', 'Dashboard')</div>
            </div>
            <div class="topbar-actions">
                @perm('appointments.manage')
                <a href="{{ route('admin.appointments.create', ['walkin' => 1]) }}" class="btn btn-sm hide-sm"><i class="fa-solid fa-person-walking" aria-hidden="true"></i> Walk-in</a>
                <a href="{{ route('admin.appointments.create') }}" class="btn btn-primary btn-sm hide-sm"><i class="fa-solid fa-plus" aria-hidden="true"></i> New booking</a>
                <a href="{{ route('admin.appointments.create') }}" class="btn btn-primary btn-icon btn-sm show-sm" aria-label="New booking" title="New booking"><i class="fa-solid fa-plus" aria-hidden="true"></i></a>
                @endperm
                <a href="{{ route('home') }}" target="_blank" rel="noopener" class="btn btn-ghost btn-icon btn-sm" aria-label="View website" title="View website"><i class="fa-solid fa-globe" aria-hidden="true"></i></a>
                @include('partials.theme-toggle', ['class' => 'btn-sm'])
                <form method="POST" action="{{ route('admin.logout') }}" data-no-lock>
                    @csrf
                    <button type="submit" class="btn btn-ghost btn-icon btn-sm" aria-label="Sign out" title="Sign out"><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i></button>
                </form>
            </div>
        </header>

        <main class="content" id="content">
            @yield('content')
        </main>
    </div>
</div>

@include('partials.toasts')
<script src="{{ asset_v('js/app.js') }}" defer></script>
@stack('scripts')
</body>
</html>
