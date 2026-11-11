@extends('layouts.admin')
@section('title', 'Booking ' . $appointment->appointment_no)
@section('crumb', 'Appointments')

@php
    $a = $appointment;
    $date = \Carbon\Carbon::parse($a->appointment_date);
    $daysAway = (int) now()->startOfDay()->diffInDays($date->copy()->startOfDay(), false);
    $when = match (true) {
        $daysAway === 0  => 'Today',
        $daysAway === 1  => 'Tomorrow',
        $daysAway === -1 => 'Yesterday',
        $daysAway > 1    => 'In ' . $daysAway . ' days',
        default          => abs($daysAway) . ' days ago',
    };
    $actions = [
        'confirmed'   => ['Confirm', 'btn-primary', ['pending']],
        'in_progress' => ['Start', 'btn-soft', ['pending', 'confirmed']],
        'completed'   => ['Mark completed', 'btn-success', ['pending', 'confirmed', 'in_progress']],
        'no_show'     => ['No-show', '', ['pending', 'confirmed']],
        'cancelled'   => ['Cancel booking', 'btn-danger-soft', ['pending', 'confirmed', 'in_progress']],
        'pending'     => ['Reopen', '', ['cancelled', 'no_show']],
    ];
@endphp

@section('content')
<div class="page-head">
    <div>
        <div class="row row-wrap">
            <h1>{{ $a->customer->name ?? 'Booking' }}</h1>
            @include('partials.status', ['status' => $a->status])
        </div>
        <p>
            <span class="chip-code">{{ $a->appointment_no }}</span>
            · {{ $date->format('l, j F Y') }} · {{ fmt_time($a->slot_start) }} – {{ fmt_time($a->slot_end) }}
        </p>
    </div>
    <a href="{{ route('admin.appointments.index') }}" class="btn"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> All appointments</a>
</div>

