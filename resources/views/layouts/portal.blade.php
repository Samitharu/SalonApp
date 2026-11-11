<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => trim($__env->yieldContent('title')) . ' · ' . setting('salon_name', 'Salon')])
    <meta name="robots" content="noindex, nofollow">
    @stack('head')
</head>
<body>
<header class="portal-header">
    <div class="container">
        <span class="brand-mark" aria-hidden="true">@yield('mark', mb_substr(setting('salon_name', 'S'), 0, 1))</span>
        <div class="who grow">
            <strong class="truncate">@yield('who')</strong>
            <span>@yield('who_sub')</span>
        </div>
        <div class="row" style="gap:var(--s-1);">
            @yield('header_actions')
            @include('partials.theme-toggle', ['class' => 'btn-sm'])
        </div>
    </div>
</header>

<main class="portal-main">
    <div class="container @yield('container_class', 'container-md')">
        @yield('content')
    </div>
</main>

@include('partials.toasts')
<script src="{{ asset_v('js/app.js') }}" defer></script>
@stack('scripts')
</body>
</html>
