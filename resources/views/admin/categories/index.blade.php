@extends('layouts.admin')
@section('title', 'Categories')

@section('content')
<div class="page-head">
    <div>
        <h1>Categories</h1>
        <p>Main categories appear on the homepage, each with its own page and hero photo.</p>
    </div>
    <a href="{{ route('admin.categories.create') }}" class="btn btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add category</a>
</div>

<div class="card">
    @if($categories->isEmpty())
        <div class="empty">
            <div class="empty-icon"><i class="fa-solid fa-layer-group" aria-hidden="true"></i></div>
            <h3>No categories yet</h3>
            <p>Categories like “Hair”, “Nails” or “Skin” make your menu easier to browse.</p>
            <a href="{{ route('admin.categories.create') }}" class="btn btn-sm"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add category</a>
        </div>
    @else
        <div class="table-wrap">
            <table class="table table-stack">
                <thead><tr><th>Category</th><th>Website</th><th class="num">Services</th><th class="num">Order</th><th class="actions"><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                    @foreach($categories as $cat)
                        <tr>
                            <td class="cell-primary">
                                <div class="row" style="flex-wrap:nowrap;">
                                    @if($cat->imageUrl())
                                        <img class="cat-thumb" src="{{ $cat->imageUrl() }}" alt="" loading="lazy" width="64" height="40">
                                    @else
                                        <span class="cat-thumb cat-thumb-empty" title="No hero photo yet"><i class="fa-regular fa-image" aria-hidden="true"></i></span>
                                    @endif
                                    <div>
                                        <a href="{{ route('admin.categories.edit', $cat) }}" class="row-link">{{ $cat->name }}</a>
                                        @if($cat->tagline)<div class="text-xs text-muted">{{ \Illuminate\Support\Str::limit($cat->tagline, 60) }}</div>@endif
                                    </div>
                                </div>
                            </td>
                            <td data-label="Website">
                                @if(!$cat->is_active)
                                    <span class="badge badge-neutral">Hidden</span>
                                @elseif(!$cat->bookable_count)
                                    <span class="badge badge-pending" title="Add a bookable service to show it">No services yet</span>
                                @else
                                    <a href="{{ route('services.category', $cat->slug) }}" target="_blank" rel="noopener" class="badge badge-completed">Live</a>
                                @endif
                                @if($cat->is_active && !$cat->image)
                                    <span class="badge badge-neutral no-dot">No photo</span>
                                @endif
                            </td>
                            <td data-label="Services" class="num"><a href="{{ route('admin.services.index', ['category' => $cat->id]) }}">{{ $cat->services_count }}</a></td>
                            <td data-label="Order" class="num">{{ $cat->sort_order ?? 0 }}</td>
                            <td class="actions" data-label="">
                                <div class="row">
                                    <a href="{{ route('admin.categories.edit', $cat) }}" class="btn btn-ghost btn-sm">Edit</a>
                                    <form method="POST" action="{{ route('admin.categories.destroy', $cat) }}"
                                          data-confirm="Delete “{{ $cat->name }}”?" data-confirm-message="Its {{ $cat->services_count }} {{ \Illuminate\Support\Str::plural('service', $cat->services_count) }} will be kept and become uncategorised." data-confirm-button="Delete">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-ghost btn-icon btn-sm" aria-label="Delete {{ $cat->name }}"><i class="fa-regular fa-trash-can" aria-hidden="true"></i></button>
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
