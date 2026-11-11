@extends('layouts.admin')
@php
    $editing = $category->exists;
    $maxKb = \App\Http\Controllers\Admin\ServiceCategoryController::IMAGE_MAX_KB;
@endphp
@section('title', $editing ? 'Edit category' : 'Add category')
@section('crumb', 'Categories')

@section('content')
<div class="page-head">
    <div>
        <h1>{{ $editing ? $category->name : 'Add category' }}</h1>
        @if($editing && $category->is_active && $category->services()->bookable()->exists())
            <p><a href="{{ route('services.category', $category->slug) }}" target="_blank" rel="noopener">View on website <i class="fa-solid fa-arrow-up-right-from-square text-xs" aria-hidden="true"></i></a></p>
        @endif
    </div>
</div>

<form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('admin.categories.update', $category) : route('admin.categories.store') }}" class="card card-pad" style="max-width:760px;" novalidate>
    @csrf
    @if($editing) @method('PUT') @endif
    <div class="form-grid">
        <div class="field full">
            <label class="label" for="name">Name <span class="req">*</span></label>
            <input class="input" id="name" name="name" value="{{ old('name', $category->name) }}" required maxlength="100" placeholder="e.g. Bridal" @error('name') aria-invalid="true" @enderror>
            @error('name')<div class="error-text">{{ $message }}</div>@enderror
        </div>
        <div class="field full">
            <label class="label" for="tagline">Short line <span class="optional">(optional)</span></label>
            <input class="input" id="tagline" name="tagline" value="{{ old('tagline', $category->tagline) }}" maxlength="150" placeholder="e.g. Timeless looks for your special day" @error('tagline') aria-invalid="true" @enderror>
            @error('tagline')<div class="error-text">{{ $message }}</div>@else<div class="hint">Shown on the homepage card and at the top of the category page.</div>@enderror
        </div>
        <fieldset class="field full">
            <legend class="label">Icon <span class="optional">(shown on the homepage category strip)</span></legend>
            @php $icon = old('icon', $category->icon); @endphp
            <div class="icon-pick">
                <label class="icon-pick-item" title="No icon (shows the first letter)">
                    <input type="radio" name="icon" value="" @checked(!$icon)>
                    <span class="icon-pick-box"><span class="icon-pick-letter" aria-hidden="true">{{ mb_substr($category->name ?: 'A', 0, 1) }}</span><span class="sr-only">No icon, show first letter</span></span>
                </label>
                @foreach(\App\Models\ServiceCategory::ICONS as $key => [$classes, $label])
                    <label class="icon-pick-item" title="{{ $label }}">
                        <input type="radio" name="icon" value="{{ $key }}" @checked($icon === $key)>
                        <span class="icon-pick-box"><i class="{{ $classes }}" aria-hidden="true"></i><span class="sr-only">{{ $label }}</span></span>
                    </label>
                @endforeach
            </div>
            @error('icon')<div class="error-text">{{ $message }}</div>@enderror
        </fieldset>
        <div class="field full">
            <label class="label" for="description">Description <span class="optional">(optional)</span></label>
            <textarea class="textarea" id="description" name="description" maxlength="500" rows="3">{{ old('description', $category->description) }}</textarea>
            <div class="hint">A few sentences about this category, shown on its page and the services menu.</div>
        </div>
        <div class="field full">
            <span class="label">Hero photo <span class="optional">(wide landscape photo works best, e.g. 1920 × 1000)</span></span>
            <div class="file-drop file-drop-wide">
                <img id="catPreview" src="{{ $category->imageUrl() }}" alt="" @if(!$category->imageUrl()) hidden @endif>
                <div class="stack-sm grow">
                    <label class="sr-only" for="image">Upload hero photo</label>
                    <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp" data-preview="catPreview"
                           data-resize="2400" data-max-file="{{ upload_limit('file', $maxKb) }}" data-upload-error="imageError">
                    <div class="hint">Used for the homepage card and the top of the category page. Large photos are resized automatically.</div>
                    @if($category->image)
                        <label class="check text-sm"><input type="checkbox" name="remove_image" value="1"> Remove current photo</label>
                    @endif
                </div>
            </div>
            <div class="error-text" id="imageError" role="alert" @if(!$errors->has('image')) hidden @endif>{{ $errors->first('image') }}</div>
        </div>
        <div class="field">
            <label class="label" for="sort_order">Display order</label>
            <input class="input" type="number" id="sort_order" name="sort_order" min="0" max="9999" value="{{ old('sort_order', $category->sort_order ?? 0) }}">
            <div class="hint">Lower numbers appear first.</div>
        </div>
        <div class="field" style="justify-content:center;">
            <input type="hidden" name="is_active" value="0">
            <label class="switch">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active ?? true))>
                <span class="track" aria-hidden="true"></span>
                Show on website
            </label>
            <div class="hint">Appears on the homepage once it has at least one bookable service.</div>
        </div>
    </div>
    <div class="form-actions">
        <a href="{{ route('admin.categories.index') }}" class="btn">Cancel</a>
        <button type="submit" class="btn btn-primary">{{ $editing ? 'Save changes' : 'Create category' }}</button>
    </div>
</form>
@endsection
