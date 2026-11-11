@php
    $salon = setting('salon_name', 'Salon');
    $date = \Carbon\Carbon::parse($a->appointment_date);
    $first = $a->customer ? \Illuminate\Support\Str::before(trim($a->customer->name), ' ') : null;
    $updated = $kind === 'updated';
    $contact = collect([setting('salon_address'), setting('salon_phone'), setting('salon_email')])->filter();
    $serif = "'Playfair Display', Georgia, 'Times New Roman', serif";
    $sans = "'Montserrat', 'Segoe UI', Helvetica, Arial, sans-serif";
    $paid = $a->paidSoFar();
    $owing = $a->advanceOutstanding();
    $rows = array_filter([
        'Service' => $a->service->name ?? null,
        'Stylist' => $a->staff->name ?? null,
        'Duration' => $a->service?->durationLabel(),
        ...($a->discount() > 0 ? [$a->offer_name ?: 'Offer' => '− ' . money($a->discount())] : []),
        'Price' => money($a->total()),
        'Advance received' => $paid > 0 ? money($paid) : null,
        'To pay at the salon' => $paid > 0 ? money($a->balanceDue()) : null,
    ]);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $updated ? 'Your booking has changed' : 'Your booking is confirmed' }}</title>
</head>
<body style="margin:0;padding:0;background:#F5F0E7;color:#2B2925;font-family:{{ $sans }};">
    <div style="display:none;max-height:0;overflow:hidden;">{{ $a->service->name ?? 'Your appointment' }} on {{ $date->format('l, j F') }} at {{ fmt_time($a->slot_start) }}. Reference {{ $a->appointment_no }}.</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F5F0E7;">
        <tr><td align="center" style="padding:28px 12px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#FFFFFF;border-top:4px solid #7A5A45;">

                <tr><td align="center" style="padding:32px 28px 8px;">
                    @if($logoPng)
                        <img src="{{ $message->embedData($logoPng, 'logo.png', 'image/png') }}" alt="" height="64" style="display:block;height:64px;max-width:200px;width:auto;margin:0 auto 12px;border:0;">
                    @endif
                    <div style="font-family:{{ $serif }};font-size:24px;letter-spacing:5px;text-transform:uppercase;color:#2B2925;">{{ $salon }}</div>
                    @if(setting('salon_tagline'))
                        <div style="font-family:{{ $serif }};font-style:italic;font-size:14px;color:#6B6357;margin-top:4px;">{{ setting('salon_tagline') }}</div>
                    @endif
                </td></tr>

                <tr><td style="padding:20px 28px 0;">
                    <div style="font-family:{{ $serif }};font-size:26px;line-height:1.25;color:#7A5A45;text-align:center;">{{ $updated ? 'Your booking has changed' : 'Your booking is confirmed' }}</div>
                    <p style="margin:12px 0 0;font-size:15px;line-height:1.6;color:#4A453D;text-align:center;">
                        {{ $first ? "Hello {$first}," : 'Hello,' }}
                        {{ $updated ? 'here are the new details of your appointment.' : 'we look forward to seeing you. Here are your appointment details.' }}
                    </p>
                </td></tr>

                {{-- Date & time: what people look for first --}}
                <tr><td style="padding:22px 28px 0;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F7F2EA;">
                        <tr><td align="center" style="padding:18px 12px;">
                            <div style="font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#6B6357;">{{ $date->format('l') }}</div>
                            <div style="font-family:{{ $serif }};font-size:24px;color:#2B2925;margin-top:4px;">{{ $date->format('j F Y') }}</div>
                            <div style="font-size:16px;font-weight:600;color:#2B2925;margin-top:4px;">{{ fmt_time($a->slot_start) }} – {{ fmt_time($a->slot_end) }}</div>
                        </td></tr>
                    </table>
                </td></tr>

                <tr><td style="padding:18px 28px 0;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <td style="padding:10px 0;border-bottom:1px solid #E3D8C6;font-size:14px;color:#6B6357;">Reference</td>
                            <td align="right" style="padding:10px 0;border-bottom:1px solid #E3D8C6;font-family:Consolas,Menlo,monospace;font-size:16px;font-weight:700;letter-spacing:1px;color:#2B2925;">{{ $a->appointment_no }}</td>
                        </tr>
                        @foreach($rows as $label => $value)
                            <tr>
                                <td style="padding:10px 0;border-bottom:1px solid #E3D8C6;font-size:14px;color:#6B6357;">{{ $label }}</td>
                                <td align="right" style="padding:10px 0 10px 12px;border-bottom:1px solid #E3D8C6;font-size:14px;font-weight:600;color:#2B2925;">{{ $value }}</td>
                            </tr>
                        @endforeach
                    </table>
                    <p style="margin:12px 0 0;font-size:13px;line-height:1.5;color:#6B6357;">Show this reference when you arrive.</p>
                </td></tr>

                @if($owing > 0)
                    <tr><td style="padding:18px 28px 0;">
                        <div style="padding:14px 16px;background:#FBF3E4;border-left:3px solid #A86A12;font-size:14px;line-height:1.55;color:#4A453D;">
                            <strong>Advance to pay: {{ money($owing) }}</strong><br>
                            Please use your reference <strong>{{ $a->appointment_no }}</strong> as the payment note.
                            @if(setting('bank_details'))<div style="margin-top:8px;white-space:pre-line;">{{ setting('bank_details') }}</div>@endif
                        </div>
                    </td></tr>
                @endif

                <tr><td align="center" style="padding:24px 28px 0;">
                    <a href="{{ route('booking.manage') }}" style="display:inline-block;padding:14px 26px;background:#7A5A45;color:#FFFFFF;font-size:13px;font-weight:600;letter-spacing:2px;text-transform:uppercase;text-decoration:none;border-radius:4px;">Manage my booking</a>
                    <p style="margin:12px 0 0;font-size:13px;line-height:1.5;color:#6B6357;">To change or cancel, use your reference and phone number.</p>
                </td></tr>

                @if(setting('booking_policy'))
                    <tr><td style="padding:18px 28px 0;font-size:13px;line-height:1.55;color:#6B6357;text-align:center;">{{ setting('booking_policy') }}</td></tr>
                @endif

                <tr><td align="center" style="padding:26px 28px 30px;">
                    <div style="height:1px;background:#E3D8C6;margin-bottom:18px;"></div>
                    <div style="font-size:12.5px;line-height:1.7;color:#6B6357;">
                        @foreach($contact as $line)<div>{{ $line }}</div>@endforeach
                        @if(setting('maps_url'))<a href="{{ setting('maps_url') }}" style="color:#7A5A45;">Get directions</a>@endif
                    </div>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
