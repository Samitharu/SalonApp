{{-- Session flash messages rendered as auto-dismissing toasts. --}}
<div id="toast-region" class="toast-region" role="status" aria-live="polite">
    @if(session('success'))
        <div class="toast toast-success">
            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="toast-close" aria-label="Dismiss"><i class="fa-solid fa-xmark"></i></button>
        </div>
    @endif
    @if(session('error'))
        <div class="toast toast-error" role="alert">
            <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
            <div>{{ session('error') }}</div>
            <button type="button" class="toast-close" aria-label="Dismiss"><i class="fa-solid fa-xmark"></i></button>
        </div>
    @endif
</div>
