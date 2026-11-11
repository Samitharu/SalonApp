@extends('layouts.admin')
@section('title', 'Offers')

@section('content')
<div class="page-head">
    <div>
        <h1>Offers</h1>
        <p>Discounts for visits between two dates. They switch on and off by themselves.</p>
    </div>
    <a href="{{ route('admin.offers.create') }}" class="btn btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add offer</a>
</div>

<div class="card">
    @if($offers->isEmpty())
        <div class="empty">
            <div class="empty-icon"><i class="fa-solid fa-tags" aria-hidden="true"></i></div>
            <h3>No offers yet</h3>
            <p>Add a discount with a start and end date, such as 20% off facials for the festive season.</p>
            <a href="{{ route('admin.offers.create') }}" class="btn btn-sm btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add offer</a>
        </div>
    @else
        <div class="table-wrap">
            <table class="table table-stack">
                <thead><tr><th>Offer</th><th>Visit dates</th><th>Applies to</th><th>Status</th><th class="num">Bookings</th><th class="actions"><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                    @foreach($offers as $offer)
                        @php $status = $offer->status(); @endphp
                        <tr class="{{ $status === 'ended' ? 'is-muted' : '' }}">
                            <td class="cell-primary">
                                <a href="{{ route('admin.offers.edit', $offer) }}" class="row-link">{{ $offer->name }}</a>
                                <div class="text-xs text-muted">{{ $offer->label() }}</div>
                            </td>
                            <td data-label="Visit dates" class="nowrap">{{ $offer->periodLabel() }}</td>
                            <td data-label="Applies to">{{ $offer->coversAll() ? 'All services' : $offer->services_count . ' ' . \Illuminate\Support\Str::plural('service', $offer->services_count) }}</td>
                            <td data-label="Status">
                                <span class="badge {{ ['running' => 'badge-completed', 'upcoming' => 'badge-confirmed', 'paused' => 'badge-pending', 'ended' => 'badge-neutral'][$status] }}">{{ ucfirst($status) }}</span>
                            </td>
                            <td data-label="Bookings" class="num">
                                {{ $offer->bookings_count }}
                                @if($offer->discount_given > 0)<div class="text-xs text-muted">{{ money($offer->discount_given) }} off</div>@endif
                            </td>
                            <td class="actions" data-label="">
                                <div class="row" style="flex-wrap:nowrap;">
                                    <a href="{{ route('admin.offers.edit', $offer) }}" class="btn btn-ghost btn-sm">Edit</a>
                                    <form method="POST" action="{{ route('admin.offers.destroy', $offer) }}"
                                          data-confirm="Delete “{{ $offer->name }}”?" data-confirm-message="Bookings already made keep their discounted price." data-confirm-button="Delete">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-ghost btn-icon btn-sm" aria-label="Delete {{ $offer->name }}"><i class="fa-regular fa-trash-can" aria-hidden="true"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
