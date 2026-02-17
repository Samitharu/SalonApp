document.addEventListener('click', e => {
    const link = e.target.closest('.spa-link');
    if (!link) return;

    e.preventDefault();
    loadPage(link.href);
});

async function loadPage(url) {
    const res = await fetch(url);
    const html = await res.text();

    const doc = new DOMParser().parseFromString(html, 'text/html');
    const content = doc.querySelector('#app-content');

    const app = document.querySelector('#app-content');
    app.classList.remove('show');

    setTimeout(() => {
        app.innerHTML = content.innerHTML;

        // ✅ reinitialize booking page
        if (window.initBookingPage) {
            window.initBookingPage();
        }

        // ✅ reinitialize booking submit
        if (window.initBookingSubmit) {
            window.initBookingSubmit();
        }

        app.classList.add('show');
    }, 150);

    history.pushState({}, '', url);
}

window.onpopstate = () => loadPage(location.href);