<div class="dash-grid">
    <div class="stack">
        {{-- The appointment itself: when and with whom --}}
        <section class="card appt-when" aria-label="Appointment date and time">
            <div class="cal-tile" aria-hidden="true">
                <span class="cal-month">{{ $date->format('M') }}</span>
                <span class="cal-day">{{ $date->format('j') }}</span>
                <span class="cal-dow">{{ $date->format('D') }}</span>
            </div>
            <div class="appt-when-main">
                <div class="appt-when-label">Appointment · <span class="{{ $daysAway === 0 ? 'is-today' : '' }}">{{ $when }}</span></div>
                <div class="appt-when-date">{{ $date->format('l, j F Y') }}</div>
                <div class="appt-when-time">
                    <i class="fa-regular fa-clock" aria-hidden="true"></i> {{ fmt_time($a->slot_start) }} – {{ fmt_time($a->slot_end) }}
                    <span class="text-muted">· {{ $a->service ? $a->service->durationLabel() : '' }}</span>
                </div>
                <div class="appt-when-meta">
                    <span><i class="fa-solid fa-scissors" aria-hidden="true"></i> {{ $a->service->name ?? '—' }}</span>
                    <span><i class="fa-regular fa-user" aria-hidden="true"></i> {{ $a->staff->name ?? 'Any stylist' }}</span>
                </div>
            </div>
        </section>

        @perm('appointments.manage')
        <section class="card">
            <div class="card-header"><h2 class="card-title">Actions</h2></div>
            <div class="card-body row row-wrap">
                @php $shown = 0; @endphp
                @if($a->isChangeable())
                    @php $shown++; @endphp
                    <a href="{{ route('admin.appointments.reschedule', $a) }}" class="btn"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Change service / time</a>
                @endif
                @foreach($actions as $status => [$label, $class, $from])
                    @if(in_array($a->status, $from, true))
                        @php $shown++; @endphp
                        <form method="POST" action="{{ route('admin.appointments.status', $a) }}"
                              @if($status === 'cancelled') data-confirm="Cancel this booking?" data-confirm-message="The time slot will be released for other customers." data-confirm-button="Cancel booking" @endif
                              @if($status === 'no_show') data-confirm="Mark as no-show?" data-confirm-button="Mark no-show" @endif>
                            @csrf
                            <input type="hidden" name="status" value="{{ $status }}">
                            <button type="submit" class="btn {{ $class }}">{{ $label }}</button>
                        </form>
                    @endif
                @endforeach
                @if(!$shown)
                    <p class="text-sm text-muted">This booking is {{ strtolower(status_label($a->status)) }}. No further status changes are needed.</p>
                @endif
            </div>
        </section>
        @endperm

        <section class="card">
            <div class="card-header"><h2 class="card-title">Details</h2></div>
            <div class="card-body">
                <dl class="kv">
                    <div><dt>Appointment date</dt><dd>{{ $date->format('D, j M Y') }}</dd></div>
                    <div><dt>Time</dt><dd>{{ fmt_time($a->slot_start) }} – {{ fmt_time($a->slot_end) }}</dd></div>
                    <div><dt>Service</dt><dd>{{ $a->service->name ?? '—' }}</dd></div>
                    <div><dt>Stylist</dt><dd>{{ $a->staff->name ?? 'Unassigned' }}</dd></div>
                    <div><dt>Booked via</dt><dd>{{ $a->walk_in ? 'Walk-in' : ucfirst($a->type) }}</dd></div>
                    @unless($a->walk_in && !$a->confirmationEmail())
                        <div>
                            <dt>Confirmation email</dt>
                            <dd>
                                @if(!$a->confirmationEmail())
                                    <span class="text-danger">No email on this booking</span>
                                @elseif($a->confirmation_error)
                                    <span class="text-danger">Couldn’t be sent to {{ $a->confirmationEmail() }}</span>
                                @elseif(!mail_is_live())
                                    <span class="dd-muted">{{ $a->confirmationEmail() }} · @perm('settings.manage')<a href="{{ route('admin.settings.index') }}#email">email isn’t set up yet</a>@else email isn’t set up yet @endperm</span>
                                @elseif($a->confirmation_sent_at)
                                    <span class="dd-muted">Sent {{ $a->confirmation_sent_at->format('j M, g:i A') }} to {{ $a->confirmationEmail() }}</span>
                                @else
                                    <span class="dd-muted">{{ $a->status === 'pending' ? 'Sent to ' . $a->confirmationEmail() . ' when you confirm' : 'Not sent · ' . $a->confirmationEmail() }}</span>
                                @endif
                            </dd>
                        </div>
                    @endunless
                    <div><dt>Booking made</dt><dd class="dd-muted">{{ $a->created_at->format('j M Y, g:i A') }}</dd></div>
                    @if($a->started_at)<div><dt>Started</dt><dd>{{ $a->started_at->format('j M, g:i A') }}</dd></div>@endif
                    @if($a->completed_at)<div><dt>Completed</dt><dd>{{ $a->completed_at->format('j M, g:i A') }}</dd></div>@endif
                    @if($a->job_number)<div><dt>Job number</dt><dd><span class="chip-code">{{ $a->job_number }}</span></dd></div>@endif
                    @if($a->paid_at)<div><dt>Paid</dt><dd>{{ $a->paid_at->format('j M Y, g:i A') }}</dd></div>@endif
                    @if($a->cancelled_at)<div><dt>Cancelled</dt><dd>{{ $a->cancelled_at->format('j M, g:i A') }} by {{ $a->cancelled_by ?? 'admin' }}</dd></div>@endif
                    @if($a->rescheduled_at)<div><dt>Last changed</dt><dd>{{ $a->rescheduled_at->format('j M, g:i A') }} by {{ $a->rescheduled_by ?? 'admin' }}@if($a->reschedule_count) · {{ $a->reschedule_count }} online {{ \Illuminate\Support\Str::plural('change', $a->reschedule_count) }}@endif</dd></div>@endif
                </dl>
                @if($a->confirmation_error)
                    <div class="alert alert-error mt-4" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><div>The mail server said: {{ $a->confirmation_error }}</div></div>
                @endif
                @if($a->status === 'confirmed' && $a->confirmationEmail() && admin_can('appointments.manage'))
                    <form method="POST" action="{{ route('admin.appointments.confirmation-email', $a) }}" class="mt-4">
                        @csrf
                        <button type="submit" class="btn btn-sm" data-loading="Sending…"><i class="fa-regular fa-envelope" aria-hidden="true"></i> {{ $a->confirmation_sent_at || $a->confirmation_error ? 'Send confirmation again' : 'Send confirmation' }}</button>
                    </form>
                @endif
            </div>
        </section>

        @if($a->feedback)
            @php $fb = $a->feedback; @endphp
            <section class="card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Customer feedback</h2>
                        <div class="card-subtitle">{{ $fb->created_at->format('j M Y') }}{{ $fb->allow_public ? ' · may be shown on the website' : ' · private' }}</div>
                    </div>
                    <a href="{{ route('admin.feedback.index') }}" class="link-btn">All feedback</a>
                </div>
                <div class="card-body stack-sm">
                    <div class="stars" aria-label="{{ $fb->rating }} out of 5 stars">
                        @for($i = 1; $i <= 5; $i++)<i class="fa-{{ $i <= $fb->rating ? 'solid' : 'regular' }} fa-star" aria-hidden="true"></i>@endfor
                    </div>
                    @if($fb->comment)<p>“{{ $fb->comment }}”</p>@else<p class="text-sm text-muted">No comment left.</p>@endif
                </div>
            </section>
        @endif

        @php $locked = \App\Services\AppointmentCharges::isLocked($a); @endphp
        <section class="card" id="bill">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Bill</h2>
                    <div class="card-subtitle">{{ $locked ? ($a->payment_status === 'paid' ? 'Paid — the bill is closed.' : 'Closed') : (admin_can('appointments.manage') ? 'Add extras here; they appear on the cashier bill.' : 'Extras appear on the cashier bill.') }}</div>
                </div>
                <div class="row" style="flex-wrap:nowrap;">
                    @if($a->payment_status === 'paid')
                        <span class="badge badge-paid">Paid · {{ $a->paymentMethodLabel() }}</span>
                    @elseif($a->payment_status === 'partial')
                        <span class="badge badge-confirmed no-dot">Advance paid</span>
                    @else
                        <span class="badge badge-unpaid no-dot">Unpaid</span>
                    @endif
                    @unless(in_array($a->status, ['cancelled', 'no_show'], true) && $a->payments->isEmpty())
                        <a href="{{ route('admin.appointments.receipt', $a) }}" target="_blank" rel="noopener" class="btn btn-sm" title="Open a printable receipt">
                            <i class="fa-solid fa-print" aria-hidden="true"></i> {{ $a->payment_status === 'unpaid' ? 'Print bill' : 'Print receipt' }}
                        </a>
                    @endunless
                </div>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <tbody>
                        <tr>
                            <td><div class="fw-600">{{ $a->service->name ?? 'Service' }}</div><div class="table-sub">Main service</div></td>
                            <td class="num fw-600">{{ money($a->service->price ?? 0) }}</td>
                            <td class="actions"></td>
                        </tr>
                        @if($a->discount() > 0)
                            <tr>
                                <td><div class="fw-600">{{ $a->offer_name ?: 'Offer' }}</div><div class="table-sub">Offer, added when booked</div></td>
                                <td class="num fw-600 text-success">− {{ money($a->discount()) }}</td>
                                <td class="actions"></td>
                            </tr>
                        @endif
                        @foreach($a->items as $item)
                            <tr>
                                <td>
                                    <div class="fw-600">{{ $item->name }}@if($item->quantity > 1) <span class="text-muted">× {{ $item->quantity }}</span>@endif</div>
                                    <div class="table-sub">Extra · added by {{ $item->added_by_name ?: ucfirst((string) $item->added_by) }}@if($item->quantity > 1) · {{ money($item->price) }} each @endif</div>
                                </td>
                                <td class="num fw-600">{{ money($item->lineTotal()) }}</td>
                                <td class="actions">
                                    @if(!$locked && admin_can('appointments.manage'))
                                        <form method="POST" action="{{ route('admin.appointments.items.destroy', [$a, $item]) }}"
                                              data-confirm="Remove “{{ $item->name }}”?" data-confirm-message="{{ money($item->lineTotal()) }} will be taken off the bill." data-confirm-button="Remove">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-ghost btn-icon btn-sm" aria-label="Remove {{ $item->name }}"><i class="fa-regular fa-trash-can" aria-hidden="true"></i></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-body stack-sm" style="padding-top:var(--s-3);">
                <div class="total-row"><span class="fw-600">Total</span><span class="amount" style="font-size:22px;">{{ money($a->total()) }}</span></div>

                {{-- Payments: advances and (split) settlement --}}
                @if($a->payments->isNotEmpty())
                    <div class="label mt-2">Payments</div>
                    <ul class="payment-list">
                        @foreach($a->payments as $p)
                            <li>
                                <i class="fa-solid {{ \App\Models\Payment::ICONS[$p->method] ?? 'fa-coins' }} payment-icon" aria-hidden="true"></i>
                                <div class="payment-main">
                                    <div class="fw-600">{{ $p->label() }} @if($p->kind === 'advance')<span class="badge badge-confirmed no-dot">Advance</span>@endif</div>
                                    <div class="table-sub">
                                        {{ $p->received_at->format('j M Y, g:i A') }} · {{ $p->received_by_name ?: ucfirst((string) $p->received_by) }}
                                        @if($p->reference) · Ref <span class="mono">{{ $p->reference }}</span>@endif
                                        @if($p->change() > 0) · cash {{ money($p->cash_received) }}, change {{ money($p->change()) }}@endif
                                        @if($p->note) · {{ $p->note }}@endif
                                    </div>
                                </div>
                                <span class="tabular fw-600">− {{ money($p->amount) }}</span>
                                @perm('payments.void')
                                <form method="POST" action="{{ route('admin.appointments.payments.destroy', [$a, $p]) }}"
                                      data-confirm="Remove this {{ $p->kind === 'advance' ? 'advance' : 'payment' }}?"
                                      data-confirm-message="{{ money($p->amount) }} by {{ $p->label() }} will be taken off this booking — use this for mistakes or refunds. It’s recorded in the system log."
                                      data-confirm-button="Remove">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-ghost btn-icon btn-sm" aria-label="Remove payment of {{ money($p->amount) }}"><i class="fa-regular fa-trash-can" aria-hidden="true"></i></button>
                                </form>
                                @endperm
                            </li>
                        @endforeach
                    </ul>
                    <div class="total-row"><span class="fw-700">{{ $a->payment_status === 'paid' ? 'Fully paid' : 'Balance due' }}</span><span class="amount" style="font-size:22px;">{{ money($a->balanceDue()) }}</span></div>
                @endif

                @if($a->advanceRequired() > 0 && $a->payment_status !== 'paid' && !in_array($a->status, ['cancelled', 'no_show'], true))
                    <div class="alert {{ $a->advanceOutstanding() > 0 ? 'alert-warning' : 'alert-success' }}">
                        <i class="fa-solid {{ $a->advanceOutstanding() > 0 ? 'fa-hand-holding-dollar' : 'fa-circle-check' }}" aria-hidden="true"></i>
                        <div>
                            @if($a->advanceOutstanding() > 0)
                                <strong>Advance needed:</strong> {{ money($a->advanceRequired()) }} for {{ $a->service->name }}.
                                {{ $a->advancePaid() > 0 ? money($a->advancePaid()) . ' received, ' . money($a->advanceOutstanding()) . ' still to come.' : 'Not received yet.' }}
                            @else
                                Advance of {{ money($a->advancePaid()) }} received.
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            @if(admin_can('payments.take') && $a->payment_status !== 'paid' && !in_array($a->status, ['cancelled', 'no_show'], true) && ($a->balanceDue() > 0 || in_array($a->status, ['in_progress', 'completed'], true)))
                @php
                    $settle = in_array($a->status, ['in_progress', 'completed'], true);
                    $suggest = $settle ? $a->balanceDue() : ($a->advanceOutstanding() > 0 ? min($a->advanceOutstanding(), $a->balanceDue()) : null);
                @endphp
                <form method="POST" action="{{ route('admin.appointments.payments.store', $a) }}" class="card-footer stack" novalidate>
                    @csrf
                    <input type="hidden" name="kind" value="{{ $settle ? 'payment' : 'advance' }}">
                    <div>
                        <div class="fw-700 text-sm">{{ $settle ? 'Settle the balance' : 'Record an advance' }}</div>
                        <div class="hint">{{ $settle ? 'For a balance paid by bank transfer or QR after the visit. At the counter, use the cashier station (it can split across methods).' : 'Money received before the visit — e.g. a bridal deposit by bank transfer or QR. It comes off the bill at the cashier.' }}</div>
                    </div>
                    @if($errors->payment->any())
                        <div class="alert alert-error" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><div>{{ $errors->payment->first() }}</div></div>
                    @endif
                    <div class="form-grid">
                        <div class="field">
                            <label class="label" for="payMethod">Method</label>
                            <select class="select" id="payMethod" name="method">
                                @foreach(\App\Models\Payment::METHODS as $key => $label)
                                    <option value="{{ $key }}" @selected(old('method', $settle ? 'bank_transfer' : 'bank_transfer') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label class="label" for="payAmountInput">Amount ({{ setting('currency', 'LKR') }})</label>
                            <input class="input tabular" id="payAmountInput" name="amount" type="number" inputmode="decimal" min="0.01" step="0.01"
                                   value="{{ old('amount', $suggest) }}" @if($settle) readonly @endif max="{{ $a->balanceDue() }}">
                            <div class="hint">{{ $settle ? 'Must match the balance due.' : 'Up to ' . money($a->balanceDue()) . '.' }}</div>
                        </div>
                        <div class="field">
                            <label class="label" for="payRef">Reference <span class="optional">(optional)</span></label>
                            <input class="input" id="payRef" name="reference" maxlength="100" value="{{ old('reference') }}" placeholder="Bank slip / QR transaction ID">
                        </div>
                        <div class="field">
                            <label class="label" for="payNoteAdmin">Note <span class="optional">(optional)</span></label>
                            <input class="input" id="payNoteAdmin" name="note" maxlength="255" value="{{ old('note') }}">
                        </div>
                    </div>
                    <div class="row row-end">
                        <button type="submit" class="btn btn-primary" data-loading="Saving…"><i class="fa-solid fa-hand-holding-dollar" aria-hidden="true"></i> {{ $settle ? 'Record payment' : 'Record advance' }}</button>
                    </div>
                </form>
            @endif

            @if(!$locked && admin_can('appointments.manage'))
                <form method="POST" action="{{ route('admin.appointments.items.store', $a) }}" class="card-footer stack" id="extraForm" novalidate>
                    @csrf
                    <div class="fw-700 text-sm">Add an extra</div>
                    @if($errors->extras->any())
                        <div class="alert alert-error" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><div>{{ $errors->extras->first() }}</div></div>
                    @endif
                    <div class="form-grid">
                        <div class="field full">
                            <label class="label" for="extraService">Item</label>
                            <select class="select" id="extraService" name="service_id">
                                @php $addons = collect($catalog)->where('addon', true); $others = collect($catalog)->where('addon', false); @endphp
                                @if($addons->isNotEmpty())
                                    <optgroup label="Add-ons">
                                        @foreach($addons as $c)<option value="{{ $c['id'] }}" @selected((string) old('service_id') === (string) $c['id'])>{{ $c['name'] }} — {{ $c['price_label'] }}</option>@endforeach
                                    </optgroup>
                                @endif
                                @if($others->isNotEmpty())
                                    <optgroup label="Services">
                                        @foreach($others as $c)<option value="{{ $c['id'] }}" @selected((string) old('service_id') === (string) $c['id'])>{{ $c['name'] }} — {{ $c['price_label'] }}</option>@endforeach
                                    </optgroup>
                                @endif
                                <option value="" @selected(old('service_id') === '' || empty($catalog))>Custom charge…</option>
                            </select>
                        </div>
                        <div class="field" data-custom>
                            <label class="label" for="extraName">Description</label>
                            <input class="input" id="extraName" name="name" maxlength="120" value="{{ old('name') }}" placeholder="e.g. Long hair surcharge">
                        </div>
                        <div class="field" data-custom>
                            <label class="label" for="extraPrice">Amount ({{ setting('currency', 'LKR') }})</label>
                            <input class="input tabular" id="extraPrice" name="price" type="number" min="0" step="0.01" inputmode="decimal" value="{{ old('price') }}">
                        </div>
                        <div class="field">
                            <label class="label" for="extraQty">Quantity</label>
                            <input class="input" id="extraQty" name="quantity" type="number" min="1" max="20" value="{{ old('quantity', 1) }}" style="max-width:120px;">
                        </div>
                    </div>
                    <div class="row row-end"><button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add to bill</button></div>
                </form>
            @endif
        </section>

        @perm('appointments.manage')
        <form method="POST" action="{{ route('admin.appointments.update', $a) }}" class="card">
            @csrf @method('PUT')
            <div class="card-header"><h2 class="card-title">Assignment &amp; notes</h2></div>
            <div class="card-body stack">
                <div class="field">
                    <label class="label" for="staff_id">Stylist</label>
                    <select class="select" id="staff_id" name="staff_id">
                        <option value="">Unassigned</option>
                        @foreach($staff as $s)
                            <option value="{{ $s->id }}" @selected((int) $a->staff_id === (int) $s->id)>{{ $s->name }}</option>
                        @endforeach
                        @if($a->staff && !$staff->contains('id', $a->staff_id))
                            <option value="{{ $a->staff_id }}" selected>{{ $a->staff->name }} (inactive)</option>
                        @endif
                    </select>
                </div>
                <div class="field">
                    <label class="label" for="contact_email">Email for this booking</label>
                    <input class="input" type="email" id="contact_email" name="contact_email" value="{{ old('contact_email', $a->confirmationEmail()) }}" maxlength="120" autocomplete="off" @error('contact_email') aria-invalid="true" @enderror>
                    @error('contact_email')<div class="error-text">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label class="label" for="notes">Notes</label>
                    <textarea class="textarea" id="notes" name="notes" maxlength="1000" rows="4" placeholder="Customer requests, allergies, follow-ups">{{ old('notes', $a->notes) }}</textarea>
                </div>
                <div class="row row-end"><button type="submit" class="btn btn-primary">Save changes</button></div>
            </div>
        </form>
        @endperm
    </div>

    <div class="stack">
        <section class="card">
            <div class="card-header">
                <h2 class="card-title">Customer</h2>
                @if($a->customer && admin_can('customers.view'))<a href="{{ route('admin.customers.show', $a->customer) }}" class="link-btn">Profile</a>@endif
            </div>
            <div class="card-body">
                <div class="person mb-4">
                    <span class="avatar avatar-lg" aria-hidden="true">{{ initials($a->customer->name ?? '?') }}</span>
                    <div>
                        <div class="person-name">{{ $a->customer->name ?? '—' }}</div>
                        @if($a->customer?->phone)
                            <a href="tel:{{ $a->customer->phone }}" class="person-meta">{{ $a->customer->phone }}</a>
                        @endif
                        @if($a->customer?->email)
                            <div class="person-meta">{{ $a->customer->email }}</div>
                        @endif
                    </div>
                </div>
                @if($a->customer?->notes)
                    <div class="panel-muted text-sm">{{ $a->customer->notes }}</div>
                @endif
            </div>
            @if($history->isNotEmpty())
                <div class="card-footer">
                    <div class="text-xs fw-700 text-muted mb-2" style="text-transform:uppercase;letter-spacing:.06em;">Other bookings</div>
                    <ul style="list-style:none;padding:0;" class="stack-sm">
                        @foreach($history as $h)
                            <li class="row row-between text-sm">
                                <a href="{{ route('admin.appointments.show', $h) }}">{{ \Carbon\Carbon::parse($h->appointment_date)->format('j M Y') }} · {{ $h->service->name ?? '—' }}</a>
                                @include('partials.status', ['status' => $h->status])
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </section>

        @if($productsUsed->isNotEmpty())
            <section class="card" aria-labelledby="used-title">
                <div class="card-header"><div><h2 class="card-title" id="used-title">Products used</h2><div class="card-subtitle">Taken from stock when completed</div></div></div>
                <ul class="list-plain">
                    @foreach($productsUsed as $u)
                        <li class="row row-between">
                            @perm('stock.view')<a href="{{ route('admin.products.show', $u['product']) }}">{{ $u['product']->name }}</a>@else<span>{{ $u['product']->name }}</span>@endperm
                            <span class="tabular text-sm">{{ $u['product']->qty($u['qty']) }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="card">
            <div class="card-header">
                <h2 class="card-title">Activity</h2>
                @if($activity->isNotEmpty() && admin_can('logs.view'))
                    <a href="{{ route('admin.logs.index', ['subject' => 'Appointment', 'id' => $a->id]) }}" class="link-btn">Full history</a>
                @endif
            </div>
            <div class="card-body">
                @if($activity->isEmpty())
                    <p class="text-sm text-muted">No recorded activity yet. Changes from now on appear here.</p>
                @else
                    <ol class="activity-list">
                        @foreach($activity as $log)
                            <li class="activity-item activity-{{ $log->level }}">
                                <div class="text-sm">{{ $log->description }}</div>
                                <div class="table-sub">{{ $log->created_at->format('j M, g:i A') }} · {{ $log->actorLabel() }}</div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        </section>

        @perm('appointments.delete')
        <section class="card">
            <div class="card-header"><h2 class="card-title">Danger zone</h2></div>
            <div class="card-body">
                <p class="text-sm text-muted mb-4">Deleting removes this booking permanently. To keep a record, cancel it instead.</p>
                <form method="POST" action="{{ route('admin.appointments.destroy', $a) }}"
                      data-confirm="Delete booking {{ $a->appointment_no }}?" data-confirm-message="This cannot be undone." data-confirm-button="Delete">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger-soft"><i class="fa-regular fa-trash-can" aria-hidden="true"></i> Delete booking</button>
                </form>
            </div>
        </section>
        @endperm
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var pick = document.getElementById('extraService');
    if (!pick) return;
    function sync() {
        var custom = pick.value === '';
        document.querySelectorAll('[data-custom]').forEach(function (el) { el.hidden = !custom; });
        if (!custom) { document.getElementById('extraName').value = ''; document.getElementById('extraPrice').value = ''; }
    }
    pick.addEventListener('change', sync);
    sync();
});
</script>
@endpush
