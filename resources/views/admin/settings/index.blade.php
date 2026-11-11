@extends('layouts.admin')
@section('title', 'Settings')

@php
    $v = fn ($key, $default = '') => old("settings.$key", $settings[$key] ?? $default);
    $err = fn ($key) => $errors->first("settings.$key");
@endphp

@section('content')
<div class="page-head">
    <div>
        <h1>Settings</h1>
        <p>Salon details, booking rules and station access.</p>
    </div>
</div>

<div style="max-width:900px;" class="stack-lg">
    <form method="POST" action="{{ route('admin.settings.update') }}" class="stack-lg" novalidate>
        @csrf @method('PUT')

        @if($errors->any() && !$errors->pin->any())
            <div class="alert alert-error" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><div>Some settings need attention. See the highlighted fields below.</div></div>
        @endif

        <section class="card card-pad">
            <h2 class="card-title">Salon details</h2>
            <p class="card-subtitle mb-4">Shown in the website header, footer and booking confirmations.</p>
            <div class="form-grid">
                <div class="field">
                    <label class="label" for="s-name">Salon name <span class="req">*</span></label>
                    <input class="input" id="s-name" name="settings[salon_name]" value="{{ $v('salon_name') }}" maxlength="80" required @if($err('salon_name')) aria-invalid="true" @endif>
                    @if($err('salon_name'))<div class="error-text">{{ $err('salon_name') }}</div>@endif
                </div>
                <div class="field">
                    <label class="label" for="s-currency">Currency code <span class="req">*</span></label>
                    <input class="input" id="s-currency" name="settings[currency]" value="{{ $v('currency', 'LKR') }}" maxlength="5" required>
                    @if($err('currency'))<div class="error-text">{{ $err('currency') }}</div>@endif
                </div>
                <div class="field full">
                    <label class="label" for="s-tagline">Tagline</label>
                    <input class="input" id="s-tagline" name="settings[salon_tagline]" value="{{ $v('salon_tagline') }}" maxlength="160" placeholder="One sentence about your salon">
                </div>
                <div class="field">
                    <label class="label" for="s-phone">Phone</label>
                    <input class="input" id="s-phone" name="settings[salon_phone]" value="{{ $v('salon_phone') }}" maxlength="30" type="tel">
                </div>
                <div class="field">
                    <label class="label" for="s-email">Email</label>
                    <input class="input" id="s-email" name="settings[salon_email]" value="{{ $v('salon_email') }}" maxlength="120" type="email" @if($err('salon_email')) aria-invalid="true" @endif>
                    @if($err('salon_email'))<div class="error-text">{{ $err('salon_email') }}</div>@endif
                </div>
                <div class="field full">
                    <label class="label" for="s-address">Address</label>
                    <input class="input" id="s-address" name="settings[salon_address]" value="{{ $v('salon_address') }}" maxlength="200">
                </div>
                <div class="field full">
                    <label class="label" for="s-maps">Google Maps link</label>
                    <input class="input" id="s-maps" name="settings[maps_url]" value="{{ $v('maps_url') }}" maxlength="500" placeholder="https://maps.app.goo.gl/…" @if($err('maps_url')) aria-invalid="true" @endif>
                    @if($err('maps_url'))<div class="error-text">{{ $err('maps_url') }}</div>@endif
                </div>
            </div>
        </section>

        <section class="card card-pad">
            <h2 class="card-title">Payments &amp; advances</h2>
            <p class="card-subtitle mb-4">Shown to customers who book a service that needs an advance (set per service). Staff record the advance when it arrives — there’s no online card payment.</p>
            <div class="form-grid">
                <div class="field full">
                    <label class="label" for="s-bank">Bank transfer details</label>
                    <textarea class="textarea" id="s-bank" name="settings[bank_details]" rows="4" maxlength="600" placeholder="Bank: Commercial Bank&#10;Account name: Negombo Salon (Pvt) Ltd&#10;Account no: 1234567890&#10;Branch: Negombo">{{ $v('bank_details') }}</textarea>
                    @if($err('bank_details'))<div class="error-text">{{ $err('bank_details') }}</div>@endif
                </div>
                <div class="field full">
                    <label class="label" for="s-qr">QR payment details <span class="optional">(optional)</span></label>
                    <textarea class="textarea" id="s-qr" name="settings[qr_payment_info]" rows="2" maxlength="300" placeholder="e.g. Scan our LankaQR at the counter, or pay to merchant ID 1234…">{{ $v('qr_payment_info') }}</textarea>
                </div>
            </div>
        </section>

        <section class="card card-pad">
            <h2 class="card-title">Online booking rules</h2>
            <p class="card-subtitle mb-4">Control when and how customers can book. Lunch breaks and cleanup time between appointments are on <a href="{{ route('admin.hours.index') }}">Opening hours</a>.</p>
            <div class="form-grid">
                <div class="field">
                    <label class="label" for="s-adv">Book up to (days ahead)</label>
                    <input class="input" type="number" min="1" max="365" id="s-adv" name="settings[max_advance_days]" value="{{ $v('max_advance_days', 90) }}">
                    <div class="hint">90 days ≈ 3 months. Customers pick from a strip or a month calendar.</div>
                    @if($err('max_advance_days'))<div class="error-text">{{ $err('max_advance_days') }}</div>@endif
                </div>
                <div class="field">
                    <label class="label" for="s-notice">Minimum notice (minutes)</label>
                    <input class="input" type="number" min="0" max="2880" id="s-notice" name="settings[min_notice_minutes]" value="{{ $v('min_notice_minutes', 30) }}">
                    <div class="hint">Stops customers booking a slot that starts too soon.</div>
                    @if($err('min_notice_minutes'))<div class="error-text">{{ $err('min_notice_minutes') }}</div>@endif
                </div>
                <div class="field">
                    <label class="label" for="s-step">Time slot spacing</label>
                    <select class="select" id="s-step" name="settings[slot_step]">
                        @foreach(['0' => 'Match service length', '15' => 'Every 15 minutes', '30' => 'Every 30 minutes', '60' => 'Every hour'] as $val => $label)
                            <option value="{{ $val }}" @selected((string) $v('slot_step', '0') === (string) $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="hint">Shorter spacing offers more start times.</div>
                </div>
                <div class="field">
                    <label class="label" for="s-cancel">Online cancellation cut-off (hours)</label>
                    <input class="input" type="number" min="0" max="168" id="s-cancel" name="settings[cancellation_hours]" value="{{ $v('cancellation_hours', 2) }}">
                    @if($err('cancellation_hours'))<div class="error-text">{{ $err('cancellation_hours') }}</div>@endif
                </div>
                <div class="field">
                    <label class="label" for="s-retention">Keep system log for (days)</label>
                    <input class="input" type="number" min="30" max="3650" id="s-retention" name="settings[log_retention_days]" value="{{ $v('log_retention_days', 180) }}">
                    @if($err('log_retention_days'))<div class="error-text">{{ $err('log_retention_days') }}</div>@endif
                    <div class="hint">Older entries are deleted automatically each night.</div>
                </div>
                <div class="field">
                    <label class="label" for="s-parallel">Parallel bookings when no staff exist</label>
                    <input class="input" type="number" min="1" max="50" id="s-parallel" name="settings[max_parallel_bookings]" value="{{ $v('max_parallel_bookings', 3) }}">
                    <div class="hint">Only used if you haven't added any active staff.</div>
                </div>
                <div class="field">
                    <span class="label">New online bookings</span>
                    <input type="hidden" name="settings[auto_confirm]" value="0">
                    <label class="switch mt-1">
                        <input type="checkbox" name="settings[auto_confirm]" value="1" @checked((string) $v('auto_confirm', '0') === '1')>
                        <span class="track" aria-hidden="true"></span>
                        Confirm automatically
                    </label>
                    <div class="hint">Off: bookings wait as “Pending” until you confirm them.</div>
                </div>
                <div class="field full">
                    <label class="label" for="s-policy">Booking policy</label>
                    <textarea class="textarea" id="s-policy" name="settings[booking_policy]" maxlength="600" rows="3">{{ $v('booking_policy') }}</textarea>
                    <div class="hint">Shown to customers before they confirm.</div>
                </div>
            </div>
        </section>

        <section class="card card-pad">
            <h2 class="card-title">Social &amp; search</h2>
            <p class="card-subtitle mb-4">Links appear in the website footer.</p>
            <div class="form-grid">
                <div class="field">
                    <label class="label" for="s-ig">Instagram URL</label>
                    <input class="input" id="s-ig" name="settings[instagram_url]" value="{{ $v('instagram_url') }}" placeholder="https://instagram.com/…" @if($err('instagram_url')) aria-invalid="true" @endif>
                    @if($err('instagram_url'))<div class="error-text">{{ $err('instagram_url') }}</div>@endif
                </div>
                <div class="field">
                    <label class="label" for="s-fb">Facebook URL</label>
                    <input class="input" id="s-fb" name="settings[facebook_url]" value="{{ $v('facebook_url') }}" placeholder="https://facebook.com/…" @if($err('facebook_url')) aria-invalid="true" @endif>
                    @if($err('facebook_url'))<div class="error-text">{{ $err('facebook_url') }}</div>@endif
                </div>
                <div class="field">
                    <label class="label" for="s-wa">WhatsApp number</label>
                    <input class="input" id="s-wa" name="settings[whatsapp]" value="{{ $v('whatsapp') }}" inputmode="numeric" placeholder="94771234567" @if($err('whatsapp')) aria-invalid="true" @endif>
                    @if($err('whatsapp'))<div class="error-text">{{ $err('whatsapp') }}</div>@else<div class="hint">Country code + number, digits only.</div>@endif
                </div>
                <div class="field">
                    <label class="label" for="s-meta">Search description</label>
                    <input class="input" id="s-meta" name="settings[meta_description]" value="{{ $v('meta_description') }}" maxlength="160">
                    <div class="hint">Up to 160 characters, shown in Google results.</div>
                </div>
            </div>
        </section>

        <div class="row row-end">
            <button type="submit" class="btn btn-primary btn-lg">Save settings</button>
        </div>
    </form>

    @php $logo = $settings['salon_logo'] ?? null; $paper = old('receipt_paper', $settings['receipt_paper'] ?? 'a5'); @endphp
    <form method="POST" action="{{ route('admin.settings.branding') }}" enctype="multipart/form-data" class="card card-pad" id="branding" novalidate>
        @csrf
        <h2 class="card-title">Branding &amp; receipts</h2>
        <p class="card-subtitle mb-4">Your logo appears on printed receipts with the salon name and contact details above.</p>
        @if($errors->branding->any())
            <div class="alert alert-error mb-4" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><div>{{ $errors->branding->first() }}</div></div>
        @endif
        <div class="form-grid">
            <div class="field full">
                <span class="label">Logo <span class="optional">(PNG with a transparent background looks best)</span></span>
                <div class="file-drop file-drop-logo">
                    <span class="logo-preview">
                        <img id="logoPreview" src="{{ $logo ? asset('storage/' . $logo) : '' }}" alt="" @unless($logo) hidden @endunless>
                        @unless($logo)<span class="logo-placeholder" id="logoPlaceholder">{{ mb_substr(setting('salon_name', 'S'), 0, 1) }}</span>@endunless
                    </span>
                    <div class="stack-sm grow">
                        <label class="sr-only" for="logo">Upload logo</label>
                        <input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/webp" data-preview="logoPreview">
                        <div class="hint">Square or wide, at least 200 px. Without a logo, receipts show the salon’s initial.</div>
                        @if($logo)
                            <label class="check text-sm"><input type="checkbox" name="remove_logo" value="1"> Remove logo</label>
                        @endif
                    </div>
                </div>
            </div>
            <div class="field">
                <label class="label" for="receipt_paper">Receipt printer</label>
                <select class="select" id="receipt_paper" name="receipt_paper">
                    <option value="a5" @selected($paper === 'a5')>Normal printer — A5 / A4 paper</option>
                    <option value="80mm" @selected($paper === '80mm')>Receipt printer — 80 mm roll</option>
                </select>
                <div class="hint">Sets the receipt layout and size.</div>
            </div>
            <div class="field">
                <label class="label" for="receipt_footer">Thank-you message <span class="optional">(optional)</span></label>
                <textarea class="textarea" id="receipt_footer" name="receipt_footer" rows="2" maxlength="300" placeholder="e.g. We look forward to seeing you again. Follow us @falothes">{{ old('receipt_footer', $settings['receipt_footer'] ?? '') }}</textarea>
            </div>
        </div>
        <div class="form-actions" style="justify-content:space-between;">
            @php $sample = \App\Models\Appointment::whereIn('payment_status', ['paid', 'partial'])->latest('paid_at')->latest('id')->first(); @endphp
            @if($sample && admin_can('appointments.view'))
                <a href="{{ route('admin.appointments.receipt', $sample) }}" target="_blank" rel="noopener" class="btn btn-ghost">Preview a receipt <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
            @else
                <span></span>
            @endif
            <button type="submit" class="btn btn-primary">Save branding</button>
        </div>
    </form>

    <form method="POST" action="{{ route('admin.settings.test-email') }}" class="card card-pad" id="email" novalidate>
        @csrf
        <h2 class="card-title">Customer emails</h2>
        <p class="card-subtitle mb-4">Customers are emailed when their booking is confirmed or changes. Replies go to the salon email above.</p>
        @if(mail_is_live())
            <div class="alert alert-success mb-4"><i class="fa-solid fa-circle-check" aria-hidden="true"></i><div>Email is set up. Messages are sent from <strong>{{ config('mail.from.address') }}</strong>. Send a test to be sure it reaches an inbox.</div></div>
        @else
            <div class="alert alert-error mb-4" role="alert"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><div><strong>Customers aren’t receiving emails yet.</strong> Messages are only being written to the log file. Add your mail server details to the <code>.env</code> file to start sending.</div></div>
        @endif
        @if($errors->testEmail->any())
            <div class="alert alert-error mb-4" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><div>{{ $errors->testEmail->first() }}</div></div>
        @endif
        <div class="form-grid">
            <div class="field">
                <label class="label" for="test-to">Send a test email to</label>
                <input class="input" type="email" id="test-to" name="to" value="{{ old('to', auth('admin')->user()->email) }}" maxlength="120" required>
            </div>
            <div class="field" style="justify-content:flex-end;">
                <div><button type="submit" class="btn">Send test email</button></div>
            </div>
        </div>
    </form>

    <form method="POST" action="{{ route('admin.settings.cashier-pin') }}" class="card card-pad" id="cashier" novalidate>
        @csrf @method('PUT')
        <h2 class="card-title">Cashier station</h2>
        <p class="card-subtitle mb-4">
            The cashier screen is open to signed-in admins. Set a PIN so a front-desk device can use it without admin access.
            Status: <strong>{{ $hasPin ? 'PIN set' : 'No PIN' }}</strong>.
        </p>
        @if($errors->pin->any())
            <div class="alert alert-error mb-4" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><div>{{ $errors->pin->first() }}</div></div>
        @endif
        <div class="form-grid">
            <div class="field">
                <label class="label" for="pin">New PIN</label>
                <input class="input input-mono" type="password" id="pin" name="pin" inputmode="numeric" pattern="[0-9]*" maxlength="8" autocomplete="new-password">
                <div class="hint">4–8 digits.</div>
            </div>
            <div class="field">
                <label class="label" for="pin_confirmation">Confirm PIN</label>
                <input class="input input-mono" type="password" id="pin_confirmation" name="pin_confirmation" inputmode="numeric" pattern="[0-9]*" maxlength="8" autocomplete="new-password">
            </div>
        </div>
        <div class="form-actions" style="justify-content:space-between;">
            @perm('payments.take')<a href="{{ route('cashier.index') }}" target="_blank" rel="noopener" class="btn btn-ghost">Open cashier <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>@endperm
            <div class="row">
                @if($hasPin)
                    <button type="submit" name="remove_pin" value="1" class="btn btn-danger-soft" formnovalidate>Remove PIN</button>
                @endif
                <button type="submit" class="btn btn-primary">{{ $hasPin ? 'Change PIN' : 'Set PIN' }}</button>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('logo').addEventListener('change', function () {
    var ph = document.getElementById('logoPlaceholder');
    if (ph && this.files.length) ph.hidden = true;
});
</script>
@endpush
