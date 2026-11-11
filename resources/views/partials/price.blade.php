{{-- A package's price today, with the usual price crossed out while an offer runs. Pass $service. --}}
<span class="price-line">
    @if($service->offerOn())<s class="price-was"><span class="sr-only">Usual price </span>{{ money($service->price) }}</s>@endif
    <span class="price">{{ money($service->priceOn()) }}</span>
</span>
