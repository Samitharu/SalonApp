@extends('layouts.admin')
@section('title', 'Edit photo')
@section('crumb', 'Gallery')

@section('content')
<div class="page-head">
    <div><h1>Edit photo</h1></div>
</div>

<div class="gallery-edit">
    <figure class="card gallery-edit-media">
        <img src="{{ $image->url() }}" alt="{{ $image->altText() }}" @if($image->width) width="{{ $image->width }}" height="{{ $image->height }}" @endif>
        @if($image->width)
            <figcaption class="text-xs text-muted">{{ $image->width }} × {{ $image->height }} px · added {{ $image->created_at?->format('j M Y') }}</figcaption>
        @endif
    </figure>

    <form method="POST" action="{{ route('admin.gallery.update', $image) }}" class="card card-pad" novalidate>
        @csrf @method('PUT')
        <div class="stack">
            <div class="field">
                <label class="label" for="caption">Caption <span class="optional">(optional)</span></label>
                <input class="input" id="caption" name="caption" value="{{ old('caption', $image->caption) }}" maxlength="150" @error('caption') aria-invalid="true" @enderror>
                @error('caption')<div class="error-text">{{ $message }}</div>@else<div class="hint">Shown under the photo and read out by screen readers.</div>@enderror
            </div>
            <div class="field">
                <label class="label" for="service_category_id">Category</label>
                <select class="select" id="service_category_id" name="service_category_id">
                    <option value="">No category</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" @selected(old('service_category_id', $image->service_category_id) == $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field" style="max-width:200px;">
                <label class="label" for="sort_order">Display order</label>
                <input class="input" type="number" id="sort_order" name="sort_order" min="0" max="9999" value="{{ old('sort_order', $image->sort_order) }}">
                <div class="hint">Lower numbers first; newest first within the same number.</div>
            </div>
            <label class="switch">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $image->is_active))>
                <span class="track" aria-hidden="true"></span>
                Show on website
            </label>
        </div>
        <div class="form-actions">
            <a href="{{ route('admin.gallery.index') }}" class="btn">Cancel</a>
            <button type="submit" class="btn btn-primary">Save photo</button>
        </div>
    </form>
</div>
@endsection
