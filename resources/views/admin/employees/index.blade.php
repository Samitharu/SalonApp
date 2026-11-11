@extends('layouts.admin')
@section('title', 'Staff')

@section('content')
<div class="page-head">
    <div>
        <h1>Staff</h1>
        <p>Stylists customers can book, their working hours and portal access.</p>
    </div>
    <a href="{{ route('admin.employees.create') }}" class="btn btn-primary"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> Add staff</a>
</div>

<div class="card">
    @if($employees->isEmpty())
        <div class="empty">
            <div class="empty-icon"><i class="fa-solid fa-id-badge" aria-hidden="true"></i></div>
            <h3>No staff yet</h3>
            <p>Add your stylists so customers can choose who they book with.</p>
            <a href="{{ route('admin.employees.create') }}" class="btn btn-sm"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> Add staff</a>
        </div>
    @else
        <div class="table-wrap">
            <table class="table table-stack">
                <thead>
                    <tr><th>Name</th><th>Hours</th><th>Upcoming</th><th>Rating</th><th>Portal login</th><th class="actions"><span class="sr-only">Actions</span></th></tr>
                </thead>
                <tbody>
                    @foreach($employees as $e)
                        <tr>
                            <td class="cell-primary">
                                <div class="person">
                                    <span class="avatar" aria-hidden="true">{{ initials($e->name) }}</span>
                                    <div style="min-width:0;">
                                        <a href="{{ route('admin.employees.edit', $e) }}" class="row-link nowrap">{{ $e->name }}</a>
                                        @unless($e->is_active)<span class="badge badge-no_show no-dot" style="margin-left:6px;">Inactive</span>@endunless
                                        <div class="table-sub">{{ $e->title ?: 'Stylist' }}@if($e->phone) · {{ $e->phone }}@endif</div>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Hours" class="nowrap">
                                @if($e->work_start && $e->work_end)
                                    {{ fmt_time($e->work_start) }} – {{ fmt_time($e->work_end) }}
                                @else
                                    <span class="text-muted">Salon hours</span>
                                @endif
                                @if($e->break_start && $e->break_end)
                                    <div class="table-sub">Break {{ fmt_time($e->break_start) }} – {{ fmt_time($e->break_end) }}</div>
                                @endif
                            </td>
                            <td data-label="Upcoming">{{ $e->upcoming_count }}</td>
                            <td data-label="Rating">
                                @if($e->feedback_count)
                                    <a href="{{ route('admin.feedback.index', ['staff_id' => $e->id]) }}" class="nowrap" title="{{ $e->feedback_count }} {{ \Illuminate\Support\Str::plural('review', $e->feedback_count) }}">
                                        <i class="fa-solid fa-star" style="color:#e0a526;" aria-hidden="true"></i> {{ number_format($e->feedback_avg_rating, 1) }} <span class="text-muted">({{ $e->feedback_count }})</span>
                                    </a>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td data-label="Portal login">
                                @if($e->login_username)
                                    <span class="chip-code">{{ $e->login_username }}</span>
                                @else
                                    <span class="text-muted">Not set</span>
                                @endif
                            </td>
                            <td class="actions" data-label="">
                                <div class="row">
                                    <button type="button" class="btn btn-ghost btn-sm" data-login-open
                                            data-id="{{ $e->id }}" data-name="{{ $e->name }}" data-username="{{ $e->login_username }}"
                                            data-action="{{ route('admin.employees.set-login', $e) }}">
                                        <i class="fa-solid fa-key" aria-hidden="true"></i> Login
                                    </button>
                                    @if($e->table_qr_token)
                                        <button type="button" class="btn btn-ghost btn-sm" data-qr-open
                                                data-name="{{ $e->name }}" data-url="{{ route('stylist.walkin', ['token' => $e->table_qr_token]) }}"
                                                data-regen="{{ route('admin.employees.regenerate-qr', $e) }}">
                                            <i class="fa-solid fa-qrcode" aria-hidden="true"></i> QR
                                        </button>
                                    @else
                                        <form method="POST" action="{{ route('admin.employees.regenerate-qr', $e) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-ghost btn-sm"><i class="fa-solid fa-qrcode" aria-hidden="true"></i> Create QR</button>
                                        </form>
                                    @endif
                                    <a href="{{ route('admin.employees.edit', $e) }}" class="btn btn-ghost btn-sm">Edit</a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($employees->hasPages())<div class="card-footer">{{ $employees->links() }}</div>@endif
    @endif
</div>

