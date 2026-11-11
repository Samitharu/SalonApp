@extends('layouts.admin')
@section('title', 'Services')

@section('content')
<div class="page-head">
    <div>
        <h1>Services</h1>
        <p>What customers can book, plus add-ons that can be added to a bill as extra charges.</p>
    </div>
    <a href="{{ route('admin.services.create') }}" class="btn btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add service</a>
</div>

<div class="card">
    <form method="GET" class="toolbar" role="search">
        <div class="field grow">
            <label class="label" for="search">Search</label>
            <div class="input-icon">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input class="input input-sm" id="search" name="search" value="{{ request('search') }}" placeholder="Service name">
            </div>
        </div>
        <div class="field">
            <label class="label" for="category">Category</label>
            <select class="select select-sm" id="category" name="category" data-autosubmit>
                <option value="">All categories</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" @selected((string) request('category') === (string) $cat->id)>{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="row">
            <button class="btn btn-ink btn-sm" type="submit">Apply</button>
            @if(request('search') || request('category'))<a href="{{ route('admin.services.index') }}" class="btn btn-ghost btn-sm">Clear</a>@endif
        </div>
    </form>

    @if($services->isEmpty())
        <div class="empty">
            <div class="empty-icon"><i class="fa-solid fa-scissors" aria-hidden="true"></i></div>
            <h3>{{ request('search') || request('category') ? 'No services match' : 'No services yet' }}</h3>
            <p>Add your first service so customers can book online.</p>
            <a href="{{ route('admin.services.create') }}" class="btn btn-sm"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add service</a>
        </div>
    @else
        <div class="table-wrap">
            <table class="table table-stack">
                <thead>
                    <tr>
                        <th>Service</th>
                        <th>Category</th>
                        <th class="num">Price</th>
                        <th>Duration</th>
                        <th>Visibility</th>
                        <th class="actions"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($services as $service)
                        <tr>
                            <td class="cell-primary">
                                <div class="person">
                                    @if($service->imageUrl())
                                        <img src="{{ $service->imageUrl() }}" alt="" width="40" height="40" style="width:40px;height:40px;border-radius:8px;object-fit:cover;" loading="lazy">
                                    @else
                                        <span class="avatar" style="border-radius:8px;" aria-hidden="true">{{ mb_substr($service->name, 0, 1) }}</span>
                                    @endif
                                    <div style="min-width:0;">
                                        <a href="{{ route('admin.services.edit', $service) }}" class="row-link">{{ $service->name }}</a>
                                        <div class="table-sub">{{ $service->appointments_count }} {{ \Illuminate\Support\Str::plural('booking', $service->appointments_count) }}</div>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Category">{{ $service->category->name ?? '—' }}</td>
                            <td data-label="Price" class="num fw-600">{{ money($service->price) }}</td>
                            <td data-label="Duration">
                                {{ $service->durationLabel() }}
                                @if($service->buffer_minutes)<div class="table-sub">+ {{ $service->buffer_minutes }} min cleanup</div>@endif
                            </td>
                            <td data-label="Visibility">
                                @if(!$service->is_active)
                                    <span class="badge badge-no_show">Hidden</span>
                                @elseif($service->is_addon)
                                    <span class="badge badge-accent">Add-on</span>
                                @else
                                    <span class="badge badge-completed">Bookable</span>
                                @endif
                            </td>
                            <td class="actions" data-label="">
                                <div class="row">
                                    <a href="{{ route('admin.services.edit', $service) }}" class="btn btn-ghost btn-sm">Edit</a>
                                    <form method="POST" action="{{ route('admin.services.destroy', $service) }}"
                                          data-confirm="Delete “{{ $service->name }}”?"
                                          data-confirm-message="{{ $service->appointments_count ? 'This service has bookings, so it will be hidden rather than deleted.' : 'This cannot be undone.' }}"
                                          data-confirm-button="{{ $service->appointments_count ? 'Hide service' : 'Delete' }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-ghost btn-icon btn-sm" aria-label="Delete {{ $service->name }}"><i class="fa-regular fa-trash-can" aria-hidden="true"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($services->hasPages())<div class="card-footer">{{ $services->links() }}</div>@endif
    @endif
</div>
@endsection
