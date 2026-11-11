{{-- Shared <head> contents. Pass $title (string). --}}
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="color-scheme" content="light dark">
<meta name="theme-color" content="#F5F0E7" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#1B1E19" media="(prefers-color-scheme: dark)">
<title>{{ $title }}</title>
<script>
    // Apply the saved or system theme before first paint to avoid a flash.
    (function () {
        var t = null;
        try { t = localStorage.getItem('theme'); } catch (e) {}
        if (t !== 'light' && t !== 'dark') {
            t = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        document.documentElement.setAttribute('data-theme', t);
    })();
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;1,400&family=Montserrat:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer">
<link rel="stylesheet" href="{{ asset_v('css/app.css') }}">
<link rel="icon" href="{{ asset('favicon.ico') }}">