{{-- Set login dialog --}}
<dialog class="dialog" id="loginDialog" aria-labelledby="loginDialogTitle">
    <form method="POST" id="loginForm" novalidate>
        @csrf
        <input type="hidden" name="_action" id="loginAction" value="{{ old('_action') }}">
        <div class="dialog-header">
            <div>
                <h2 class="dialog-title" id="loginDialogTitle">Portal login</h2>
                <p class="text-sm text-muted" id="loginDialogName"></p>
            </div>
            <button type="button" class="btn btn-ghost btn-icon btn-sm" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="dialog-body stack">
            @if($errors->login->any())
                <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><div>{{ $errors->login->first() }}</div></div>
            @endif
            <div class="field">
                <label class="label" for="login_username">Username</label>
                <input class="input" id="login_username" name="login_username" required maxlength="60" autocomplete="off" autocapitalize="none" value="{{ old('login_username') }}">
                <div class="hint">Letters, numbers, dots, dashes and underscores.</div>
            </div>
            <div class="field">
                <label class="label" for="login_password">New password</label>
                <input class="input" type="password" id="login_password" name="password" required minlength="8" autocomplete="new-password">
                <div class="hint">At least 8 characters.</div>
            </div>
            <div class="field">
                <label class="label" for="login_password_confirmation">Confirm password</label>
                <input class="input" type="password" id="login_password_confirmation" name="password_confirmation" required autocomplete="new-password">
            </div>
        </div>
        <div class="dialog-footer">
            <button type="button" class="btn" data-close>Cancel</button>
            <button type="submit" class="btn btn-primary">Save login</button>
        </div>
    </form>
</dialog>

{{-- Walk-in QR dialog --}}
<dialog class="dialog" id="qrDialog" aria-labelledby="qrDialogTitle">
    <div class="dialog-header">
        <div>
            <h2 class="dialog-title" id="qrDialogTitle">Walk-in QR</h2>
            <p class="text-sm text-muted" id="qrDialogName"></p>
        </div>
        <button type="button" class="btn btn-ghost btn-icon btn-sm" data-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="dialog-body text-center">
        <div class="qr-box" id="qrDialogCanvas"></div>
        <p class="text-sm mt-4">Place this at the stylist's station. When the stylist scans it while signed in, it opens the walk-in booking form.</p>
    </div>
    <div class="dialog-footer" style="justify-content:space-between;">
        <form method="POST" id="qrRegenForm" data-confirm="Generate a new QR?" data-confirm-message="The current printed QR will stop working." data-confirm-button="Generate new">
            @csrf
            <button type="submit" class="btn btn-ghost btn-sm"><i class="fa-solid fa-rotate" aria-hidden="true"></i> New code</button>
        </form>
        <button type="button" class="btn btn-primary" id="qrPrint"><i class="fa-solid fa-print" aria-hidden="true"></i> Print</button>
    </div>
</dialog>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var loginDialog = document.getElementById('loginDialog');
    var qrDialog = document.getElementById('qrDialog');
    var salon = @json(setting('salon_name', 'Salon'));

    document.querySelectorAll('dialog [data-close]').forEach(function (b) {
        b.addEventListener('click', function () { b.closest('dialog').close(); });
    });

    function openLogin(btn) {
        document.getElementById('loginForm').action = btn.dataset.action;
        document.getElementById('loginAction').value = btn.dataset.action;
        document.getElementById('loginDialogName').textContent = btn.dataset.name;
        document.getElementById('login_username').value = btn.dataset.username || '';
        loginDialog.dataset.employee = btn.dataset.id;
        loginDialog.showModal();
        document.getElementById('login_username').focus();
    }

    document.querySelectorAll('[data-login-open]').forEach(function (btn) {
        btn.addEventListener('click', function () { openLogin(btn); });
    });

    // Re-open the dialog after a validation error.
    @if($errors->login->any())
        (function () {
            var action = @json(old('_action'));
            var btn = action ? document.querySelector('[data-login-open][data-action="' + CSS.escape(action) + '"]') : null;
            if (btn) { openLogin(btn); document.getElementById('login_username').value = @json(old('login_username')); }
        })();
    @endif

    document.querySelectorAll('[data-qr-open]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var box = document.getElementById('qrDialogCanvas');
            box.innerHTML = '';
            document.getElementById('qrDialogName').textContent = btn.dataset.name;
            document.getElementById('qrRegenForm').action = btn.dataset.regen;
            if (window.QRCode) {
                new QRCode(box, { text: btn.dataset.url, width: 200, height: 200, colorDark: '#000', colorLight: '#fff', correctLevel: QRCode.CorrectLevel.M });
            }
            qrDialog.dataset.name = btn.dataset.name;
            qrDialog.showModal();
        });
    });

    document.getElementById('qrPrint').addEventListener('click', function () {
        var canvas = document.querySelector('#qrDialogCanvas canvas');
        if (!canvas) return;
        var w = window.open('', '_blank', 'width=480,height=640');
        if (!w) { UI.toast('Allow pop-ups to print the QR code.', 'error'); return; }
        var esc = UI.escapeHtml;
        w.document.write('<!doctype html><title>Walk-in QR</title><body style="font-family:sans-serif;text-align:center;padding:40px;">' +
            '<h2 style="margin:0 0 4px;">' + esc(salon) + '</h2><p style="color:#555;margin:0 0 24px;">' + esc(qrDialog.dataset.name) + ' — walk-in check-in</p>' +
            '<img src="' + canvas.toDataURL('image/png') + '" width="240" height="240" style="border:1px solid #ddd;padding:12px;border-radius:8px;">' +
            '<p style="color:#777;font-size:13px;margin-top:20px;">Stylist: scan while signed in to the portal</p></body>');
        w.document.close();
        w.onload = function () { w.focus(); w.print(); };
        setTimeout(function () { try { w.focus(); w.print(); } catch (e) {} }, 400);
    });
});
</script>
@endpush
