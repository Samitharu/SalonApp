@extends('layouts.app')
@section('title', $category->name)
@section('meta_description', ($category->tagline ?: $category->description ?: $category->name . ' packages and prices') . ' · ' . setting('salon_name', 'Salon'))
@php $hero = $category->imageUrl(); @endphp
@if($hero)
    @push('head')
        <meta property="og:image" content="{{ $hero }}">
        <link rel="preload" as="image" href="{{ $hero }}" fetchpriority="high">
    @endpush
@endif

@section('content')
{{-- ═══ Category hero ═══ --}}
<section class="cat-hero {{ $hero ? '' : 'no-image' }}">
    @if($hero)
        <img class="cat-hero-img" src="{{ $hero }}" alt="" fetchpriority="high">
    @else
        <span class="lux-ornament" aria-hidden="true">{{ mb_substr($category->name, 0, 1) }}</span>
    @endif
    <div class="cat-hero-veil" aria-hidden="true"></div>
    <div class="container cat-hero-inner">
        <nav class="crumbs" aria-label="Breadcrumb">
            <a href="{{ route('home') }}">Home</a><span aria-hidden="true">/</span>
            <a href="{{ route('services') }}">Services</a><span aria-hidden="true">/</span>
            <span aria-current="page">{{ $category->name }}</span>
        </nav>
        <h1 class="cat-hero-title">{{ $category->name }}</h1>
        @if($category->tagline)
            <p class="cat-hero-sub">{{ $category->tagline }}</p>
        @endif
        <p class="cat-hero-meta">
            {{ $packages->count() }} {{ \Illuminate\Support\Str::plural('package', $packages->count()) }}
            · from {{ money($packages->map->priceOn()->min()) }}
        </p>
    </div>
</section>

{{-- ═══ Switch category ═══ --}}
@if($others->count() > 1)
<nav class="cat-switch" aria-label="Service categories">
    <div class="container">
        <div class="chips">
            @foreach($others as $other)
                <a class="chip {{ $other->id === $category->id ? 'is-active' : '' }}" href="{{ route('services.category', $other->slug) }}" @if($other->id === $category->id) aria-current="page" @endif>{{ $other->name }}</a>
            @endforeach
        </div>
    </div>
</nav>
@endif

{{-- ═══ Packages ═══ --}}
<section class="section">
    <div class="container">
        <div class="section-head center">
            <span class="eyebrow">Packages</span>
            <h2 class="section-title">{{ $category->name }} packages</h2>
            @if($category->description)
                <p class="section-lead">{{ $category->description }}</p>
            @endif
        </div>

        {{-- Photo areas only when most packages have a photo; otherwise compact cards. --}}
        @php $withMedia = $packages->filter(fn ($s) => $s->image)->count() * 2 >= $packages->count(); @endphp
        <div class="pkg-grid">
            @foreach($packages as $service)
                @include('partials.package-card')
            @endforeach
        </div>
    </div>
</section>

{{-- ═══ Photos from this category ═══ --}}
@if($photos->isNotEmpty())
<section class="section section-alt">
    <div class="container">
        <div class="section-head center">
            <span class="eyebrow">Gallery</span>
            <h2 class="section-title">{{ $category->name }} looks</h2>
        </div>
        <ul class="photo-strip" role="list">
            @foreach($photos as $photo)
                <li>
                    <a href="{{ $photo->url() }}" class="photo-tile" data-lightbox data-caption="{{ $photo->caption }}">
                        <img src="{{ $photo->thumbUrl() }}" alt="{{ $photo->altText() }}" loading="lazy">
                    </a>
                </li>
            @endforeach
        </ul>
        <div class="row row-center mt-8">
            <a href="{{ route('gallery', ['category' => $category->slug]) }}" class="btn btn-outline">More in the gallery</a>
        </div>
    </div>
</section>
@include('partials.lightbox')
@endif

<section class="section-sm">
    <div class="container">
        <div class="cta-band">
            <div>
                <h2>Not sure which package?</h2>
                <p>Call us{{ setting('salon_phone') ? ' on ' . setting('salon_phone') : '' }} and we’ll help you choose, or book and tell us at your visit.</p>
            </div>
            <a href="{{ route('booking', ['category' => $category->slug]) }}" class="btn btn-champagne btn-lg">Book now</a>
        </div>
    </div>
</section>
@endsection
