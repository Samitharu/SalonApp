<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.head', ['title' => trim($__env->yieldContent('heading')) . ' · ' . setting('salon_name', 'Salon')])
    <meta name="robots" content="noindex">
</head>
<body>
<main class="error-page">
    <div>
        <div class="error-code">@yield('code')</div>
        <h1>@yield('heading')</h1>
        <p>@yield('message')</p>
        <div class="row row-wrap">
            @hasSection('actions')
                @yield('actions')
            @else
                <a href="{{ url('/') }}" class="btn btn-primary">Go to homepage</a>
                <a href="{{ url('/booking') }}" class="btn">Book an appointment</a>
            @endif
        </div>
    </div>
</main>
<script src="{{ asset_v('js/app.js') }}" defer></script>
</body>
</html>
