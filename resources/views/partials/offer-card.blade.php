{{-- One offer on the website. Pass $offer and optionally $limit (how many service names to list). --}}
@php
    $names = $offer->services->pluck('name');
    $shown = isset($limit) ? $names->take($limit) : $names;
    $single = !$offer->coversAll() && $offer->services->count() === 1 ? $offer->services->first() : null;
@endphp
<article class="offer-card">
    <div class="offer-card-amount">{{ $offer->label() }}</div>
    <h3>{{ $offer->name }}</h3>
    @if($offer->description)
        <p class="offer-card-desc">{{ $offer->description }}</p>
    @endif
    <ul class="offer-card-facts">
        <li><i class="fa-regular fa-calendar" aria-hidden="true"></i>For visits {{ $offer->periodLabel() }}</li>
        <li><i class="fa-solid fa-scissors" aria-hidden="true"></i>{{ $offer->coversAll() ? 'On all services' : $shown->implode(', ') . ($names->count() > $shown->count() ? ' and ' . ($names->count() - $shown->count()) . ' more' : '') }}</li>
    </ul>
    <a href="{{ $single ? route('booking', ['service' => $single->id]) : route('booking') }}" class="btn btn-primary btn-sm">Book now</a>
</article>
