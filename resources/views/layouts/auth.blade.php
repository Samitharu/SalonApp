<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => trim($__env->yieldContent('title')) . ' · ' . setting('salon_name', 'Salon')])
    <meta name="robots" content="noindex">
</head>
<body>
<main class="auth-page">
    <div class="auth-card">
        <div class="auth-head">
            <span class="brand-mark" aria-hidden="true">@yield('mark', mb_substr(setting('salon_name', 'S'), 0, 1))</span>
            <h1>@yield('heading')</h1>
            <p>@yield('subheading')</p>
        </div>
        <div class="card">
            @if(session('error'))
                <div class="alert alert-error mb-4" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><div>{{ session('error') }}</div></div>
            @endif
            @if(session('success'))
                <div class="alert alert-success mb-4"><i class="fa-solid fa-circle-check" aria-hidden="true"></i><div>{{ session('success') }}</div></div>
            @endif
            @if($errors->any())
                <div class="alert alert-error mb-4" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><div>{{ $errors->first() }}</div></div>
            @endif
            @yield('content')
        </div>
        <div class="auth-foot">
            <a href="{{ route('home') }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to website</a>
            @include('partials.theme-toggle', ['class' => 'btn-sm'])
        </div>
    </div>
</main>
<script src="{{ asset_v('js/app.js') }}" defer></script>
</body>
</html>
