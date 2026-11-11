@extends('layouts.app')
@section('title', isset($editing) ? 'Change your booking' : 'Book an appointment')
@section('hide_footer', true)

@php
    /** @var \App\Models\Appointment|null $editing  set when the customer is changing a booking */
    $editing = $editing ?? null;
    $allServices = $categories->flatMap->services->merge($uncategorised);
    $current = $editing ? [
        'serviceId' => $editing->service_id,
        'staffId'   => $editing->staff_id,
        'date'      => \Carbon\Carbon::parse($editing->appointment_date)->toDateString(),
        'start'     => substr($editing->slot_start, 0, 5),
        'service'   => $editing->service->name ?? '—',
        'stylist'   => $editing->staff->name ?? 'Any stylist',
        'when'      => \Carbon\Carbon::parse($editing->appointment_date)->format('D j M') . ', ' . fmt_time($editing->slot_start),
        'price'     => money($editing->servicePrice()),
        'offer'     => $editing->discount() > 0 ? $editing->offer_name : null,
    ] : null;
    $pricing = app(\App\Services\OfferPricing::class);
    $lastDate = collect($dates)->last()['date'] ?? now()->toDateString();
    $config = [
        'mode'   => $editing ? 'edit' : 'new',
        'today'  => now()->toDateString(),
        'ref'    => $editing->appointment_no ?? null,
        'current' => $current,
        'routes' => [
            'slots' => route('booking.slots'),
            'store' => $editing ? route('booking.update') : route('booking.store'),
            'manage' => route('booking.manage'),
        ],
        'preselect' => $editing ? (int) $editing->service_id : (int) request('service'),
        'salon'     => setting('salon_name', 'Salon'),
        'address'   => setting('salon_address', ''),
        'services'  => $allServices->mapWithKeys(fn ($s) => [$s->id => [
            'id'       => $s->id,
            'name'     => $s->name,
            'price'    => money($s->price),
            'duration' => $s->durationLabel(),
            'minutes'  => (int) $s->duration_minutes,
            'offers'   => $pricing->windows($s, $lastDate),
        ]]),
        'staff' => $staff->mapWithKeys(fn ($s) => [$s->id => ['id' => $s->id, 'name' => $s->name]]),
    ];
@endphp

