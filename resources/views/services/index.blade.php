@extends('layouts.app')
@section('title', 'Services & prices')
@section('meta_description', 'Full service menu with prices and durations at ' . setting('salon_name', 'our salon') . '. Book online.')

@section('content')
<section class="page-hero">
    <div class="container">
        <span class="eyebrow">Menu</span>
        <h1>Services &amp; prices</h1>
        <p>All prices in {{ setting('currency', 'LKR') }}. Durations are approximate.</p>
    </div>
</section>

<section class="section-sm">
    <div class="container container-md">
        @if($services->isEmpty() && $addons->isEmpty())
            <div class="card">
                <div class="empty">
                    <div class="empty-icon"><i class="fa-solid fa-scissors" aria-hidden="true"></i></div>
                    <h3>Our menu is being updated</h3>
                    <p>Please check back soon, or contact us to book by phone.</p>
                </div>
            </div>
        @else
            <div class="row row-between row-wrap mb-6">
                @if($categories->count() > 1)
                    <div class="chips" role="group" aria-label="Filter by category">
                        <button type="button" class="chip" aria-pressed="true" data-filter="all">All <span class="count">{{ $services->count() }}</span></button>
                        @foreach($categories as $cat)
                            <button type="button" class="chip" aria-pressed="false" data-filter="{{ $cat->id }}">{{ $cat->name }}</button>
                        @endforeach
                    </div>
                @endif
                <label class="search-box">
                    <span class="sr-only">Search services</span>
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input type="search" class="input" id="serviceSearch" placeholder="Search services" autocomplete="off">
                </label>
            </div>

            @php
                $groups = $categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'desc' => $c->description, 'items' => $services->where('service_category_id', $c->id), 'url' => $c->is_active ? route('services.category', $c->slug) : null]);
                $other  = $services->whereNull('service_category_id');
                if ($other->isNotEmpty()) {
                    $groups->push(['id' => 'none', 'name' => $categories->isEmpty() ? 'All services' : 'Other services', 'desc' => null, 'items' => $other, 'url' => null]);
                }
            @endphp

            @foreach($groups as $group)
                <div class="menu-group" data-group="{{ $group['id'] }}">
                    <div class="menu-group-head">
                        <h2 class="menu-group-title">{{ $group['name'] }}</h2>
                        @if($group['url'])
                            <a href="{{ $group['url'] }}" class="menu-group-link">View packages <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                        @endif
                    </div>
                    @if($group['desc'])
                        <p class="menu-group-desc">{{ $group['desc'] }}</p>
                    @endif
                    <div class="menu-list">
                        @foreach($group['items'] as $service)
                            <div class="menu-item {{ $service->imageUrl() ? '' : 'no-thumb' }}" data-name="{{ mb_strtolower($service->name . ' ' . $service->description) }}">
                                @if($service->imageUrl())
                                    <img class="menu-item-thumb" src="{{ $service->imageUrl() }}" alt="" loading="lazy" width="64" height="64">
                                @endif
                                <div class="menu-item-main">
                                    <div class="menu-item-name">{{ $service->name }}</div>
                                    @if($service->description)
                                        <div class="menu-item-desc">{{ $service->description }}</div>
                                    @endif
                                    <div class="duration mt-1"><i class="fa-regular fa-clock" aria-hidden="true"></i>{{ $service->durationLabel() }}</div>
                                    @include('partials.offer-tag')
                                </div>
                                <div class="menu-item-meta">
                                    @include('partials.price')
                                    <a href="{{ route('booking', ['service' => $service->id]) }}" class="btn btn-soft btn-sm mt-2" aria-label="Book {{ $service->name }}">Book</a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            @if($addons->isNotEmpty())
                <div class="menu-group" data-group="addons">
                    <h2 class="menu-group-title">Add-ons &amp; extras</h2>
                    <p class="menu-group-desc">Add any of these to your appointment at the salon — just ask your stylist.</p>
                    <div class="menu-list">
                        @foreach($addons as $addon)
                            <div class="menu-item no-thumb" data-name="{{ mb_strtolower($addon->name . ' ' . $addon->description) }}">
                                <div class="menu-item-main">
                                    <div class="menu-item-name">{{ $addon->name }}</div>
                                    @if($addon->description)<div class="menu-item-desc">{{ $addon->description }}</div>@endif
                                </div>
                                <div class="menu-item-meta"><span class="price">+ {{ money($addon->price) }}</span></div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="card" id="noResults" hidden>
                <div class="empty">
                    <div class="empty-icon"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></div>
                    <h3>No services match your search</h3>
                    <p>Try a different word, or browse all services.</p>
                </div>
            </div>
        @endif
    </div>
</section>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var chips = document.querySelectorAll('[data-filter]');
    var search = document.getElementById('serviceSearch');
    var groups = document.querySelectorAll('[data-group]');
    var empty = document.getElementById('noResults');
    var active = 'all';
    if (!search) return;

    function apply() {
        var term = search.value.trim().toLowerCase();
        var anyVisible = false;
        groups.forEach(function (g) {
            var groupMatch = active === 'all' || g.dataset.group === active;
            var visibleItems = 0;
            g.querySelectorAll('.menu-item').forEach(function (item) {
                var show = groupMatch && (!term || item.dataset.name.indexOf(term) !== -1);
                item.hidden = !show;
                if (show) visibleItems++;
            });
            g.hidden = visibleItems === 0;
            if (visibleItems) anyVisible = true;
        });
        empty.hidden = anyVisible;
    }

    chips.forEach(function (chip) {
        chip.addEventListener('click', function () {
            active = chip.dataset.filter;
            chips.forEach(function (c) { c.setAttribute('aria-pressed', String(c === chip)); });
            apply();
        });
    });
    search.addEventListener('input', apply);
});
</script>
@endpush
