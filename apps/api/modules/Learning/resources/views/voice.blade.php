{{-- The recording of the week, from a guardian's weekly note (WeeklyVoiceController): played here, never offered as a download. --}}
@php
    $l = 'learning::digest.voice.';
    $rtl = str_starts_with(app()->getLocale(), 'ar');
    $brand = $branding?->tokens['primary'] ?? '#0D735E';
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="referrer" content="no-referrer">
    <title>{{ __($l.'title', ['student' => $student]) }}</title>
    @if ($branding?->faviconUrl) <link rel="icon" href="{{ $branding->faviconUrl }}"> @endif
    <style>
        @foreach ([400, 700] as $w)
        @font-face { font-family: 'IBM Plex Sans Arabic'; font-weight: {{ $w }}; font-display: swap; src: url('{{ asset("fonts/stages/ibm-plex-sans-arabic/ibm-plex-sans-arabic-arabic-{$w}-normal.woff2") }}') format('woff2'); unicode-range: U+0600-06FF, U+0750-077F, U+FB50-FDFF, U+FE70-FEFC; }
        @font-face { font-family: 'IBM Plex Sans Arabic'; font-weight: {{ $w }}; font-display: swap; src: url('{{ asset("fonts/stages/ibm-plex-sans-arabic/ibm-plex-sans-arabic-latin-{$w}-normal.woff2") }}') format('woff2'); unicode-range: U+0000-00FF, U+2000-206F; }
        @endforeach
        :root { --brand: {{ $brand }}; --brand-soft: color-mix(in oklab, var(--brand) 10%, white); --ink: #1c1917; --muted: #78716c; --line: #e7e5e4; --bg: #f5f5f4; --card: #fff; color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100dvh; background: var(--bg); color: var(--ink); font: 16px/1.6 'IBM Plex Sans Arabic', system-ui, -apple-system, 'Segoe UI', sans-serif; -webkit-font-smoothing: antialiased; }
        main { max-width: 30rem; margin: 0 auto; padding: 1.25rem 1rem 2.5rem; display: flex; flex-direction: column; gap: .9rem; }
        .brand { display: flex; align-items: center; gap: .7rem; }
        .brand img { height: 2.4rem; width: auto; }
        .brand .mark { display: grid; place-items: center; width: 2.4rem; height: 2.4rem; border-radius: .7rem; background: var(--brand); color: #fff; font-weight: 700; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 1rem; padding: 1.2rem 1.15rem; display: flex; flex-direction: column; gap: .5rem; }
        .wave { display: grid; place-items: center; width: 3.5rem; height: 3.5rem; border-radius: 1rem; background: var(--brand-soft); color: var(--brand); }
        h1 { margin: 0; font-size: 1.3rem; line-height: 1.4; }
        .muted { color: var(--muted); font-size: .92rem; margin: 0; }
        audio { width: 100%; margin-top: .4rem; }
        footer { text-align: center; color: var(--muted); font-size: .78rem; }
        @media (prefers-reduced-motion: no-preference) {
            main > * { animation: rise .45s cubic-bezier(.2, .7, .2, 1) backwards; }
            main > :nth-child(2) { animation-delay: 70ms; } main > :nth-child(3) { animation-delay: 140ms; }
        }
        @keyframes rise { from { opacity: 0; transform: translateY(12px); } }
    </style>
</head>
<body>
<main>
    <div class="brand">
        @if ($branding?->logoUrl)<img src="{{ $branding->logoUrl }}" alt="">@else<span class="mark">{{ mb_substr($branding?->name() ?? config('app.name'), 0, 1) }}</span>@endif
        <strong>{{ $branding?->name() ?? config('app.name') }}</strong>
    </div>
    <section class="card">
        <span class="wave" aria-hidden="true">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 10v4M8 7v10M12 4v16M16 8v8M20 11v2"/></svg>
        </span>
        <h1>{{ __($l.'title', ['student' => $student]) }}</h1>
        <p class="muted">{{ __($l.'lead', ['homework' => $homework]) }}</p>
        <p class="muted">{{ __($l.'when', ['when' => $when]) }}</p>
        <audio controls preload="metadata" controlslist="nodownload noplaybackrate" src="{{ $src }}">{{ __($l.'no_player') }}</audio>
    </section>
    <footer>{{ __($l.'private') }}</footer>
</main>
</body>
</html>