@section('content')
<div class="container">
    <div class="booking-layout">
        <div>
            @if($editing)
                <div class="alert alert-info mb-6 change-banner">
                    <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                    <div class="grow">
                        <strong>Changing booking <span class="mono">{{ $editing->appointment_no }}</span></strong>
                        <div class="text-sm">Currently: {{ $current['service'] }} · {{ $current['when'] }} · {{ $current['stylist'] }}</div>
                    </div>
                    <a href="{{ route('booking.manage') }}" class="btn btn-sm">Keep my booking</a>
                </div>
            @endif

            <ol class="stepper" aria-label="Booking progress" id="stepper">
                <li class="is-current" data-step-ind="1"><span class="num">1</span><span class="step-label">Service</span></li>
                <li data-step-ind="2"><span class="num">2</span><span class="step-label">Date &amp; time</span></li>
                <li data-step-ind="3"><span class="num">3</span><span class="step-label">{{ $editing ? 'Review' : 'Your details' }}</span></li>
            </ol>

            {{-- ── Step 1: service ───────────────────────────────── --}}
            <section class="step-panel" data-step="1" aria-labelledby="step1-title">
                <h1 id="step1-title" class="h2" style="font-size:24px;">{{ $editing ? 'Keep or change your service' : 'Choose a service' }}</h1>
                <p class="step-lead">{{ $editing ? 'Your current service is selected. Pick another one to change it, or continue.' : 'Select the treatment you\'d like to book.' }}</p>

                @if($allServices->isEmpty())
                    <div class="card"><div class="empty">
                        <div class="empty-icon"><i class="fa-solid fa-scissors" aria-hidden="true"></i></div>
                        <h3>Online booking is unavailable</h3>
                        <p>No services are open for booking right now. Please call us to make an appointment.</p>
                    </div></div>
                @else
                    @php
                        $showFilter = $allServices->count() > 5;
                        $showChips = $showFilter && $categories->count() + ($uncategorised->isNotEmpty() ? 1 : 0) > 1;
                        // ?category=bridal (from the homepage or a category page) opens on that category —
                        // only when the category buttons are there to switch back.
                        $startCat = $showChips ? $categories->firstWhere('slug', (string) request('category')) : null;
                    @endphp
                    @if($showFilter)
                        <div class="svc-filter">
                            <label class="search-box svc-search">
                                <span class="sr-only">Search services</span>
                                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                                <input type="search" class="input" id="svcSearch" placeholder="Search services, e.g. facial" autocomplete="off" enterkeyhint="search">
                            </label>
                            @if($showChips)
                                <div class="chips" role="group" aria-label="Filter by category" id="svcChips">
                                    <button type="button" class="chip" data-cat="all" aria-pressed="{{ $startCat ? 'false' : 'true' }}">All <span class="count">{{ $allServices->count() }}</span></button>
                                    @foreach($categories as $cat)
                                        <button type="button" class="chip" data-cat="{{ $cat->id }}" aria-pressed="{{ $startCat && $startCat->id === $cat->id ? 'true' : 'false' }}">{{ $cat->name }} <span class="count">{{ $cat->services->count() }}</span></button>
                                    @endforeach
                                    @if($uncategorised->isNotEmpty())
                                        <button type="button" class="chip" data-cat="other" aria-pressed="false">Other <span class="count">{{ $uncategorised->count() }}</span></button>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endif

                    <div role="radiogroup" aria-labelledby="step1-title" id="svcList">
                        @foreach($categories as $cat)
                            <div class="option-group" data-group="{{ $cat->id }}" @if($startCat && $startCat->id !== $cat->id) hidden @endif>
                                <div class="option-group-title">{{ $cat->name }}</div>
                                <div class="option-list">
                                    @foreach($cat->services as $s)
                                        @include('booking.partials.service-option', ['s' => $s, 'catName' => $cat->name])
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                        @if($uncategorised->isNotEmpty())
                            <div class="option-group" data-group="other" @if($startCat) hidden @endif>
                                @if($categories->isNotEmpty())<div class="option-group-title">Other</div>@endif
                                <div class="option-list">
                                    @foreach($uncategorised as $s)
                                        @include('booking.partials.service-option', ['s' => $s, 'catName' => ''])
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    @if($showFilter)
                        <div class="svc-empty" id="svcEmpty" hidden>
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                            <div>
                                <strong>No services match “<span id="svcTerm"></span>”</strong>
                                <div class="text-sm text-muted">Try another word, or <button type="button" class="link-btn" id="svcClear">show all services</button>.</div>
                            </div>
                        </div>
                        <p class="sr-only" aria-live="polite" id="svcStatus"></p>
                    @endif
                @endif
            </section>

            {{-- ── Step 2: stylist, date, time ───────────────────── --}}
            <section class="step-panel" data-step="2" aria-labelledby="step2-title" hidden>
                <button type="button" class="link-btn mb-4" data-back="1"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back</button>
                <h2 id="step2-title" style="font-size:24px;">Pick a date &amp; time</h2>
                <p class="step-lead">Times shown are available right now.</p>

                @if($staff->isNotEmpty())
                    <div class="mb-6">
                        <div class="label mb-2" id="stylist-label">Stylist</div>
                        <div class="stylist-grid" role="radiogroup" aria-labelledby="stylist-label">
                            <button type="button" class="option stylist-option" role="radio" aria-checked="true" data-staff="">
                                <span class="option-check"><i class="fa-solid fa-check" aria-hidden="true"></i></span>
                                <span class="avatar avatar-lg" aria-hidden="true"><i class="fa-solid fa-users"></i></span>
                                <span class="option-title">Any stylist</span>
                                <span class="option-sub">Most availability</span>
                            </button>
                            @foreach($staff as $member)
                                <button type="button" class="option stylist-option" role="radio" aria-checked="false" data-staff="{{ $member->id }}">
                                    <span class="option-check"><i class="fa-solid fa-check" aria-hidden="true"></i></span>
                                    <span class="avatar avatar-lg" aria-hidden="true">{{ initials($member->name) }}</span>
                                    <span class="option-title">{{ $member->name }}</span>
                                    <span class="option-sub">{{ $member->title ?: 'Stylist' }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="mb-6">
                    <div class="row row-between mb-2">
                        <div class="label" id="date-label">Date <span class="optional">· up to {{ count($dates) >= 85 ? round(count($dates) / 30) . ' months' : count($dates) . ' days' }} ahead</span></div>
                        <div class="row" style="gap:var(--s-2);">
                            <span class="text-sm text-muted hide-sm" id="monthLabel"></span>
                            <button type="button" class="btn btn-sm" id="calToggle" aria-expanded="false" aria-controls="calPanel">
                                <i class="fa-regular fa-calendar" aria-hidden="true"></i> Calendar
                            </button>
                        </div>
                    </div>
                    <div class="date-strip" role="group" aria-labelledby="date-label" id="dateStrip">
                        @foreach($dates as $d)
                            @php $c = \Carbon\Carbon::parse($d['date']); @endphp
                            <button type="button" class="date-pill" data-date="{{ $d['date'] }}" data-month="{{ $c->format('F Y') }}" aria-pressed="false"
                                    aria-label="{{ $c->format('l j F') }}{{ $d['open'] ? '' : ' (closed)' }}" @disabled(!$d['open'])>
                                <span class="dow">{{ $loop->first ? 'Today' : $c->format('D') }}</span>
                                <span class="day">{{ $c->format('j') }}</span>
                                <span class="mon">{{ $c->format('M') }}</span>
                            </button>
                        @endforeach
                    </div>

                    <div class="cal card" id="calPanel" hidden>
                        <div class="cal-head">
                            <button type="button" class="btn btn-ghost btn-icon btn-sm" data-cal="prev" aria-label="Previous month"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
                            <div class="cal-title" id="calTitle" aria-live="polite"></div>
                            <button type="button" class="btn btn-ghost btn-icon btn-sm" data-cal="next" aria-label="Next month"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>
                        </div>
                        <div class="cal-grid cal-dow" aria-hidden="true">
                            <span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span>
                        </div>
                        <div class="cal-grid" id="calGrid" role="group" aria-labelledby="calTitle"></div>
                        <p class="text-xs text-muted cal-note">Greyed-out days are closed or outside the booking window.</p>
                    </div>
                </div>

                <div class="alert mb-6" id="offerNote" hidden></div>

                <div>
                    <div class="label mb-2">Time</div>
                    <div id="slotArea" aria-live="polite">
                        <div class="panel-muted text-sm text-muted">Select a date to see available times.</div>
                    </div>
                </div>
            </section>

            {{-- ── Step 3: details (new) or review (change) ────────── --}}
            <section class="step-panel" data-step="3" aria-labelledby="step3-title" hidden>
                <button type="button" class="link-btn mb-4" data-back="2"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back</button>
                @if($editing)
                <h2 id="step3-title" style="font-size:24px;">Review your change</h2>
                <p class="step-lead">Check the new details, then confirm.</p>

                <form id="detailsForm" class="card" novalidate data-no-lock data-mode="edit">
                    <div class="review-grid">
                        <div class="review-col">
                            <div class="review-head">Current</div>
                            <dl class="kv">
                                <div><dt>Service</dt><dd>{{ $current['service'] }}</dd></div>
                                <div><dt>When</dt><dd>{{ $current['when'] }}</dd></div>
                                <div><dt>Stylist</dt><dd>{{ $current['stylist'] }}</dd></div>
                                <div><dt>Service price</dt><dd>{{ $current['price'] }}@if($current['offer']) ({{ $current['offer'] }})@endif</dd></div>
                            </dl>
                        </div>
                        <div class="review-col is-new">
                            <div class="review-head">New</div>
                            <dl class="kv">
                                <div><dt>Service</dt><dd data-review="service"></dd></div>
                                <div><dt>When</dt><dd data-review="when"></dd></div>
                                <div><dt>Stylist</dt><dd data-review="stylist"></dd></div>
                                <div><dt>Service price</dt><dd data-review="price"></dd></div>
                            </dl>
                        </div>
                    </div>
                    <div class="card-body" style="padding-top:0;">
                        @if(setting('auto_confirm', '0') !== '1')
                            <div class="alert alert-info"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><div>Changed bookings are re-confirmed by the salon. Your reference stays the same.</div></div>
                        @endif
                        <div class="alert alert-error mt-4" id="bookingError" role="alert" hidden></div>
                        <div class="step-actions desktop-nav-actions" style="margin-top:var(--s-6);">
                            <button type="button" class="btn btn-lg" data-back="2">Back</button>
                            <button type="submit" class="btn btn-primary btn-lg" data-confirm-booking>Confirm change</button>
                        </div>
                    </div>
                </form>
                @else
                <h2 id="step3-title" style="font-size:24px;">Your details</h2>
                <p class="step-lead">We'll use these to confirm your appointment.</p>

                <form id="detailsForm" class="card card-pad" novalidate data-no-lock>
                    <div class="form-grid">
                        <div class="field">
                            <label class="label" for="bkName">Full name <span class="req" aria-hidden="true">*</span></label>
                            <input class="input" id="bkName" name="name" autocomplete="name" required maxlength="120">
                            <div class="error-text" data-error-for="name" hidden></div>
                        </div>
                        <div class="field">
                            <label class="label" for="bkPhone">Mobile number <span class="req" aria-hidden="true">*</span></label>
                            <input class="input" id="bkPhone" name="phone" type="tel" inputmode="tel" autocomplete="tel" required maxlength="20" placeholder="077 123 4567">
                            <div class="error-text" data-error-for="phone" hidden></div>
                        </div>
                        <div class="field full">
                            <label class="label" for="bkEmail">Email <span class="req" aria-hidden="true">*</span></label>
                            <input class="input" id="bkEmail" name="email" type="email" inputmode="email" autocomplete="email" required maxlength="120" placeholder="you@example.com">
                            <div class="hint">We’ll send your booking confirmation here.</div>
                            <div class="error-text" data-error-for="email" hidden></div>
                        </div>
                        <div class="field full">
                            <label class="label" for="bkNotes">Notes for your stylist <span class="optional">(optional)</span></label>
                            <textarea class="textarea" id="bkNotes" name="notes" maxlength="500" rows="3" placeholder="Allergies, hair length, anything we should know"></textarea>
                        </div>
                    </div>

                    @if(setting('booking_policy'))
                        <div class="alert alert-info mt-6"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><div>{{ setting('booking_policy') }}</div></div>
                    @endif

                    <div class="alert alert-error mt-4" id="bookingError" role="alert" hidden></div>

                    <div class="step-actions desktop-nav-actions" style="margin-top:var(--s-6);">
                        <button type="button" class="btn btn-lg" data-back="2">Back</button>
                        <button type="submit" class="btn btn-primary btn-lg" data-confirm-booking>Confirm booking</button>
                    </div>
                </form>
                @endif
            </section>
        </div>

        {{-- ── Summary (desktop) ─────────────────────────────────── --}}
        <aside class="summary summary-desktop" aria-label="Booking summary">
            <div class="card card-pad" id="summary">
                <h2 class="card-title">Your booking</h2>
                <div class="summary-empty mt-2" data-summary-empty>No service selected yet.</div>
                <div data-summary-body hidden>
                    <div class="summary-service mt-4">
                        <div>
                            <div class="fw-700" data-sum="service"></div>
                            <div class="text-sm text-muted" data-sum="duration"></div>
                        </div>
                        <div class="summary-price">
                            <s class="price-was" data-sum="was" hidden></s>
                            <div class="fw-700 tabular" data-sum="price"></div>
                        </div>
                    </div>
                    <div class="offer-tag mt-2" data-sum="offer" hidden></div>
                    <div class="summary-lines">
                        <div class="line"><i class="fa-regular fa-user" aria-hidden="true"></i><span data-sum="stylist">Any stylist</span></div>
                        <div class="line"><i class="fa-regular fa-calendar" aria-hidden="true"></i><span data-sum="date">Date not chosen</span></div>
                        <div class="line"><i class="fa-regular fa-clock" aria-hidden="true"></i><span data-sum="time">Time not chosen</span></div>
                    </div>
                    <div class="summary-total"><span>Total</span><span class="tabular" data-sum="price"></span></div>
                    <p class="text-xs text-muted mt-2">Pay at the salon after your appointment.</p>
                </div>
                {{-- Next step, right under the total (steps 1–2; step 3 confirms under the form). --}}
                <div class="summary-action" id="summaryAction">
                    <button type="button" class="btn btn-primary btn-lg btn-block" id="summaryNext" disabled>Continue <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
                    <p class="summary-hint" id="summaryHint">Choose a service to continue</p>
                </div>
            </div>
        </aside>
    </div>

    {{-- ── Confirmation ──────────────────────────────────────────── --}}
    <section id="confirmation" class="ticket" hidden aria-live="polite">
        <div class="card">
            <div class="ticket-head">
                <div class="ticket-icon"><i class="fa-solid fa-check" aria-hidden="true"></i></div>
                <h1 tabindex="-1" id="confirmTitle">{{ $editing ? 'Booking updated' : 'You\'re booked!' }}</h1>
                <p class="text-muted mt-2" id="confirmLead">Show this QR code or reference when you arrive.</p>
            </div>
            <div class="ticket-body">
                <dl class="kv">
                    <div><dt>Reference</dt><dd><span class="ticket-ref" data-conf="ref"></span></dd></div>
                    <div><dt>Status</dt><dd data-conf="status"></dd></div>
                    <div><dt>Service</dt><dd data-conf="service"></dd></div>
                    <div><dt>Date</dt><dd data-conf="date"></dd></div>
                    <div><dt>Time</dt><dd data-conf="time"></dd></div>
                    <div><dt>Stylist</dt><dd data-conf="stylist"></dd></div>
                    <div><dt>Price</dt><dd data-conf="price"></dd></div>
                </dl>
                <div class="advance-box" id="advanceBox" hidden>
                    <div class="advance-box-head">
                        <i class="fa-solid fa-hand-holding-dollar" aria-hidden="true"></i>
                        <div><strong>Advance payment: <span data-conf="advance"></span></strong>
                            <div class="text-sm">Your booking is confirmed once the salon receives it. Use your reference <strong class="mono" data-conf="ref2"></strong> as the payment note.</div></div>
                    </div>
                    @if(setting('bank_details'))
                        <div class="advance-box-section"><div class="label">Bank transfer</div><div class="pre-line">{{ setting('bank_details') }}</div></div>
                    @endif
                    @if(setting('qr_payment_info'))
                        <div class="advance-box-section"><div class="label">QR payment</div><div class="pre-line">{{ setting('qr_payment_info') }}</div></div>
                    @endif
                    @if(!setting('bank_details') && !setting('qr_payment_info'))
                        <div class="advance-box-section text-sm">You can pay the advance in cash at the salon{{ setting('salon_phone') ? ', or call ' . setting('salon_phone') . ' for bank details' : '' }}.</div>
                    @endif
                </div>
                <div class="ticket-qr">
                    <div class="qr-box" id="qrBox" aria-label="Booking QR code" role="img"></div>
                </div>
            </div>
            <div class="ticket-actions no-print">
                <button type="button" class="btn" id="downloadTicket"><i class="fa-solid fa-download" aria-hidden="true"></i> Save image</button>
                <button type="button" class="btn" id="addCalendar"><i class="fa-regular fa-calendar-plus" aria-hidden="true"></i> Add to calendar</button>
                <a href="{{ route('booking.manage') }}" class="btn btn-ghost">Manage booking</a>
                <a href="{{ route('home') }}" class="btn btn-primary">Done</a>
            </div>
        </div>
    </section>
</div>

{{-- ── Mobile action bar ─────────────────────────────────────────── --}}
<div class="mobile-bar" id="mobileBar">
    <div class="mobile-bar-inner">
        <div class="info" data-mobile-info>
            <strong>Choose a service</strong>
            <span>Step 1 of 3</span>
        </div>
        <button type="button" class="btn btn-primary btn-lg" id="mobileNext" disabled>Continue</button>
    </div>
</div>

<script type="application/json" id="booking-config">@json($config)</script>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" defer></script>
<script src="{{ asset_v('js/booking.js') }}" defer></script>
@endpush
