{{-- Where a platform sign-in ends: the person's account is closed on this browser, and the way back to the console. --}}
@php
    $l = 'identity::impersonation.ended.';
    $rtl = str_starts_with(app()->getLocale(), 'ar');
    $brand = $branding?->tokens['primary'] ?? '#4F46E5';
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __($l.$why.'.title') }}</title>
    <style>
        @foreach ([400, 600, 700] as $w)
        @font-face { font-family: 'IBM Plex Sans Arabic'; font-weight: {{ $w }}; font-display: swap; src: url('{{ asset("fonts/stages/ibm-plex-sans-arabic/ibm-plex-sans-arabic-arabic-{$w}-normal.woff2") }}') format('woff2'); unicode-range: U+0600-06FF, U+0750-077F, U+FB50-FDFF, U+FE70-FEFC; }
        @font-face { font-family: 'IBM Plex Sans Arabic'; font-weight: {{ $w }}; font-display: swap; src: url('{{ asset("fonts/stages/ibm-plex-sans-arabic/ibm-plex-sans-arabic-latin-{$w}-normal.woff2") }}') format('woff2'); unicode-range: U+0000-00FF, U+2000-206F; }
        @endforeach
        :root { --brand: {{ $brand }}; --ink: #1c1917; --muted: #78716c; --line: #e7e5e4; --bg: #f5f5f4; --card: #fff; color-scheme: light; }
        @media (prefers-color-scheme: dark) { :root { --ink: #f5f5f4; --muted: #a8a29e; --line: #292524; --bg: #0c0a09; --card: #1c1917; color-scheme: dark; } }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100dvh; display: grid; place-items: center; background: var(--bg); color: var(--ink); font: 16px/1.6 'IBM Plex Sans Arabic', system-ui, -apple-system, 'Segoe UI', sans-serif; padding: 1rem; }
        .card { width: 100%; max-width: 26rem; background: var(--card); border: 1px solid var(--line); border-radius: 1.1rem; padding: 1.6rem 1.3rem; text-align: center; display: flex; flex-direction: column; gap: .6rem; align-items: center; }
        .icon { width: 3rem; height: 3rem; border-radius: 1rem; display: grid; place-items: center; background: color-mix(in oklab, var(--brand) 12%, transparent); color: var(--brand); }
        .icon svg { width: 1.5rem; height: 1.5rem; }
        h1 { margin: 0; font-size: 1.2rem; }
        p { margin: 0; color: var(--muted); font-size: .92rem; }
        a { margin-top: .6rem; display: inline-block; padding: .7rem 1.1rem; border-radius: .8rem; background: var(--brand); color: #fff; font-weight: 700; text-decoration: none; }
        @media (prefers-reduced-motion: no-preference) { .card { animation: rise .45s cubic-bezier(.2, .7, .2, 1) backwards; } }
        @keyframes rise { from { opacity: 0; transform: translateY(12px); } }
    </style>
</head>
<body>
<main class="card">
    <span class="icon" aria-hidden="true">{{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::OutlinedLifebuoy) }}</span>
    <h1>{{ __($l.$why.'.title') }}</h1>
    <p>{{ __($l.$why.'.body') }}</p>
    <a href="{{ $console }}">{{ __($l.'back') }}</a>
</main>
</body>
</html>
