@extends('layouts.app')
@section('title', '')

@php
    $todayDow  = now()->dayOfWeek;
    $todayRow  = $hours[$todayDow] ?? null;
    $openToday = $todayRow && !$todayRow->is_closed && $todayRow->open_time;
    $place     = setting('salon_address') ? \Illuminate\Support\Str::before(setting('salon_address'), ',') : 'Salon & bridal';
    $subLine   = $categoryLine ?: setting('salon_tagline', 'Hair  |  Skin  |  Nails  |  Bridal');

    // With no slides set up, show one default slide (warm backdrop, no photo).
    $heroSlides = $slides->isNotEmpty() ? $slides : collect([null]);

    // Category opened from the strip (?category=slug), or null for the overview.
    $sel = $selected?->slug;
    $packagesLine = fn ($c) => $c->packages_count . ' ' . \Illuminate\Support\Str::plural('package', $c->packages_count) . ' · from ' . money($c->services->map->priceOn()->min());
@endphp
@if($selected && $selected->imageUrl())
    @push('head')<link rel="preload" as="image" href="{{ $selected->imageUrl() }}" fetchpriority="high">@endpush
@endif

@section('content')

{{-- ═══ Hero carousel (Theme 1 · Classic Luxury) ═══ --}}
<section class="lux-hero" aria-roledescription="carousel" aria-label="Highlights" data-carousel data-mode="{{ $sel ? 'category' : 'slides' }}">
    @foreach($heroSlides as $i => $slide)
        @php $img = $slide?->imageUrl(); $on = $i === 0 && !$sel; @endphp
        <div class="lux-slide{{ $on ? ' is-active' : '' }}{{ $img ? '' : ' no-image' }}"
             role="group" aria-roledescription="slide" aria-label="{{ $i + 1 }} of {{ $heroSlides->count() }}" @unless($on) aria-hidden="true" @endunless data-slide>
            @if($img)
                <img class="lux-slide-img" src="{{ $img }}" alt="" @if($i === 0 && !$sel) fetchpriority="high" @else loading="lazy" @endif>
            @else
                <div class="lux-ornament" aria-hidden="true">{{ mb_substr(setting('salon_name', 'S'), 0, 1) }}</div>
            @endif
            <div class="lux-veil" aria-hidden="true"></div>

            <div class="container lux-hero-inner">
                <div class="lux-hero-copy">
                    <span class="lux-eyebrow">{{ $place }}</span>
                    @if($i === 0)
                        <h1 class="lux-hero-title">{{ $slide?->title ?: 'Beauty beyond the everyday' }}</h1>
                    @else
                        <h2 class="lux-hero-title">{{ $slide->title }}</h2>
                    @endif
                    <p class="lux-hero-sub">{{ $slide?->subtitle ?: $subLine }}</p>
                    <div class="lux-hero-cta">
                        <a href="{{ $slide?->button_link ?: route('booking') }}" class="btn btn-primary btn-lg" @unless($on) tabindex="-1" @endunless>{{ $slide?->button_text ?: 'Book appointment' }}</a>
                        <a href="{{ route('services') }}" class="btn btn-outline-light btn-lg" @unless($on) tabindex="-1" @endunless>View services</a>
                    </div>
                    <p class="lux-today">
                        @if($openToday)
                            <span class="status-dot" aria-hidden="true"></span>Open today {{ fmt_time($todayRow->open_time) }} – {{ fmt_time($todayRow->close_time) }}
                        @else
                            <span class="status-dot closed" aria-hidden="true"></span>Closed today — book for another day
                        @endif
                    </p>
                </div>
            </div>
        </div>
    @endforeach

    {{-- One hero per category, shown when it's picked on the strip. Photos load only when first shown. --}}
    @foreach($categories as $cat)
        @php $on = $cat->slug === $sel; $img = $cat->imageUrl(); @endphp
        <div class="lux-slide lux-cat-slide{{ $on ? ' is-active' : '' }}{{ $img ? '' : ' no-image' }}" data-cat-hero="{{ $cat->slug }}" @unless($on) aria-hidden="true" @endunless>
            @if($img)
                <img class="lux-slide-img" alt="" @if($on) src="{{ $img }}" fetchpriority="high" @else data-src="{{ $img }}" @endif>
            @else
                <div class="lux-ornament" aria-hidden="true">{{ mb_substr($cat->name, 0, 1) }}</div>
            @endif
            <div class="lux-veil" aria-hidden="true"></div>
            <div class="container lux-hero-inner">
                <div class="lux-hero-copy">
                    <span class="lux-eyebrow">Our services</span>
                    <h2 class="lux-hero-title">{{ $cat->name }}</h2>
                    @if($cat->tagline || $cat->description)
                        <p class="lux-hero-sub">{{ $cat->tagline ?: \Illuminate\Support\Str::limit($cat->description, 120) }}</p>
                    @endif
                    <div class="lux-hero-cta">
                        <a href="#services" class="btn btn-primary btn-lg" @unless($on) tabindex="-1" @endunless>View packages</a>
                        <a href="{{ route('booking', ['category' => $cat->slug]) }}" class="btn btn-outline-light btn-lg" @unless($on) tabindex="-1" @endunless>Book appointment</a>
                    </div>
                    <p class="lux-today">{{ $packagesLine($cat) }}</p>
                </div>
            </div>
        </div>
    @endforeach

    @if($heroSlides->count() > 1)
        <button type="button" class="lux-arrow lux-prev" data-prev aria-label="Previous slide"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
        <button type="button" class="lux-arrow lux-next" data-next aria-label="Next slide"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>
        <div class="lux-dots" role="group" aria-label="Choose slide">
            @foreach($heroSlides as $i => $slide)
                <button type="button" class="lux-dot" data-dot="{{ $i }}" aria-label="Slide {{ $i + 1 }}" @if($i === 0) aria-current="true" @endif></button>
            @endforeach
        </div>
    @endif
</section>

{{-- ═══ Category strip: picks the hero and the packages below ═══ --}}
@if($categories->isNotEmpty())
<nav class="lux-features lux-cats" aria-label="Service categories">
    <div class="container lux-features-grid" style="--cols: {{ min($categories->count(), 8) }}">
        @foreach($categories as $cat)
            <a class="lux-feature lux-cat" href="{{ route('home', ['category' => $cat->slug]) }}#services" data-cat-select="{{ $cat->slug }}"{!! $cat->slug === $sel ? ' aria-current="true"' : '' !!}>
                @if($cat->iconClass())
                    <i class="{{ $cat->iconClass() }}" aria-hidden="true"></i>
                @else
                    <span class="lux-cat-letter" aria-hidden="true">{{ mb_substr($cat->name, 0, 1) }}</span>
                @endif
                <h3>{{ $cat->name }}</h3>
                <p>{{ $cat->tagline ?: $cat->packages_count . ' ' . \Illuminate\Support\Str::plural('package', $cat->packages_count) }}</p>
            </a>
        @endforeach
    </div>
</nav>
<p class="sr-only" aria-live="polite" id="catStatus"></p>
@else
<section class="lux-features" aria-label="Why choose us">
    <div class="container lux-features-grid">
        <div class="lux-feature">
            <i class="fa-regular fa-gem" aria-hidden="true"></i>
            <h3>Premium care</h3>
            <p>Experienced professionals</p>
        </div>
        <div class="lux-feature">
            <i class="fa-regular fa-face-smile" aria-hidden="true"></i>
            <h3>Personalised services</h3>
            <p>Tailored to you</p>
        </div>
        <div class="lux-feature">
            <i class="fa-regular fa-heart" aria-hidden="true"></i>
            <h3>Trusted by brides</h3>
            <p>For life’s special moments</p>
        </div>
    </div>
</section>
@endif

{{-- ═══ Offers running today ═══ --}}
@if($offers->isNotEmpty())
<section class="section section-alt" id="offers">
    <div class="container">
        <div class="section-head center">
            <span class="eyebrow">Offers</span>
            <h2 class="section-title">Special offers</h2>
        </div>
        <div class="offer-grid">
            @foreach($offers->take(3) as $offer)
                @include('partials.offer-card', ['limit' => 3])
            @endforeach
        </div>
        @if($offers->count() > 3)
            <div class="row row-center mt-8">
                <a href="{{ route('offers') }}" class="btn btn-outline">See all {{ $offers->count() }} offers</a>
            </div>
        @endif
    </div>
</section>
@endif

{{-- ═══ Main categories: overview cards, or the picked category's packages ═══ --}}
@if($categories->isNotEmpty())
<section class="section" id="services">
    <div class="container">
        <div class="cat-panel" data-cat-panel="" {{ $sel ? 'hidden' : 'data-selected' }}>
        <div class="section-head center">
            <span class="eyebrow">Our services</span>
            <h2 class="section-title">Explore by category</h2>
            <p class="section-lead">Choose a category to see its packages, prices and durations.</p>
        </div>

        <ul class="cat-grid" role="list" data-count="{{ $categories->count() }}">
            @foreach($categories as $cat)
                <li style="--i: {{ $loop->index }}">
                    <a class="cat-card {{ $cat->imageUrl() ? '' : 'no-image' }}" href="{{ route('home', ['category' => $cat->slug]) }}#services" data-cat-select="{{ $cat->slug }}">
                        @if($cat->imageUrl())
                            <img src="{{ $cat->imageUrl() }}" alt="" loading="lazy">
                        @else
                            <span class="cat-card-mark" aria-hidden="true">{{ mb_substr($cat->name, 0, 1) }}</span>
                        @endif
                        <span class="cat-card-body">
                            <span class="cat-card-name">{{ $cat->name }}</span>
                            <span class="cat-card-meta">
                                {{ $cat->packages_count }} {{ \Illuminate\Support\Str::plural('package', $cat->packages_count) }} · <span class="from">from {{ money($cat->services->map->priceOn()->min()) }}</span>
                            </span>
                            <span class="cat-card-cta" aria-hidden="true">Explore <i class="fa-solid fa-arrow-right"></i></span>
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
        <div class="row row-center mt-8">
            <a href="{{ route('services') }}" class="btn btn-outline">Full menu &amp; prices</a>
        </div>
        </div>

        @foreach($categories as $cat)
            {{-- Photo areas only when most packages have a photo; otherwise compact cards. --}}
            @php $withMedia = $cat->services->filter(fn ($s) => $s->image)->count() * 2 >= $cat->services->count(); @endphp
            <div class="cat-panel" data-cat-panel="{{ $cat->slug }}" {{ $cat->slug === $sel ? 'data-selected' : 'hidden' }}>
                <div class="cat-panel-head">
                    <a href="{{ route('home') }}#services" class="cat-back" data-cat-select=""><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> All categories</a>
                    <div class="section-head center">
                        <span class="eyebrow">Our services</span>
                        <h2 class="section-title">{{ $cat->name }}</h2>
                        @if($cat->description)
                            <p class="section-lead">{{ $cat->description }}</p>
                        @endif
                    </div>
                </div>
                <div class="pkg-grid">
                    @foreach($cat->services as $service)
                        @include('partials.package-card', ['i' => $loop->index])
                    @endforeach
                </div>
                <div class="row row-center mt-8">
                    <a href="{{ route('services.category', $cat->slug) }}" class="btn btn-outline">{{ $cat->name }} page &amp; photos</a>
                </div>
            </div>
        @endforeach
    </div>
</section>
@elseif($services->isNotEmpty())
<section class="section">
    <div class="container">
        <div class="section-head center">
            <span class="eyebrow">Services</span>
            <h2 class="section-title">Our signature services</h2>
            <p class="section-lead">Transparent prices and durations, so you know exactly what to expect.</p>
        </div>

        <div class="grid-auto">
            @foreach($services as $service)
                <article class="service-tile">
                    <div class="service-tile-media">
                        @if($service->imageUrl())
                            <img src="{{ $service->imageUrl() }}" alt="" loading="lazy" width="400" height="250">
                        @else
                            <div class="placeholder" aria-hidden="true">{{ mb_substr($service->name, 0, 1) }}</div>
                        @endif
                    </div>
                    <div class="service-tile-body">
                        @if($service->category)
                            <span class="service-tile-cat">{{ $service->category->name }}</span>
                        @endif
                        <h3>{{ $service->name }}</h3>
                        @if($service->description)
                            <p>{{ $service->description }}</p>
                        @endif
                        @include('partials.offer-tag')
                        <div class="service-tile-foot">
                            <div>
                                @include('partials.price')
                                <div class="duration"><i class="fa-regular fa-clock" aria-hidden="true"></i>{{ $service->durationLabel() }}</div>
                            </div>
                            <a href="{{ route('booking', ['service' => $service->id]) }}" class="btn btn-outline btn-sm" aria-label="Book {{ $service->name }}">Book</a>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
        <div class="row row-center mt-8">
            <a href="{{ route('services') }}" class="btn btn-outline">View all services</a>
        </div>
    </div>
</section>
@endif

{{-- ═══ Gallery preview ═══ --}}
@if($photos->isNotEmpty())
<section class="section section-alt">
    <div class="container">
        <div class="section-head center">
            <span class="eyebrow">Gallery</span>
            <h2 class="section-title">Our latest work</h2>
        </div>
        <ul class="photo-strip" role="list">
            @foreach($photos as $photo)
                <li>
                    <a href="{{ route('gallery') }}" class="photo-tile">
                        <img src="{{ $photo->thumbUrl() }}" alt="{{ $photo->altText() }}" loading="lazy">
                    </a>
                </li>
            @endforeach
        </ul>
        <div class="row row-center mt-8">
            <a href="{{ route('gallery') }}" class="btn btn-outline">View gallery</a>
        </div>
    </div>
</section>
@endif

{{-- ═══ How it works ═══ --}}
<section class="section section-alt">
    <div class="container">
        <div class="section-head center">
            <span class="eyebrow">How it works</span>
            <h2 class="section-title">Booking takes three steps</h2>
        </div>
        <div class="steps-grid">
            <div class="step-item">
                <div class="step-num">01</div>
                <h3>Pick a service</h3>
                <p>Browse the menu with clear prices and durations.</p>
            </div>
            <div class="step-item">
                <div class="step-num">02</div>
                <h3>Choose a time</h3>
                <p>See live availability and pick your stylist, or let us match you.</p>
            </div>
            <div class="step-item">
                <div class="step-num">03</div>
                <h3>Show your QR</h3>
                <p>You get a booking reference and QR code to show when you arrive.</p>
            </div>
        </div>
    </div>
</section>

{{-- ═══ Team ═══ --}}
@if($staff->isNotEmpty())
<section class="section">
    <div class="container">
        <div class="section-head center">
            <span class="eyebrow">Our team</span>
            <h2 class="section-title">Meet your stylists</h2>
        </div>
        <div class="team-grid">
            @foreach($staff as $member)
                <div class="team-member">
                    <span class="avatar avatar-xl" aria-hidden="true">{{ initials($member->name) }}</span>
                    <h3>{{ $member->name }}</h3>
                    <div class="role">{{ $member->title ?: 'Stylist' }}</div>
                    @if($member->bio)
                        <p>{{ \Illuminate\Support\Str::limit($member->bio, 110) }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ═══ Visit ═══ --}}
<section class="section section-alt" id="visit">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Visit us</span>
            <h2 class="section-title">Opening hours &amp; contact</h2>
        </div>
        <div class="visit-grid">
            <div class="card card-pad">
                <h3 class="card-title mb-2">Opening hours</h3>
                <ul class="hours-list">
                    @foreach([1, 2, 3, 4, 5, 6, 0] as $d)
                        @php $h = $hours[$d] ?? null; @endphp
                        <li class="{{ $d === $todayDow ? 'is-today' : '' }}">
                            <span>{{ \App\Models\BusinessHour::DAYS[$d] }}@if($d === $todayDow) <span class="badge badge-accent no-dot">Today</span>@endif</span>
                            @if($h && !$h->is_closed && $h->open_time)
                                <span class="tabular text-right">
                                    {{ fmt_time($h->open_time) }} – {{ fmt_time($h->close_time) }}
                                    @if($h->break_start && $h->break_end)
                                        <span class="d-block text-xs text-muted">Lunch {{ fmt_time($h->break_start) }} – {{ fmt_time($h->break_end) }}</span>
                                    @endif
                                </span>
                            @else
                                <span class="closed">Closed</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
            <div class="card card-pad">
                <h3 class="card-title mb-4">Get in touch</h3>
                <ul class="contact-list">
                    @if(setting('salon_address'))
                        <li><i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                            <div><div class="k">Address</div>
                                @if(setting('maps_url'))
                                    <a href="{{ setting('maps_url') }}" target="_blank" rel="noopener">{{ setting('salon_address') }}</a>
                                @else
                                    <span class="fw-600">{{ setting('salon_address') }}</span>
                                @endif
                            </div></li>
                    @endif
                    @if(setting('salon_phone'))
                        <li><i class="fa-solid fa-phone" aria-hidden="true"></i>
                            <div><div class="k">Phone</div><a href="tel:{{ preg_replace('/[^0-9+]/', '', setting('salon_phone')) }}">{{ setting('salon_phone') }}</a></div></li>
                    @endif
                    @if(setting('whatsapp'))
                        <li><i class="fa-brands fa-whatsapp" aria-hidden="true"></i>
                            <div><div class="k">WhatsApp</div><a href="https://wa.me/{{ setting('whatsapp') }}" target="_blank" rel="noopener">Message us</a></div></li>
                    @endif
                    @if(setting('salon_email'))
                        <li><i class="fa-solid fa-envelope" aria-hidden="true"></i>
                            <div><div class="k">Email</div><a href="mailto:{{ setting('salon_email') }}">{{ setting('salon_email') }}</a></div></li>
                    @endif
                </ul>
            </div>
        </div>
    </div>
</section>

{{-- ═══ Reviews ═══ --}}
@if($reviews->isNotEmpty())
<section class="section" style="padding-bottom:0;">
    <div class="container">
        <div class="section-head center">
            <span class="eyebrow">Reviews</span>
            <h2 class="section-title">What our clients say</h2>
        </div>
        <div class="review-cards">
            @foreach($reviews as $review)
                <figure class="review-card">
                    <span class="stars" aria-label="{{ $review->rating }} out of 5 stars">
                        @for($i = 1; $i <= 5; $i++)<i class="fa-{{ $i <= $review->rating ? 'solid' : 'regular' }} fa-star" aria-hidden="true"></i>@endfor
                    </span>
                    <blockquote>“{{ \Illuminate\Support\Str::limit($review->comment, 260) }}”</blockquote>
                    <figcaption><strong>{{ $review->publicName() }}</strong>@if($review->service) · {{ $review->service->name }}@endif</figcaption>
                </figure>
            @endforeach
        </div>
        <div class="row row-center mt-4">
            <a href="{{ route('review.create') }}" class="btn"><i class="fa-regular fa-star" aria-hidden="true"></i> Visited us? Leave a review</a>
        </div>
    </div>
</section>
@endif

{{-- ═══ CTA ═══ --}}
<section class="section-sm">
    <div class="container">
        <div class="cta-band">
            <div>
                <h2>Ready for your next appointment?</h2>
                <p>Choose a time that suits you — it only takes a minute.</p>
            </div>
            <a href="{{ route('booking') }}" class="btn btn-champagne btn-lg">Book now</a>
        </div>
    </div>
</section>

@endsection

@push('scripts')
<script>
// Hero carousel + category picker.
// Slides: arrows, dots, keyboard; auto-advance every 7s unless the visitor prefers
// reduced motion, is hovering/focused in it, or the tab is hidden.
// Categories: picking one on the strip (or a card) swaps the hero to that category
// and the services section to its packages, without reloading. The URL follows
// (?category=slug) so Back works and links can be shared.
document.addEventListener('DOMContentLoaded', function () {
    var root = document.querySelector('[data-carousel]');
    if (!root) return;
    var slides = Array.prototype.slice.call(root.querySelectorAll('[data-slide]'));
    var catHeroes = Array.prototype.slice.call(root.querySelectorAll('[data-cat-hero]'));
    var panels = Array.prototype.slice.call(document.querySelectorAll('[data-cat-panel]'));
    var pickers = Array.prototype.slice.call(document.querySelectorAll('[data-cat-select]'));
    var status = document.getElementById('catStatus');
    var section = document.getElementById('services');
    var dots = root.querySelectorAll('[data-dot]');
    var i = 0, timer = null, paused = false;
    var current = root.dataset.mode === 'category' ? (catHeroes.filter(function (h) { return h.classList.contains('is-active'); })[0] || {}).dataset : null;
    current = current ? current.catHero : '';
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function setActive(el, on) {
        el.classList.toggle('is-active', on);
        if (on) el.removeAttribute('aria-hidden'); else el.setAttribute('aria-hidden', 'true');
        el.querySelectorAll('a').forEach(function (a) { if (on) a.removeAttribute('tabindex'); else a.setAttribute('tabindex', '-1'); });
    }

    function show(n) {
        i = (n + slides.length) % slides.length;
        slides.forEach(function (s, k) { setActive(s, !current && k === i); });
        dots.forEach(function (d, k) { if (k === i) d.setAttribute('aria-current', 'true'); else d.removeAttribute('aria-current'); });
    }

    function start() {
        if (reduce || timer || slides.length < 2) return;
        timer = setInterval(function () { if (!current && !paused && !document.hidden) show(i + 1); }, 7000);
    }

    // On phones the strip scrolls sideways: bring the picked category into view.
    function reveal(a) {
        var strip = a.parentElement;
        if (strip.scrollWidth <= strip.clientWidth) return;
        strip.scrollLeft = a.offsetLeft - (strip.clientWidth - a.offsetWidth) / 2;
    }

    function select(slug, push) {
        current = slug;
        root.dataset.mode = slug ? 'category' : 'slides';
        catHeroes.forEach(function (h) {
            var on = h.dataset.catHero === slug;
            var img = h.querySelector('img[data-src]');
            if (on && img) { img.src = img.dataset.src; img.removeAttribute('data-src'); }
            setActive(h, on);
        });
        show(i);
        var label = '';
        panels.forEach(function (p) {
            var on = p.dataset.catPanel === slug;
            p.hidden = !on;
            p.classList.remove('is-entering');
            if (on) {
                p.setAttribute('data-selected', '');
                label = (p.querySelector('.section-title') || {}).textContent || '';
                // Restart the gentle entrance (CSS skips it for reduced-motion users).
                void p.offsetWidth;
                p.classList.add('is-entering');
                clearTimeout(p._enterTimer);
                p._enterTimer = setTimeout(function () { p.classList.remove('is-entering'); }, 1200);
            } else p.removeAttribute('data-selected');
        });
        pickers.forEach(function (a) {
            if (!a.closest('.lux-cats')) return;
            if (a.dataset.catSelect === slug) { a.setAttribute('aria-current', 'true'); reveal(a); } else a.removeAttribute('aria-current');
        });
        if (status) status.textContent = slug ? 'Showing ' + label + ' packages' : 'Showing all categories';
        if (push) {
            var url = slug ? '?category=' + encodeURIComponent(slug) : location.pathname;
            history.pushState({ cat: slug }, '', url);
        }
    }

    pickers.forEach(function (a) {
        a.addEventListener('click', function (e) {
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.button > 0) return;
            e.preventDefault();
            var slug = a.dataset.catSelect;
            var fromSection = section && section.contains(a);
            if (slug === current && !fromSection) return;
            select(slug, true);
            // Picked from inside the services section: keep the new packages in view.
            if (fromSection && section.getBoundingClientRect().top < 0) section.scrollIntoView();
            if (fromSection) {
                var head = section.querySelector('[data-cat-panel][data-selected] .section-title');
                if (head) { head.setAttribute('tabindex', '-1'); head.focus({ preventScroll: true }); }
            }
        });
    });
    window.addEventListener('popstate', function () {
        var slug = new URLSearchParams(location.search).get('category') || '';
        if (!panels.some(function (p) { return p.dataset.catPanel === slug; })) slug = '';
        select(slug, false);
    });

    var picked = document.querySelector('.lux-cats [aria-current="true"]');
    if (picked) reveal(picked);

    if (slides.length > 1) {
        root.querySelector('[data-prev]').addEventListener('click', function () { show(i - 1); });
        root.querySelector('[data-next]').addEventListener('click', function () { show(i + 1); });
        dots.forEach(function (d) { d.addEventListener('click', function () { show(+d.dataset.dot); }); });
        root.addEventListener('keydown', function (e) {
            if (current) return;
            if (e.key === 'ArrowLeft') show(i - 1);
            if (e.key === 'ArrowRight') show(i + 1);
        });
        ['mouseenter', 'focusin'].forEach(function (ev) { root.addEventListener(ev, function () { paused = true; }); });
        ['mouseleave', 'focusout'].forEach(function (ev) { root.addEventListener(ev, function () { paused = false; }); });
    }
    start();
});
</script>
@endpush
