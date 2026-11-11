@extends('layouts.admin')
@section('title', 'Homepage hero')

@section('content')
<div class="page-head">
    <div>
        <h1>Homepage hero</h1>
        <p>The first active slide (lowest order) is shown at the top of the homepage. Without one, a default headline is used.</p>
    </div>
    <a href="{{ route('admin.sliders.create') }}" class="btn btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add slide</a>
</div>

<div class="card">
    @if($sliders->isEmpty())
        <div class="empty">
            <div class="empty-icon"><i class="fa-regular fa-image" aria-hidden="true"></i></div>
            <h3>Using the default hero</h3>
            <p>Add a slide to set your own headline, text, button and photo.</p>
            <a href="{{ route('admin.sliders.create') }}" class="btn btn-sm"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add slide</a>
        </div>
    @else
        @php $liveId = $sliders->where('is_active', true)->sortBy('sort_order')->first()?->id; @endphp
        <div class="table-wrap">
            <table class="table table-stack">
                <thead><tr><th>Slide</th><th>Button</th><th class="num">Order</th><th>Status</th><th class="actions"><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                    @foreach($sliders as $slider)
                        <tr>
                            <td class="cell-primary">
                                <div class="person">
                                    @if($slider->imageUrl())
                                        <img src="{{ $slider->imageUrl() }}" alt="" width="64" height="40" style="width:64px;height:40px;object-fit:cover;border-radius:6px;" loading="lazy">
                                    @endif
                                    <div style="min-width:0;">
                                        <a href="{{ route('admin.sliders.edit', $slider) }}" class="row-link">{{ $slider->title }}</a>
                                        @if($slider->subtitle)<div class="table-sub truncate" style="max-width:360px;">{{ $slider->subtitle }}</div>@endif
                                    </div>
                                </div>
                            </td>
                            <td data-label="Button">{{ $slider->button_text ?: '—' }}</td>
                            <td data-label="Order" class="num">{{ $slider->sort_order }}</td>
                            <td data-label="Status">
                                @if($slider->id === $liveId)
                                    <span class="badge badge-completed">Live</span>
                                @elseif($slider->is_active)
                                    <span class="badge badge-info">Active</span>
                                @else
                                    <span class="badge badge-no_show">Hidden</span>
                                @endif
                            </td>
                            <td class="actions" data-label="">
                                <div class="row">
                                    <a href="{{ route('admin.sliders.edit', $slider) }}" class="btn btn-ghost btn-sm">Edit</a>
                                    <form method="POST" action="{{ route('admin.sliders.destroy', $slider) }}" data-confirm="Delete this slide?" data-confirm-button="Delete">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-ghost btn-icon btn-sm" aria-label="Delete slide"><i class="fa-regular fa-trash-can" aria-hidden="true"></i></button>
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
