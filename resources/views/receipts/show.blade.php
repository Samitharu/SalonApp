@php
    use App\Models\Payment;
    $salon   = setting('salon_name', 'Salon');
    $logo    = setting('salon_logo');
    $title   = ['paid' => 'Payment receipt', 'partial' => 'Advance receipt', 'unpaid' => 'Bill'][$kind];
    $stamp   = ['paid' => 'Paid', 'partial' => 'Advance paid', 'unpaid' => 'Unpaid'][$kind];
    $first   = $a->customer ? \Illuminate\Support\Str::before(trim($a->customer->name), ' ') : null;
    $contact = collect([setting('salon_address'), setting('salon_phone'), setting('salon_email')])->filter();
    // Show the web address only when it's a real domain (not localhost or a bare IP).
    $host    = parse_url(config('app.url') ?: url('/'), PHP_URL_HOST) ?: '';
    $site    = $host && $host !== 'localhost' && !filter_var($host, FILTER_VALIDATE_IP) ? preg_replace('/^www\./', '', $host) : null;
    $thermal = $paper === '80mm';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} {{ $receiptNo }} · {{ $salon }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;1,400&family=Montserrat:wght@400;500;600;700&display=swap">
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <style>
        /* Receipt — Classic Luxury. Always light; pure black on thermal paper. */
        :root {
            --ink: #2B2925; --muted: #6B6357; --line: #E3D8C6; --soft: #F7F2EA; --accent: #7A5A45; --gold: #B8955B;
            --serif: 'Playfair Display', Georgia, 'Times New Roman', serif;
            --sans: 'Montserrat', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        }
        * { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body { margin: 0; background: #EDE6DA; color: var(--ink); font: 13px/1.5 var(--sans); }
        .toolbar { position: sticky; top: 0; z-index: 2; display: flex; justify-content: center; gap: 10px; padding: 12px; background: rgba(237, 230, 218, .92); backdrop-filter: blur(6px); }
        .toolbar a, .toolbar button { font: 600 12px/1 var(--sans); letter-spacing: .12em; text-transform: uppercase; padding: 12px 18px; border-radius: 4px; border: 1px solid #CDBEA5; background: #FFFDF9; color: var(--ink); text-decoration: none; cursor: pointer; }
        .toolbar button.primary { background: var(--accent); border-color: var(--accent); color: #fff; }

        .sheet { width: 148mm; max-width: calc(100% - 24px); margin: 8px auto 32px; background: #fff; padding: 14mm 12mm 12mm; box-shadow: 0 10px 40px rgba(58, 43, 32, .12); position: relative; }
        .sheet::before { content: ''; position: absolute; inset: 0 0 auto 0; height: 4px; background: linear-gradient(90deg, var(--accent), var(--gold), var(--accent)); }

        .r-head { text-align: center; }
        .r-logo { display: block; max-width: 170px; max-height: 70px; margin: 0 auto 10px; object-fit: contain; }
        .r-mono { width: 58px; height: 58px; margin: 0 auto 10px; border-radius: 50%; border: 1.5px solid var(--gold); display: flex; align-items: center; justify-content: center; font: italic 400 30px/1 var(--serif); color: var(--accent); }
        .r-salon { margin: 0; font: 500 24px/1.15 var(--serif); letter-spacing: .2em; text-transform: uppercase; }
        .r-tagline { margin: 4px 0 0; font: italic 400 13px/1.4 var(--serif); color: var(--muted); }
        .r-contact { margin: 8px 0 0; font-size: 11px; color: var(--muted); letter-spacing: .02em; }
        .r-contact span + span::before { content: '·'; margin: 0 7px; color: var(--gold); }

        .r-rule { display: flex; align-items: center; gap: 10px; margin: 14px 0; color: var(--gold); }
        .r-rule::before, .r-rule::after { content: ''; flex: 1; height: 1px; background: var(--line); }
        .r-rule i { width: 6px; height: 6px; transform: rotate(45deg); border: 1px solid currentColor; }

        .r-titlebar { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        .r-title { margin: 0; font: 600 12px/1 var(--sans); letter-spacing: .24em; text-transform: uppercase; }
        .r-stamp { font: 700 10.5px/1 var(--sans); letter-spacing: .18em; text-transform: uppercase; padding: 6px 10px; border: 1.5px solid currentColor; border-radius: 3px; color: #3E7A55; }
        .r-stamp.partial { color: #A86A12; }
        .r-stamp.unpaid { color: #A33A3A; }

        .r-meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px 20px; margin: 14px 0 0; }
        .r-meta div { min-width: 0; }
        .r-meta dt { font-size: 9.5px; font-weight: 600; letter-spacing: .16em; text-transform: uppercase; color: var(--muted); }
        .r-meta dd { margin: 2px 0 0; font-weight: 600; overflow-wrap: anywhere; }
        .r-mono-val { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; letter-spacing: .04em; }

        .r-items { width: 100%; border-collapse: collapse; margin-top: 18px; }
        .r-items th { font-size: 9.5px; font-weight: 600; letter-spacing: .16em; text-transform: uppercase; color: var(--muted); text-align: left; padding: 0 0 8px; border-bottom: 1.5px solid var(--ink); }
        .r-items td { padding: 10px 0; border-bottom: 1px solid var(--line); vertical-align: top; }
        .r-items .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .r-items .qty { width: 44px; text-align: center; }
        .r-item-name { font-weight: 600; }
        .r-item-detail { font-size: 11px; color: var(--muted); margin-top: 2px; }

        .r-totals { margin: 12px 0 0 auto; width: 62%; min-width: 220px; }
        .r-row { display: flex; justify-content: space-between; gap: 12px; padding: 4px 0; font-variant-numeric: tabular-nums; }
        .r-row span:first-child { color: var(--muted); }
        .r-row.total { margin-top: 6px; padding: 10px 12px; background: var(--soft); border-top: 1.5px solid var(--ink); font-size: 15px; font-weight: 700; }
        .r-row.total span:first-child { color: var(--ink); font: 600 11px/1.9 var(--sans); letter-spacing: .18em; text-transform: uppercase; }
        .r-row.balance { font-weight: 700; color: #A33A3A; }
        .r-row.balance span:first-child { color: #A33A3A; }

        .r-section-title { margin: 20px 0 6px; font-size: 9.5px; font-weight: 600; letter-spacing: .16em; text-transform: uppercase; color: var(--muted); }
        .r-pay { display: flex; justify-content: space-between; gap: 12px; padding: 7px 0; border-bottom: 1px dashed var(--line); font-variant-numeric: tabular-nums; }
        .r-pay small { display: block; font-size: 10.5px; color: var(--muted); }
        .r-pay > span, .r-row span:last-child { white-space: nowrap; }
        .r-phone { font-weight: 400; color: var(--muted); white-space: nowrap; }
        .thermal .r-meta dd .r-phone { display: block; }

        .r-foot { margin-top: 22px; text-align: center; }
        .r-thanks { margin: 0; font: italic 400 19px/1.3 var(--serif); color: var(--accent); }
        .r-note { margin: 6px auto 0; max-width: 90%; font-size: 11.5px; color: var(--muted); white-space: pre-line; }
        .r-qr { display: inline-flex; align-items: center; gap: 12px; margin-top: 16px; padding: 10px 14px; border: 1px solid var(--line); border-radius: 6px; text-align: left; }
        .r-qr img, .r-qr canvas { display: block; }
        .r-qr strong { display: block; font-size: 11px; letter-spacing: .14em; text-transform: uppercase; }
        .r-qr span { font-size: 11px; color: var(--muted); }
        .r-small { margin-top: 14px; font-size: 10px; color: var(--muted); letter-spacing: .04em; }

        /* 80 mm thermal roll: narrow, black only, no backgrounds. */
        .thermal .sheet { width: 80mm; padding: 6mm 4mm; box-shadow: none; }
        .thermal .sheet::before { display: none; }
        .thermal { --muted: #000; --line: #000; --soft: transparent; --accent: #000; --gold: #000; --ink: #000; }
        .thermal .r-salon { font-size: 18px; letter-spacing: .14em; }
        .thermal .r-logo { max-width: 52mm; max-height: 22mm; filter: grayscale(1) contrast(1.4); }
        .thermal .r-meta { grid-template-columns: 1fr; gap: 4px; }
        .thermal .r-meta div { display: flex; justify-content: space-between; gap: 8px; }
        .thermal .r-meta dd { margin: 0; text-align: right; }
        .thermal .r-totals { width: 100%; min-width: 0; }
        .thermal .r-row.total { padding: 8px 0; }
        .thermal .r-stamp, .thermal .r-row.balance, .thermal .r-row.balance span:first-child { color: #000; }
        .thermal .r-items .qty { width: 26px; }
        .thermal .r-qr { border: 0; padding: 0; flex-direction: column; text-align: center; }

        @page { size: A5 portrait; margin: 0; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0 auto; max-width: none; box-shadow: none; }
            .sheet::before, .r-row.total, .r-stamp { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            /* A5: keep a normal bill on one sheet. */
            body:not(.thermal) { font-size: 11.5px; }
            body:not(.thermal) .sheet { width: 148mm; padding: 7mm 10mm 6mm; }
            body:not(.thermal) .r-logo { max-height: 46px; margin-bottom: 4px; }
            body:not(.thermal) .r-meta { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            body:not(.thermal) .r-foot > .r-rule { display: none; }
            body:not(.thermal) .r-qr span { display: none; }
            body:not(.thermal) .r-mono { width: 46px; height: 46px; font-size: 24px; margin-bottom: 6px; }
            body:not(.thermal) .r-salon { font-size: 20px; }
            body:not(.thermal) .r-contact { margin-top: 4px; }
            body:not(.thermal) .r-rule { margin: 9px 0; }
            body:not(.thermal) .r-meta { margin-top: 9px; gap: 6px 18px; }
            body:not(.thermal) .r-items { margin-top: 10px; }
            body:not(.thermal) .r-items td { padding: 6px 0; }
            body:not(.thermal) .r-totals { margin-top: 6px; }
            body:not(.thermal) .r-row { padding: 2px 0; }
            body:not(.thermal) .r-row.total { padding: 7px 10px; font-size: 13.5px; }
            body:not(.thermal) .r-section-title { margin: 10px 0 2px; }
            body:not(.thermal) .r-pay { padding: 4px 0; }
            /* Footer side by side on paper: thank-you on the left, QR on the right. */
            body:not(.thermal) .r-foot { margin-top: 10px; display: grid; grid-template-columns: minmax(0, 1fr) auto; column-gap: 16px; align-items: center; text-align: left; }
            body:not(.thermal) .r-foot > .r-thanks { grid-column: 1; grid-row: 1; }
            body:not(.thermal) .r-foot > .r-note { grid-column: 1; grid-row: 2; margin: 2px 0 0; max-width: none; }
            body:not(.thermal) .r-foot > .r-qr { grid-column: 2; grid-row: 1 / span 2; }
            body:not(.thermal) .r-foot > .r-small { grid-column: 1 / -1; grid-row: 3; text-align: center; }
            body:not(.thermal) .r-thanks { font-size: 16px; }
            body:not(.thermal) .r-qr { margin-top: 0; padding: 6px 10px; }
            body:not(.thermal) .r-qr img, body:not(.thermal) .r-qr canvas { width: 64px !important; height: 64px !important; }
            body:not(.thermal) .r-small { margin-top: 6px; }
            .r-foot, .r-totals { break-inside: avoid; }
        }
        @media (max-width: 560px) {
            .sheet { padding: 22px 16px; }
            .r-meta { grid-template-columns: 1fr 1fr; }
            .r-totals { width: 100%; min-width: 0; }
        }
    </style>
    @if($thermal)
        <style>@page { size: 80mm auto; margin: 0; }</style>
    @endif
</head>
<body class="{{ $thermal ? 'thermal' : '' }}">

<div class="toolbar">
    <a href="{{ $backUrl }}">← Back</a>
    <button type="button" class="primary" onclick="window.print()">Print</button>
</div>

<main class="sheet" aria-label="{{ $title }}">
    <header class="r-head">
        @if($logo)
            <img class="r-logo" src="{{ asset('storage/' . $logo) }}" alt="{{ $salon }}" id="rLogo">
        @else
            <div class="r-mono" aria-hidden="true">{{ mb_substr($salon, 0, 1) }}</div>
        @endif
        <h1 class="r-salon">{{ $salon }}</h1>
        @if(setting('salon_tagline'))<p class="r-tagline">{{ setting('salon_tagline') }}</p>@endif
        @if($contact->isNotEmpty())
            <p class="r-contact">@foreach($contact as $c)<span>{{ $c }}</span>@endforeach</p>
        @endif
    </header>

    <div class="r-rule" aria-hidden="true"><i></i></div>

    <div class="r-titlebar">
        <h2 class="r-title">{{ $title }}</h2>
        <span class="r-stamp {{ $kind }}">{{ $stamp }}</span>
    </div>

    <dl class="r-meta">
        <div><dt>Receipt no.</dt><dd class="r-mono-val">{{ $receiptNo }}</dd></div>
        <div><dt>Date</dt><dd>{{ $issuedAt->format('j M Y, g:i A') }}</dd></div>
        <div><dt>Customer</dt><dd>{{ $a->customer->name ?? '—' }}@if($phone) <span class="r-phone">· {{ $phone }}</span>@endif</dd></div>
        <div><dt>Booking ref.</dt><dd class="r-mono-val">{{ $a->appointment_no }}</dd></div>
        <div><dt>Stylist</dt><dd>{{ $a->staff->name ?? '—' }}</dd></div>
        <div><dt>Appointment</dt><dd>{{ \Carbon\Carbon::parse($a->appointment_date)->format('j M Y') }}, {{ fmt_time($a->slot_start) }}</dd></div>
    </dl>

    <table class="r-items">
        <thead><tr><th>Description</th><th class="qty">Qty</th><th class="num">Amount</th></tr></thead>
        <tbody>
            @foreach($lines as $line)
                <tr>
                    <td>
                        <div class="r-item-name">{{ $line['name'] }}</div>
                        @if($line['detail'])<div class="r-item-detail">{{ $line['detail'] }}</div>@endif
                        @if($line['quantity'] > 1)<div class="r-item-detail">{{ money($line['price']) }} each</div>@endif
                    </td>
                    <td class="qty">{{ $line['quantity'] }}</td>
                    <td class="num">{{ money($line['total']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="r-totals">
        @if($lines->count() > 1 || $discount > 0)
            <div class="r-row"><span>Subtotal</span><span>{{ money($subtotal) }}</span></div>
        @endif
        @if($discount > 0)
            <div class="r-row"><span>{{ $a->offer_name ?: 'Offer' }}</span><span>− {{ money($discount) }}</span></div>
        @endif
        <div class="r-row total"><span>Total</span><span>{{ money($total) }}</span></div>
        @if($paid > 0)
            <div class="r-row"><span>Paid</span><span>{{ money($paid) }}</span></div>
        @endif
        @if($change > 0)
            <div class="r-row"><span>Change given</span><span>{{ money($change) }}</span></div>
        @endif
        @if($balance > 0.009)
            <div class="r-row balance"><span>Balance due</span><span>{{ money($balance) }}</span></div>
        @endif
    </div>

    @if($a->payments->isNotEmpty())
        <div class="r-section-title">Payments</div>
        @foreach($a->payments as $p)
            <div class="r-pay">
                <div>
                    {{ $p->kind === 'advance' ? 'Advance · ' : '' }}{{ Payment::methodLabel($p->method) }}
                    <small>{{ $p->received_at?->format('j M Y, g:i A') }}@if($p->reference) · Ref {{ $p->reference }}@endif @if($p->method === 'cash' && $p->cash_received) · received {{ money($p->cash_received) }}@endif</small>
                </div>
                <span>{{ money($p->amount) }}</span>
            </div>
        @endforeach
    @endif

    <footer class="r-foot">
        <div class="r-rule" aria-hidden="true"><i></i></div>
        <p class="r-thanks">Thank you{{ $first ? ', ' . $first : '' }}</p>
        <p class="r-note">{{ setting('receipt_footer') ?: 'We look forward to seeing you again.' }}</p>
        @if($feedbackUrl)
            <div class="r-qr">
                <div id="rQr" aria-hidden="true"></div>
                <div><strong>Rate your visit</strong><span>Scan with your phone camera</span></div>
            </div>
        @endif
        @php $small = collect([$site, setting('salon_phone')])->filter()->implode(' · '); @endphp
        @if($small)<div class="r-small">{{ $small }}</div>@endif
    </footer>
</main>

@if($feedbackUrl)
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
@endif
<script>
(function () {
    var feedback = @json($feedbackUrl);
    if (feedback && window.QRCode) {
        new QRCode(document.getElementById('rQr'), { text: feedback, width: {{ $thermal ? 96 : 84 }}, height: {{ $thermal ? 96 : 84 }}, colorDark: '#000000', colorLight: '#ffffff', correctLevel: QRCode.CorrectLevel.M });
    }
    @if($autoPrint)
    // Print once the fonts and logo are ready, so nothing prints blank.
    var logo = document.getElementById('rLogo');
    var ready = [document.fonts ? document.fonts.ready : Promise.resolve()];
    if (logo && !logo.complete) ready.push(new Promise(function (r) { logo.onload = logo.onerror = r; }));
    Promise.all(ready).then(function () { setTimeout(function () { window.print(); }, 150); });
    @endif
})();
</script>
</body>
</html>
