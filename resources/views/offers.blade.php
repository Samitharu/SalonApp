@extends('layouts.app')
@section('title', 'Offers')
@section('meta_description', 'Current offers at ' . setting('salon_name', 'our salon') . '. Book online.')

@section('content')
<section class="page-hero">
    <div class="container">
        <span class="eyebrow">Offers</span>
        <h1>Special offers</h1>
        <p>Book a visit on the dates shown and the discount is added for you. No code needed.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        @if($offers->isEmpty())
            <div class="card"><div class="empty">
                <div class="empty-icon"><i class="fa-solid fa-tags" aria-hidden="true"></i></div>
                <h3>No offers right now</h3>
                <p>Take a look at our services, or check back soon.</p>
                <a href="{{ route('services') }}" class="btn btn-sm">See services &amp; prices</a>
            </div></div>
        @else
            <div class="offer-grid">
                @foreach($offers as $offer)
                    @include('partials.offer-card')
                @endforeach
            </div>
        @endif
    </div>
</section>
@endsection
