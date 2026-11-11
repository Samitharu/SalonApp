<button type="button" class="option" role="radio" aria-checked="false" data-service="{{ $s->id }}"
        data-search="{{ mb_strtolower($s->name . ' ' . $s->description . ' ' . ($catName ?? '')) }}">
    <span class="option-check"><i class="fa-solid fa-check" aria-hidden="true"></i></span>
    @if($s->imageUrl())
        <img class="option-thumb" src="{{ $s->imageUrl() }}" alt="" width="56" height="56" loading="lazy" decoding="async">
    @endif
    <span class="option-main">
        <span class="option-title">{{ $s->name }}</span>
        <span class="option-sub">{{ $s->durationLabel() }}@if($s->description) · {{ \Illuminate\Support\Str::limit($s->description, 70) }}@endif</span>
        @include('partials.offer-tag', ['service' => $s])
        @if($s->advanceLabel())
            <span class="option-advance"><i class="fa-solid fa-hand-holding-dollar" aria-hidden="true"></i> {{ $s->advanceLabel() }} to book</span>
        @endif
    </span>
    <span class="option-aside">@include('partials.price', ['service' => $s])</span>
</button>
