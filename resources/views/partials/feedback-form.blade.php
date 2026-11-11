{{--
  Star rating + comment form for a completed visit.
  @param \App\Models\Appointment $appointment
  @param string $action  POST URL
--}}
@php $a = $appointment; @endphp
<form method="POST" action="{{ $action }}" class="card card-pad stack" novalidate>
    @csrf
    <div>
        <h2 class="card-title">How was your visit?</h2>
        <p class="card-subtitle">Tell us about your {{ $a->service->name ?? 'appointment' }}{{ $a->staff ? ' with ' . $a->staff->name : '' }} on {{ \Carbon\Carbon::parse($a->appointment_date)->format('j M') }}.</p>
    </div>

    @if($errors->feedback->any())
        <div class="alert alert-error" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><div>{{ $errors->feedback->first() }}</div></div>
    @endif

    <fieldset class="fieldset">
        <legend class="label" style="padding:0;">Your rating <span class="req">*</span></legend>
        <div class="star-input" role="radiogroup" aria-label="Rating">
            @for($i = 5; $i >= 1; $i--)
                <input type="radio" id="rating-{{ $i }}" name="rating" value="{{ $i }}" @checked((int) old('rating') === $i)>
                <label for="rating-{{ $i }}" title="{{ $i }} {{ \Illuminate\Support\Str::plural('star', $i) }}">
                    <i class="fa-solid fa-star" aria-hidden="true"></i><span class="sr-only">{{ $i }} {{ \Illuminate\Support\Str::plural('star', $i) }}</span>
                </label>
            @endfor
        </div>
        <div class="star-caption text-sm text-muted" data-star-caption aria-live="polite">Tap a star to rate</div>
    </fieldset>

    <div class="field">
        <label class="label" for="comment">Comments <span class="optional">(optional)</span></label>
        <textarea class="textarea" id="comment" name="comment" maxlength="1000" rows="4" placeholder="What did you like? Anything we could do better?" data-count-target="commentCount">{{ old('comment') }}</textarea>
        <div class="hint" style="text-align:right;"><span id="commentCount">0</span> / 1000</div>
    </div>

    <label class="check"><input type="checkbox" name="allow_public" value="1" @checked(old('allow_public'))> You may show my review (first name and initial only) on your website</label>

    <div class="row row-end">
        <button type="submit" class="btn btn-primary" data-loading="Sending…"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Send feedback</button>
    </div>
</form>

@once
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var words = { 1: 'Poor', 2: 'Fair', 3: 'Good', 4: 'Very good', 5: 'Excellent' };
    document.querySelectorAll('.star-input').forEach(function (group) {
        var caption = group.parentNode.querySelector('[data-star-caption]');
        function update() {
            var on = group.querySelector('input:checked');
            if (caption) caption.textContent = on ? on.value + ' / 5 · ' + words[on.value] : 'Tap a star to rate';
        }
        group.addEventListener('change', update);
        update();
    });
    document.querySelectorAll('[data-count-target]').forEach(function (ta) {
        var out = document.getElementById(ta.dataset.countTarget);
        function count() { if (out) out.textContent = ta.value.length; }
        ta.addEventListener('input', count);
        count();
    });
});
</script>
@endpush
@endonce
