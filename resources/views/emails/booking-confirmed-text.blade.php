@php $first = $a->customer ? \Illuminate\Support\Str::before(trim($a->customer->name), ' ') : null; @endphp
{{ setting('salon_name', 'Salon') }}

{{ $kind === 'updated' ? 'Your booking has changed' : 'Your booking is confirmed' }}

{{ $first ? "Hello {$first}," : 'Hello,' }}

Date: {{ \Carbon\Carbon::parse($a->appointment_date)->format('l, j F Y') }}
Time: {{ fmt_time($a->slot_start) }} - {{ fmt_time($a->slot_end) }}
Reference: {{ $a->appointment_no }}
Service: {{ $a->service->name ?? '-' }}
@if($a->staff)
Stylist: {{ $a->staff->name }}
@endif
@if($a->discount() > 0)
{{ $a->offer_name ?: 'Offer' }}: - {{ money($a->discount()) }}
@endif
Price: {{ money($a->total()) }}
@if($a->paidSoFar() > 0)
Advance received: {{ money($a->paidSoFar()) }}
To pay at the salon: {{ money($a->balanceDue()) }}
@endif
@if($a->advanceOutstanding() > 0)

Advance to pay: {{ money($a->advanceOutstanding()) }}
Use your reference {{ $a->appointment_no }} as the payment note.
@if(setting('bank_details'))
{{ setting('bank_details') }}
@endif
@endif

Show this reference when you arrive.
To change or cancel, use your reference and phone number: {{ route('booking.manage') }}

{{ collect([setting('salon_address'), setting('salon_phone'), setting('salon_email')])->filter()->implode("\n") }}
