@extends('layouts.app')
@section('title', 'Manage my booking')

@section('content')
<section class="page-hero">
    <div class="container container-sm">
        <span class="eyebrow">Your appointment</span>
        <h1>Manage my booking</h1>
        <p>{{ isset($appointment) ? 'Change, cancel or review your appointment.' : 'Look up your appointment with the reference from your confirmation and the phone number you booked with.' }}</p>
    </div>
</section>

<section class="section-sm">
    <div class="container container-sm stack-lg">

        @isset($appointment)
            @php
                $a = $appointment;
                $date = \Carbon\Carbon::parse($a->appointment_date);
                $upcoming = in_array($a->status, ['pending', 'confirmed'], true);
            @endphp

            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Booking <span class="chip-code">{{ $a->appointment_no }}</span></div>
                        <div class="card-subtitle">
                            Booking made {{ $a->created_at->format('j M Y') }}
                            @if($a->rescheduled_at) · changed {{ $a->rescheduled_at->format('j M') }}@endif
                        </div>
                    </div>
                    @include('partials.status', ['status' => $a->status])
                </div>
                <div class="card-body">
                    <dl class="kv">
                        <div><dt>Service</dt><dd>{{ $a->service->name ?? '—' }}</dd></div>
                        <div><dt>Date</dt><dd>{{ $date->format('l, j F Y') }}</dd></div>
                        <div><dt>Time</dt><dd>{{ fmt_time($a->slot_start) }} – {{ fmt_time($a->slot_end) }}</dd></div>
                        <div><dt>Stylist</dt><dd>{{ $a->staff->name ?? 'Assigned on arrival' }}</dd></div>
                        @if($a->discount() > 0)
                            <div><dt>Usual price</dt><dd>{{ money($a->service->price) }}</dd></div>
                            <div><dt>{{ $a->offer_name ?: 'Offer' }}</dt><dd>− {{ money($a->discount()) }}</dd></div>
                        @endif
                        @foreach($a->items as $item)
                            <div><dt>Extra: {{ $item->name }}@if($item->quantity > 1) × {{ $item->quantity }}@endif</dt><dd>{{ money($item->lineTotal()) }}</dd></div>
                        @endforeach
                        <div><dt>{{ $a->payment_status === 'paid' ? 'Paid' : 'Total' }}</dt><dd>{{ money($a->total()) }}</dd></div>
                        @if($a->payment_status === 'partial')
                            <div><dt>Advance paid</dt><dd>{{ money($a->paidSoFar()) }}</dd></div>
                            <div><dt>Balance at the salon</dt><dd>{{ money($a->balanceDue()) }}</dd></div>
                        @endif
                    </dl>

                    @if($upcoming && $a->advanceOutstanding() > 0)
                        <div class="advance-box">
                            <div class="advance-box-head">
                                <i class="fa-solid fa-hand-holding-dollar" aria-hidden="true"></i>
                                <div><strong>Advance needed: {{ money($a->advanceOutstanding()) }}</strong>
                                    <div class="text-sm">{{ $a->service->name }} needs an advance to secure your slot. Use your reference <strong class="mono">{{ $a->appointment_no }}</strong> as the payment note — the salon confirms your booking once it arrives.</div></div>
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
                    @elseif($upcoming && $a->advancePaid() > 0)
                        <div class="alert alert-success mt-4"><i class="fa-solid fa-circle-check" aria-hidden="true"></i><div>Advance of {{ money($a->advancePaid()) }} received — thank you.</div></div>
                    @endif
                </div>

                @if($upcoming)
                    <div class="card-footer stack-sm">
                        @if($a->isChangeableByCustomer())
                            <div class="row row-wrap manage-actions">
                                <a href="{{ route('booking.edit') }}" class="btn btn-primary"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Change booking</a>
                                <form method="POST" action="{{ route('booking.cancel') }}"
                                      data-confirm="Cancel this booking?"
                                      data-confirm-message="Your {{ $a->service->name ?? '' }} appointment on {{ $date->format('j M') }} will be released."
                                      data-confirm-button="Cancel booking">
                                    @csrf
                                    <button type="submit" class="btn btn-danger-soft" data-loading="Cancelling…">Cancel booking</button>
                                </form>
                            </div>
                            <p class="hint">Change the service, stylist, date or time, or cancel, up to {{ (int) setting('cancellation_hours', 2) }} hours before your appointment.</p>
                        @elseif($a->isCancellableByCustomer())
                            <div class="row row-wrap manage-actions">
                                <form method="POST" action="{{ route('booking.cancel') }}" data-confirm="Cancel this booking?" data-confirm-button="Cancel booking">
                                    @csrf
                                    <button type="submit" class="btn btn-danger-soft" data-loading="Cancelling…">Cancel booking</button>
                                </form>
                            </div>
                            <p class="hint">This booking has already been changed {{ \App\Models\Appointment::MAX_CUSTOMER_CHANGES }} times. Call us{{ setting('salon_phone') ? ' on ' . setting('salon_phone') : '' }} to change it again.</p>
                        @elseif($a->payments->isNotEmpty())
                            <p class="text-sm text-muted">A payment has been made on this booking, so changes and cancellations are handled by the salon. Please call us{{ setting('salon_phone') ? ' on ' . setting('salon_phone') : '' }}.</p>
                        @else
                            <p class="text-sm text-muted">It’s too close to your appointment to change or cancel online. Please call us{{ setting('salon_phone') ? ' on ' . setting('salon_phone') : '' }}.</p>
                        @endif
                    </div>
                @endif
            </div>

            {{-- ── Feedback ─────────────────────────────────────── --}}
            @if($a->feedback)
                <div class="card card-pad">
                    <h2 class="card-title">Your review</h2>
                    <div class="stars mt-2" aria-label="{{ $a->feedback->rating }} out of 5 stars">
                        @for($i = 1; $i <= 5; $i++)<i class="fa-{{ $i <= $a->feedback->rating ? 'solid' : 'regular' }} fa-star" aria-hidden="true"></i>@endfor
                    </div>
                    @if($a->feedback->comment)<p class="mt-2">“{{ $a->feedback->comment }}”</p>@endif
                    <p class="hint mt-2">Thank you — your feedback helps us improve.</p>
                </div>
            @elseif($a->status === 'completed')
                @include('partials.feedback-form', ['appointment' => $a, 'action' => route('booking.feedback')])
            @endif

            <div class="row row-between row-wrap">
                <form method="POST" action="{{ route('booking.forget') }}">
                    @csrf
                    <button type="submit" class="link-btn"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Look up a different booking</button>
                </form>
                <a href="{{ route('booking') }}" class="btn">Book another appointment</a>
            </div>
        @else
            <form method="POST" action="{{ route('booking.lookup') }}" class="card card-pad stack" novalidate>
                @csrf
                <div class="form-grid">
                    <div class="field">
                        <label class="label" for="reference">Booking reference</label>
                        <input class="input input-mono" id="reference" name="reference" value="{{ old('reference') }}" required maxlength="20" autocomplete="off" placeholder="e.g. AB12CD34" @error('reference') aria-invalid="true" @enderror>
                        @error('reference')<div class="error-text">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label class="label" for="phone">Phone number</label>
                        <input class="input" id="phone" name="phone" type="tel" inputmode="tel" value="{{ old('phone') }}" required maxlength="20" autocomplete="tel" @error('phone') aria-invalid="true" @enderror>
                        @error('phone')<div class="error-text">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="row row-end">
                    <button type="submit" class="btn btn-primary" data-loading="Searching…"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Find my booking</button>
                </div>
                <p class="hint">You can change, cancel, or leave feedback after your visit.</p>
            </form>
        @endisset
    </div>
</section>
@endsection
