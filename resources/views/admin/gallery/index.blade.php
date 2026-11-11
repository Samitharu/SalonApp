@extends('layouts.admin')
@section('title', 'Gallery')
@php
    use App\Http\Controllers\Admin\GalleryController as G;
    $total = $counts->sum();
@endphp

@section('content')
<div class="page-head">
    <div>
        <h1>Gallery</h1>
        <p>Photos of your work, shown on the Gallery page, the homepage and each category page.</p>
    </div>
    <a href="{{ route('gallery') }}" target="_blank" rel="noopener" class="btn"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i> View gallery</a>
</div>

<form method="POST" action="{{ route('admin.gallery.store') }}" enctype="multipart/form-data" class="card card-pad mb-6" novalidate>
    @csrf
    <h2 class="card-title mb-4">Upload photos</h2>
    <div class="form-grid">
        <div class="field full">
            <label class="upload-zone" for="images">
                <i class="fa-regular fa-images" aria-hidden="true"></i>
                <span class="fw-600">Choose photos</span>
                <span class="text-sm text-muted">Up to {{ G::MAX_FILES }} at a time · JPG, PNG or WebP · large photos are resized automatically</span>
                <input type="file" id="images" name="images[]" multiple accept="image/jpeg,image/png,image/webp" required
                       data-resize="2400" data-max-count="{{ G::MAX_FILES }}" data-max-file="{{ upload_limit('file', G::MAX_KB) }}"
                       data-max-total="{{ upload_limit('total') }}" data-upload-error="uploadError">
            </label>
            <div class="upload-picked" id="uploadPicked" hidden></div>
            <div class="error-text" id="uploadError" role="alert" @if(!$errors->has('images') && !$errors->has('images.*')) hidden @endif>{{ $errors->first('images') ?: collect($errors->get('images.*'))->flatten()->first() }}</div>
        </div>
        <div class="field">
            <label class="label" for="service_category_id">Category <span class="optional">(optional)</span></label>
            <select class="select" id="service_category_id" name="service_category_id">
                <option value="">No category</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" @selected(old('service_category_id', ctype_digit((string) $filter) ? (int) $filter : null) == $cat->id)>{{ $cat->name }}</option>
                @endforeach
            </select>
            <div class="hint">Tagged photos also show on that category’s page.</div>
        </div>
        <div class="field">
            <label class="label" for="caption">Caption <span class="optional">(optional)</span></label>
            <input class="input" id="caption" name="caption" value="{{ old('caption') }}" maxlength="150" placeholder="e.g. Kandyan bridal look">
            <div class="hint">Applied to every photo in this upload. Edit each one later.</div>
        </div>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i> Upload photos</button>
    </div>
</form>

<div class="card">
    <div class="card-header">
        <div class="chips" role="navigation" aria-label="Filter by category">
            <a class="chip {{ $filter === null || $filter === '' ? 'is-active' : '' }}" href="{{ route('admin.gallery.index') }}">All <span class="count">{{ $total }}</span></a>
            @foreach($categories as $cat)
                @if($counts->get($cat->id))
                    <a class="chip {{ (string) $filter === (string) $cat->id ? 'is-active' : '' }}" href="{{ route('admin.gallery.index', ['category' => $cat->id]) }}">{{ $cat->name }} <span class="count">{{ $counts->get($cat->id) }}</span></a>
                @endif
            @endforeach
            @if($counts->get(''))
                <a class="chip {{ $filter === 'none' ? 'is-active' : '' }}" href="{{ route('admin.gallery.index', ['category' => 'none']) }}">No category <span class="count">{{ $counts->get('') }}</span></a>
            @endif
        </div>
    </div>

    @if($images->isEmpty())
        <div class="empty">
            <div class="empty-icon"><i class="fa-regular fa-images" aria-hidden="true"></i></div>
            <h3>No photos yet</h3>
            <p>Upload photos of your best work: bridal looks, hair, nails and more.</p>
        </div>
    @else
        <ul class="admin-gallery" role="list">
            @foreach($images as $img)
                @php [$tw, $th] = $img->thumbSize(); @endphp
                <li class="admin-gallery-item {{ $img->is_active ? '' : 'is-hidden' }}">
                    <a href="{{ route('admin.gallery.edit', $img) }}" class="admin-gallery-media" aria-label="Edit photo {{ $img->caption ?: '#' . $img->id }}">
                        <img src="{{ $img->thumbUrl() }}" alt="" loading="lazy" @if($tw) width="{{ $tw }}" height="{{ $th }}" @endif>
                        @unless($img->is_active)<span class="badge badge-neutral">Hidden</span>@endunless
                    </a>
                    <div class="admin-gallery-body">
                        <div class="grow">
                            <div class="text-sm fw-600 truncate">{{ $img->caption ?: 'No caption' }}</div>
                            <div class="text-xs text-muted truncate">{{ $img->category->name ?? 'No category' }}</div>
                        </div>
                        <form method="POST" action="{{ route('admin.gallery.destroy', $img) }}" data-confirm="Delete this photo?" data-confirm-message="It will be removed from the website straight away." data-confirm-button="Delete">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-ghost btn-icon btn-sm" aria-label="Delete photo {{ $img->caption ?: '#' . $img->id }}"><i class="fa-regular fa-trash-can" aria-hidden="true"></i></button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
        @if($images->hasPages())<div class="card-footer">{{ $images->links() }}</div>@endif
    @endif
</div>
@endsection

@push('scripts')
<script>
// List the chosen photos as small previews once they've been prepared.
document.addEventListener('files-ready', function (e) {
    if (e.target.id !== 'images') return;
    var box = document.getElementById('uploadPicked');
    box.innerHTML = '';
    e.detail.files.forEach(function (f) {
        var img = document.createElement('img');
        img.src = URL.createObjectURL(f);
        img.alt = '';
        box.appendChild(img);
    });
    var note = document.createElement('span');
    note.className = 'text-sm text-muted';
    note.textContent = e.detail.files.length + (e.detail.files.length === 1 ? ' photo' : ' photos') + ' ready to upload';
    box.appendChild(note);
    box.hidden = false;
});
</script>
@endpush
