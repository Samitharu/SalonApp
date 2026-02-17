<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>{{ setting('salon_name') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://fonts.googleapis.com/css2?family:Playfair+Display:wght@500;700&family:Poppins:wght@300;400;500&display=swap" rel="stylesheet">

    <style>
        /* Background */
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(120deg, #fff7f8, #fcefee);
            color: #222;
        }

        /* Premium navbar */
        .navbar {
            background: linear-gradient(135deg, #1a1a1a, #2a2a2a);
            box-shadow: 0 5px 20px rgba(0, 0, 0, .25);
        }

        .navbar-brand {
            font-family: 'Playfair Display', serif;
            font-size: 22px;
            color: #f8c8d8 !important;
        }

        .nav-link {
            color: #eee !important;
            margin-left: 15px;
            transition: .2s;
        }

        .nav-link:hover {
            color: #ff9db5 !important;
        }

        /* Hero */
        .hero {
            background: linear-gradient(135deg, #ffb6c1, #ffd6de);
            padding: 100px 20px;
            text-align: center;
        }

        .hero h1 {
            font-family: 'Playfair Display', serif;
            font-size: 48px;
        }

        /* Cards */
        .card {
            background: white;
            border: none;
            border-radius: 22px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, .08);
            transition: .3s;
        }

        .card:hover {
            transform: translateY(-8px);
        }

        /* Buttons */
        .btn-primary {
            background: linear-gradient(135deg, #ff5f8a, #ff8fb1);
            border: none;
            border-radius: 30px;
            padding: 12px 26px;
            font-weight: 500;
        }

        .btn-primary:hover {
            opacity: .9;
        }

        /* Inputs */
        input,
        select {
            border-radius: 14px !important;
            border: 1px solid #eee !important;
            padding: 12px !important;
        }

        /* Footer */
        footer {
            background: #111;
            color: #eee;
            padding: 25px;
            text-align: center;
            margin-top: 60px;
        }

        #app-content {
            transition: opacity .25s;
        }
    </style>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>

<body>

    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand spa-link" href="/">
                {{ setting('salon_name') }}
            </a>

            <div class="navbar-nav ms-auto">

                <a class="nav-link spa-link" href="/">Home</a>
                <a class="nav-link spa-link" href="/services">Services</a>
                <a class="nav-link spa-link" href="/booking">Book Now</a>

                @auth('admin')
                <div class="nav-item dropdown ms-3">
                    <a class="nav-link dropdown-toggle"
                        data-bs-toggle="dropdown"
                        href="#">
                        {{ auth('admin')->user()->name }}
                    </a>

                    <div class="dropdown-menu dropdown-menu-end">
                        <a class="dropdown-item spa-link" href="/admin">
                            Dashboard
                        </a>
                        <a class="dropdown-item" href="/admin/logout">
                            Logout
                        </a>
                    </div>
                </div>
                @else
                <a class="nav-link ms-3" href="/admin/login">
                    Admin Login
                </a>
                @endauth

            </div>
        </div>
    </nav>

    <main id="app-content">
        @yield('content')
    </main>

    <footer>
        © {{ date('Y') }} {{ setting('salon_name') }}
    </footer>

    <script src="/js/spa.js"></script>
    <script src="/js/booking.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>