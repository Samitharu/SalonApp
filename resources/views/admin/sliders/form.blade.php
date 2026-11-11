@extends('layouts.admin')
@php $editing = $slider->exists; @endphp
@section('title', $editing ? 'Edit slide' : 'Add slide')
@section('crumb', 'Homepage hero')

@section('content')
<div class="page-head">
    <div><h1>{{ $editing ? 'Edit slide' : 'Add slide' }}</h1></div>
</div>

<form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('admin.sliders.update', $slider) : route('admin.sliders.store') }}" class="card card-pad" style="max-width:760px;" novalidate>
    @csrf
    @if($editing) @method('PUT') @endif
    <div class="form-grid">
        <div class="field full">
            <label class="label" for="title">Headline <span class="req">*</span></label>
            <input class="input" id="title" name="title" value="{{ old('title', $slider->title) }}" required maxlength="150" placeholder="e.g. Look good, feel better" @error('title') aria-invalid="true" @enderror>
            @error('title')<div class="error-text">{{ $message }}</div>@enderror
        </div>
        <div class="field full">
            <label class="label" for="subtitle">Supporting text</label>
            <textarea class="textarea" id="subtitle" name="subtitle" maxlength="200" rows="2">{{ old('subtitle', $slider->subtitle) }}</textarea>
            @error('subtitle')<div class="error-text">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label class="label" for="button_text">Button label</label>
            <input class="input" id="button_text" name="button_text" value="{{ old('button_text', $slider->button_text) }}" maxlength="30">
        </div>
        <div class="field">
            <label class="label" for="button_link">Button link</label>
            <input class="input" id="button_link" name="button_link" value="{{ old('button_link', $slider->button_link) }}" maxlength="255" placeholder="/booking" @error('button_link') aria-invalid="true" @enderror>
            @error('button_link')<div class="error-text">{{ $message }}</div>@else<div class="hint">A page like /booking or a full https:// link.</div>@enderror
        </div>
        <div class="field full">
            <span class="label">Photo <span class="optional">(optional, portrait works best, up to 3 MB)</span></span>
            <div class="file-drop">
                <img id="slidePreview" src="{{ $slider->imageUrl() }}" alt="" @if(!$slider->imageUrl()) hidden @endif>
                <div class="stack-sm grow">
                    <label class="sr-only" for="image">Upload photo</label>
                    <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp" data-preview="slidePreview">
                    @if($slider->image)
                        <label class="check text-sm"><input type="checkbox" name="remove_image" value="1"> Remove current photo</label>
                    @endif
                </div>
            </div>
            @error('image')<div class="error-text">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label class="label" for="sort_order">Order</label>
            <input class="input" type="number" id="sort_order" name="sort_order" min="0" max="9999" value="{{ old('sort_order', $slider->sort_order ?? 0) }}">
        </div>
        <div class="field" style="justify-content:flex-end;">
            <label class="switch">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $slider->is_active ?? true))>
                <span class="track" aria-hidden="true"></span>
                Active
            </label>
        </div>
    </div>
    <div class="form-actions">
        <a href="{{ route('admin.sliders.index') }}" class="btn">Cancel</a>
        <button type="submit" class="btn btn-primary">{{ $editing ? 'Save slide' : 'Create slide' }}</button>
    </div>
</form>
@endsection
