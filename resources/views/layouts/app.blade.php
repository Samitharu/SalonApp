@php
    $salon = setting('salon_name', 'Salon');
    $pageTitle = trim($__env->yieldContent('title'));
    $metaDescription = trim($__env->yieldContent('meta_description')) ?: setting('meta_description', setting('salon_tagline', 'Book your next salon appointment online.'));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => $pageTitle ? $pageTitle . ' · ' . $salon : $salon])
    <meta name="description" content="{{ $metaDescription }}">
    <meta property="og:title" content="{{ $pageTitle ?: $salon }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <link rel="canonical" href="{{ url()->current() }}">
    @stack('head')
</head>
<body class="site">
<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header">
    <div class="container">
        <a class="brand brand-wordmark" href="{{ route('home') }}" aria-label="{{ $salon }} home">
            <span class="brand-name">{{ $salon }}</span>
        </a>

        @php $hasOffers = app(\App\Services\OfferPricing::class)->showing()->isNotEmpty(); @endphp
        <nav class="site-nav" aria-label="Main">
            <a class="nav-link" href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif>Home</a>
            <a class="nav-link" href="{{ route('services') }}" @if(request()->routeIs('services', 'services.category')) aria-current="page" @endif>Services</a>
            @if($hasOffers)<a class="nav-link" href="{{ route('offers') }}" @if(request()->routeIs('offers')) aria-current="page" @endif>Offers</a>@endif
            <a class="nav-link" href="{{ route('gallery') }}" @if(request()->routeIs('gallery')) aria-current="page" @endif>Gallery</a>
            <a class="nav-link" href="{{ route('booking.manage') }}" @if(request()->routeIs('booking.manage', 'booking.lookup', 'booking.cancel', 'booking.edit')) aria-current="page" @endif>My booking</a>
            <a class="nav-link" href="{{ route('home') }}#visit">Contact</a>
        </nav>

        <div class="header-actions">
            @include('partials.theme-toggle')
            <a href="{{ route('booking') }}" class="btn btn-primary hide-mobile">Book now</a>
            <button type="button" class="btn btn-ghost btn-icon nav-toggle" data-toggle-target="mobile-nav" aria-controls="mobile-nav" aria-expanded="false" aria-label="Open menu">
                <i class="fa-solid fa-bars" aria-hidden="true"></i>
            </button>
        </div>
    </div>
</header>

<nav class="mobile-nav" id="mobile-nav" aria-label="Mobile" hidden>
    <a class="nav-link" href="{{ route('home') }}">Home <i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
    <a class="nav-link" href="{{ route('services') }}">Services &amp; prices <i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
    @if($hasOffers)<a class="nav-link" href="{{ route('offers') }}">Offers <i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>@endif
    <a class="nav-link" href="{{ route('gallery') }}">Gallery <i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
    <a class="nav-link" href="{{ route('booking.manage') }}">My booking <i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
    <a class="nav-link" href="{{ route('review.create') }}">Rate your visit <i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
    <a class="nav-link" href="{{ route('home') }}#visit">Contact <i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
    <a href="{{ route('booking') }}" class="btn btn-primary btn-block btn-lg">Book appointment</a>
</nav>

<main id="main">
    @yield('content')
</main>

@unless(View::hasSection('hide_footer'))
<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div>
                <a class="brand brand-wordmark" href="{{ route('home') }}">
                    <span class="brand-name">{{ $salon }}</span>
                </a>
                @if(setting('salon_tagline'))
                    <p class="footer-about">{{ setting('salon_tagline') }}</p>
                @endif
                <div class="footer-social">
                    @if(setting('instagram_url'))
                        <a class="btn btn-icon btn-sm" href="{{ setting('instagram_url') }}" target="_blank" rel="noopener" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
                    @endif
                    @if(setting('facebook_url'))
                        <a class="btn btn-icon btn-sm" href="{{ setting('facebook_url') }}" target="_blank" rel="noopener" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                    @endif
                    @if(setting('whatsapp'))
                        <a class="btn btn-icon btn-sm" href="https://wa.me/{{ setting('whatsapp') }}" target="_blank" rel="noopener" aria-label="WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
                    @endif
                </div>
            </div>
            <div>
                <div class="footer-title">Explore</div>
                <ul class="footer-links">
                    <li><a href="{{ route('services') }}">Services &amp; prices</a></li>
                    @if($hasOffers)<li><a href="{{ route('offers') }}">Offers</a></li>@endif
                    <li><a href="{{ route('gallery') }}">Gallery</a></li>
                    <li><a href="{{ route('booking') }}">Book an appointment</a></li>
                    <li><a href="{{ route('booking.manage') }}">Manage my booking</a></li>
                    <li><a href="{{ route('review.create') }}">Rate your visit</a></li>
                </ul>
            </div>
            <div>
                <div class="footer-title">Team</div>
                <ul class="footer-links">
                    <li><a href="{{ route('stylist.login') }}">Stylist portal</a></li>
                    <li><a href="{{ route('admin.login') }}">Admin</a></li>
                </ul>
            </div>
            <div>
                <div class="footer-title">Contact</div>
                <ul class="footer-links">
                    @if(setting('salon_phone'))
                        <li><a href="tel:{{ preg_replace('/[^0-9+]/', '', setting('salon_phone')) }}"><i class="fa-solid fa-phone icon-muted" aria-hidden="true"></i>&nbsp; {{ setting('salon_phone') }}</a></li>
                    @endif
                    @if(setting('salon_email'))
                        <li><a href="mailto:{{ setting('salon_email') }}"><i class="fa-solid fa-envelope icon-muted" aria-hidden="true"></i>&nbsp; {{ setting('salon_email') }}</a></li>
                    @endif
                    @if(setting('salon_address'))
                        <li class="text-muted"><i class="fa-solid fa-location-dot icon-muted" aria-hidden="true"></i>&nbsp; {{ setting('salon_address') }}</li>
                    @endif
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <span>&copy; {{ date('Y') }} {{ $salon }}. All rights reserved.</span>
            <span>Online booking available 24/7</span>
        </div>
    </div>
</footer>
@endunless

@include('partials.toasts')
<script src="{{ asset_v('js/app.js') }}" defer></script>
@stack('scripts')
</body>
</html>
