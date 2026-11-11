{{-- One bookable package. Pass $service, $withMedia (show the photo area) and optionally $i (position, staggers the entrance). --}}
<article class="service-tile" @isset($i) style="--i: {{ min($i, 8) }}" @endisset>
    @if($withMedia)
        <div class="service-tile-media">
            @if($service->imageUrl())
                <img src="{{ $service->imageUrl() }}" alt="" loading="lazy" width="400" height="300">
            @else
                <div class="placeholder" aria-hidden="true">{{ mb_substr($service->name, 0, 1) }}</div>
            @endif
        </div>
    @endif
    <div class="service-tile-body">
        <h3>{{ $service->name }}</h3>
        @if($service->description)
            <p>{{ $service->description }}</p>
        @endif
        @include('partials.offer-tag')
        @if($service->advanceLabel())
            <span class="pkg-advance"><i class="fa-solid fa-hand-holding-dollar" aria-hidden="true"></i>{{ $service->advanceLabel() }} to book</span>
        @endif
        <div class="service-tile-foot">
            <div>
                @include('partials.price')
                <div class="duration"><i class="fa-regular fa-clock" aria-hidden="true"></i>{{ $service->durationLabel() }}</div>
            </div>
            <a href="{{ route('booking', ['service' => $service->id]) }}" class="btn btn-primary btn-sm" aria-label="Book {{ $service->name }}">Book</a>
        </div>
    </div>
</article>
