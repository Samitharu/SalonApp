@extends('layouts.app')
@section('title', $current ? $current->name . ' gallery' : 'Gallery')
@section('meta_description', 'Photos of our work at ' . setting('salon_name', 'our salon') . ($current ? ': ' . $current->name : '') . '.')

@section('content')
<section class="page-hero">
    <div class="container">
        <span class="eyebrow">Gallery</span>
        <h1>{{ $current ? $current->name : 'Our work' }}</h1>
        <p>A look at recent work from our stylists.</p>
    </div>
</section>

<section class="section-sm">
    <div class="container">
        @if($categories->count() > 1)
            <nav class="chips mb-6" aria-label="Filter photos">
                <a class="chip {{ $current ? '' : 'is-active' }}" href="{{ route('gallery') }}" @unless($current) aria-current="page" @endunless>All</a>
                @foreach($categories as $cat)
                    <a class="chip {{ $current && $current->id === $cat->id ? 'is-active' : '' }}" href="{{ route('gallery', ['category' => $cat->slug]) }}" @if($current && $current->id === $cat->id) aria-current="page" @endif>{{ $cat->name }}</a>
                @endforeach
            </nav>
        @endif

        @if($photos->isEmpty())
            <div class="card">
                <div class="empty">
                    <div class="empty-icon"><i class="fa-regular fa-images" aria-hidden="true"></i></div>
                    <h3>Photos coming soon</h3>
                    <p>We’re putting together our best work. In the meantime, browse our services.</p>
                    <a href="{{ route('services') }}" class="btn btn-sm">Services &amp; prices</a>
                </div>
            </div>
        @else
            <ul class="gallery-grid" role="list">
                @foreach($photos as $photo)
                    @php [$w, $h] = $photo->thumbSize(); @endphp
                    <li class="gallery-item">
                        <a href="{{ $photo->url() }}" data-lightbox data-caption="{{ $photo->caption }}">
                            <img src="{{ $photo->thumbUrl() }}" alt="{{ $photo->altText() }}" loading="lazy" @if($w) width="{{ $w }}" height="{{ $h }}" @endif>
                        </a>
                        @if($photo->caption || (!$current && $photo->category))
                            <div class="gallery-caption">
                                @if($photo->caption)<span>{{ $photo->caption }}</span>@endif
                                @if(!$current && $photo->category)<span class="gallery-tag">{{ $photo->category->name }}</span>@endif
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
            @if($photos->hasPages())
                <div class="mt-8">{{ $photos->links() }}</div>
            @endif
        @endif
    </div>
</section>

<section class="section-sm">
    <div class="container">
        <div class="cta-band">
            <div>
                <h2>Love what you see?</h2>
                <p>Book your appointment online in a minute.</p>
            </div>
            <a href="{{ route('booking') }}" class="btn btn-champagne btn-lg">Book now</a>
        </div>
    </div>
</section>

@include('partials.lightbox')
@endsection
