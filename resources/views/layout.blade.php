{{--
    The frame of the pages this addon serves outside the Control Panel (the
    confirmation page). Deliberately plain. A site gives it its own look by
    putting `resources/views/vendor/accounts/layout.blade.php` in place; the
    pages only fill `title` and `content` and use the classes `.btn`,
    `.field`, `.error`, `.status` and `.muted`.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title')</title>
    <style>
        body { margin: 0; padding: 0; background: #f4f4f5; font-family: -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #18181b; }
        main { max-width: 440px; margin: 10vh auto; background: #fff; border-radius: 12px; padding: 40px 32px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        h1 { font-size: 22px; margin: 0 0 12px; }
        p { color: #52525b; line-height: 1.6; margin: 0 0 12px; }
        .field { display: block; margin: 20px 0 0; }
        .field span { display: block; font-size: 14px; margin-bottom: 6px; }
        .field input { width: 100%; box-sizing: border-box; padding: 10px 12px; border: 1px solid #d4d4d8; border-radius: 8px; font: inherit; }
        .error { color: #b91c1c; font-size: 14px; margin-top: 8px; }
        .status { background: #f4f4f5; border-radius: 8px; padding: 10px 12px; font-size: 14px; }
        .btn { margin-top: 20px; padding: 11px 20px; border: 0; border-radius: 8px; background: #18181b; color: #fff; font: inherit; cursor: pointer; }
        .muted { font-size: 14px; color: #71717a; margin-top: 20px; }
    </style>
</head>
<body>
    <main>
        @yield('content')
    </main>
</body>
</html>
