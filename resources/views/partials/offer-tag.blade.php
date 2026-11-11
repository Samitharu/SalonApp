{{-- Which offer a package has today, if any. Pass $service. --}}
@if($tagOffer = $service->offerOn())
    <span class="offer-tag"><i class="fa-solid fa-tag" aria-hidden="true"></i>{{ $tagOffer->label() }} · visits until {{ $tagOffer->ends_on->format('j M') }}</span>
@endif
