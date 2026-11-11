@extends('layouts.app')
@section('title', 'Rate your visit')

@section('content')
<section class="page-hero">
    <div class="container container-sm">
        <span class="eyebrow">Feedback</span>
        <h1>Rate your visit</h1>
        <p>Your stars and comments help us improve — and help other clients choose.</p>
    </div>
</section>

<section class="section-sm">
    <div class="container container-sm stack-lg">

        @if(!$appointment)
            {{-- Step 1: find the visit --}}
            <form method="POST" action="{{ route('review.lookup') }}" class="card card-pad stack" novalidate>
                @csrf
                <div>
                    <h2 class="card-title">Find your visit</h2>
                    <p class="card-subtitle">Enter the booking reference from your confirmation or receipt, and the phone number you booked with. Got a receipt with a QR code? Just scan it.</p>
                </div>
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
                    <button type="submit" class="btn btn-primary" data-loading="Searching…">Continue <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
                </div>
            </form>

            @if($recent->isNotEmpty())
                <div class="stack">
                    <h2 class="card-title">What others said</h2>
                    @foreach($recent as $review)
                        <figure class="review-card">
                            <span class="stars" aria-label="{{ $review->rating }} out of 5 stars">
                                @for($i = 1; $i <= 5; $i++)<i class="fa-{{ $i <= $review->rating ? 'solid' : 'regular' }} fa-star" aria-hidden="true"></i>@endfor
                            </span>
                            <blockquote>“{{ \Illuminate\Support\Str::limit($review->comment, 200) }}”</blockquote>
                            <figcaption><strong>{{ $review->publicName() }}</strong></figcaption>
                        </figure>
                    @endforeach
                </div>
            @endif

        @else
            @php
                $a = $appointment;
                $fb = $a->feedback;
            @endphp

            {{-- The visit being rated --}}
            <div class="card card-pad visit-summary">
                <span class="avatar avatar-lg" aria-hidden="true"><i class="fa-solid fa-scissors"></i></span>
                <div>
                    <div class="fw-700">{{ $a->service->name ?? 'Your appointment' }}</div>
                    <div class="text-sm text-muted">
                        {{ \Carbon\Carbon::parse($a->appointment_date)->format('l, j F Y') }}
                        @if($a->staff) · with {{ $a->staff->name }}@endif
                        · <span class="mono">{{ $a->appointment_no }}</span>
                    </div>
                </div>
            </div>

            @if($fb)
                {{-- Already reviewed --}}
                <div class="card card-pad stack review-thanks">
                    <div class="dialog-icon tone-success" aria-hidden="true"><i class="fa-solid fa-heart"></i></div>
                    <div>
                        <h2 class="card-title">Thank you, {{ \Illuminate\Support\Str::before($a->customer->name ?? 'there', ' ') }}!</h2>
                        <p class="card-subtitle">Here’s the review you left{{ $fb->created_at ? ' on ' . $fb->created_at->format('j M') : '' }}.</p>
                    </div>
                    <div class="stars stars-lg" aria-label="{{ $fb->rating }} out of 5 stars">
                        @for($i = 1; $i <= 5; $i++)<i class="fa-{{ $i <= $fb->rating ? 'solid' : 'regular' }} fa-star" aria-hidden="true"></i>@endfor
                    </div>
                    @if($fb->comment)<blockquote class="review-quote">“{{ $fb->comment }}”</blockquote>@endif
                    <a href="{{ route('booking') }}" class="btn btn-primary">Book your next visit</a>
                </div>
            @elseif($blocked)
                {{-- Not completed yet --}}
                <div class="alert alert-info"><i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    <div>{{ $blocked }} This booking is currently <strong>{{ strtolower(status_label($a->status)) }}</strong>.</div>
                </div>
            @else
                @include('partials.feedback-form', ['appointment' => $a, 'action' => route('review.store')])
            @endif

            <form method="POST" action="{{ route('review.forget') }}" class="row row-center">
                @csrf
                <button type="submit" class="link-btn"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Rate a different visit</button>
            </form>
        @endif
    </div>
</section>
@endsection
