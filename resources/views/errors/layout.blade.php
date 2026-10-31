{{--
    Minimal, self-contained error page layout.

    It deliberately does NOT extend layouts/app.blade.php: that layout reads the
    settings table for branding, which is exactly the thing that can be
    unavailable when a 500 happens. Everything here is static (the accent colour
    is the config default rather than the stored brand colour) so an error page
    can always render.
--}}
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Something went wrong')</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            background: #edf4f3; color: #182230; padding: 24px;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }
        .card {
            width: 100%; max-width: 30rem; background: #fff; border: 1px solid #dfe7ed;
            border-radius: 16px; padding: 32px; text-align: center;
            box-shadow: 0 24px 70px rgba(26, 65, 70, .10);
        }
        .code { margin: 0; font-size: 3rem; font-weight: 800; letter-spacing: -.02em; color: #12A594; }
        h1 { font-size: 1.35rem; margin: 12px 0 8px; }
        p { margin: 0 0 24px; color: #64748b; line-height: 1.6; font-size: .95rem; }
        .button {
            display: inline-block; background: #10253b; color: #fff; text-decoration: none;
            font-weight: 600; padding: 12px 20px; border-radius: 10px; font-size: .9rem;
        }
        .button:hover { background: #183753; }
    </style>
</head>
<body>
    <main class="card">
        <p class="code">@yield('code')</p>
        <h1>@yield('heading')</h1>
        <p>@yield('message')</p>
        <a class="button" href="/">Go to sign in</a>
    </main>
</body>
</html>
