{{-- A suspended academy's web pages: one calm page, in the reader's language, with no academy data. --}}
@php $rtl = app()->getLocale() === 'ar'; @endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('shared::problems.suspended_page.title') }}</title>
    <style>
        :root { --ink: #1c1917; --muted: #78716c; --line: #e7e5e4; --bg: #f5f5f4; --card: #fff; color-scheme: light; }
        @media (prefers-color-scheme: dark) { :root { --ink: #f5f5f4; --muted: #a8a29e; --line: #292524; --bg: #0c0a09; --card: #1c1917; color-scheme: dark; } }
        body { margin: 0; min-height: 100dvh; display: grid; place-items: center; padding: 1rem; background: var(--bg); color: var(--ink); font: 16px/1.6 system-ui, -apple-system, 'Segoe UI', Tahoma, sans-serif; }
        main { max-width: 26rem; background: var(--card); border: 1px solid var(--line); border-radius: 1.1rem; padding: 1.6rem 1.3rem; text-align: center; }
        h1 { margin: 0 0 .4rem; font-size: 1.2rem; }
        p { margin: 0; color: var(--muted); font-size: .95rem; }
    </style>
</head>
<body>
<main>
    <h1>{{ __('shared::problems.suspended_page.title') }}</h1>
    <p>{{ __('shared::problems.suspended_page.body') }}</p>
</main>
</body>
</html>
