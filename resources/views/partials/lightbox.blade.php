{{-- Full-screen photo viewer for links marked data-lightbox (href = full image). Without JS the links simply open the photo. --}}
<dialog class="lightbox" id="lightbox" aria-label="Photo viewer">
    <button type="button" class="lightbox-btn lightbox-close" data-lb-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    <button type="button" class="lightbox-btn lightbox-prev" data-lb-prev aria-label="Previous photo"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
    <figure class="lightbox-figure">
        <img id="lbImg" alt="">
        <figcaption><span id="lbCaption"></span> <span class="lightbox-count" id="lbCount"></span></figcaption>
    </figure>
    <button type="button" class="lightbox-btn lightbox-next" data-lb-next aria-label="Next photo"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>
</dialog>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var box = document.getElementById('lightbox');
    if (!box || !box.showModal) return;
    var links = Array.prototype.slice.call(document.querySelectorAll('[data-lightbox]'));
    var img = document.getElementById('lbImg'), cap = document.getElementById('lbCaption'), count = document.getElementById('lbCount');
    var i = 0, startX = null;

    function show(n) {
        i = (n + links.length) % links.length;
        var a = links[i], thumb = a.querySelector('img');
        img.src = a.href;
        img.alt = thumb ? thumb.alt : '';
        cap.textContent = a.dataset.caption || '';
        count.textContent = links.length > 1 ? (i + 1) + ' / ' + links.length : '';
        // Warm the next photo so arrowing through feels instant.
        if (links.length > 1) { var pre = new Image(); pre.src = links[(i + 1) % links.length].href; }
    }

    links.forEach(function (a, k) {
        a.addEventListener('click', function (e) {
            if (e.metaKey || e.ctrlKey || e.shiftKey) return;
            e.preventDefault();
            show(k);
            box.showModal();
        });
    });
    box.classList.toggle('is-single', links.length < 2);
    box.querySelector('[data-lb-close]').addEventListener('click', function () { box.close(); });
    box.querySelector('[data-lb-prev]').addEventListener('click', function () { show(i - 1); });
    box.querySelector('[data-lb-next]').addEventListener('click', function () { show(i + 1); });
    box.addEventListener('click', function (e) { if (e.target === box) box.close(); });
    box.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowLeft') show(i - 1);
        if (e.key === 'ArrowRight') show(i + 1);
    });
    box.addEventListener('close', function () { img.removeAttribute('src'); });
    // Swipe left/right on phones.
    box.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; }, { passive: true });
    box.addEventListener('touchend', function (e) {
        if (startX === null) return;
        var dx = e.changedTouches[0].clientX - startX;
        if (Math.abs(dx) > 45) show(i + (dx < 0 ? 1 : -1));
        startX = null;
    });
});
</script>
@endpush
