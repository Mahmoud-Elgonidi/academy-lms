@props(['branding' => null, 'title' => '', 'wide' => false])
{{-- A page of its own for paying, outside the panels: the academy's name, logo and colour, Arabic first, phone first. --}}
@php
    $rtl = str_starts_with(app()->getLocale(), 'ar');
    $brand = $branding?->tokens['primary'] ?? '#0D735E';
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }}</title>
    @if ($branding?->faviconUrl) <link rel="icon" href="{{ $branding->faviconUrl }}"> @endif
    <style>
        @foreach ([400, 600, 700] as $w)
        @font-face { font-family: 'IBM Plex Sans Arabic'; font-weight: {{ $w }}; font-display: swap; src: url('{{ asset("fonts/stages/ibm-plex-sans-arabic/ibm-plex-sans-arabic-arabic-{$w}-normal.woff2") }}') format('woff2'); unicode-range: U+0600-06FF, U+0750-077F, U+FB50-FDFF, U+FE70-FEFC; }
        @font-face { font-family: 'IBM Plex Sans Arabic'; font-weight: {{ $w }}; font-display: swap; src: url('{{ asset("fonts/stages/ibm-plex-sans-arabic/ibm-plex-sans-arabic-latin-{$w}-normal.woff2") }}') format('woff2'); unicode-range: U+0000-00FF, U+2000-206F; }
        @endforeach
        :root {
            --brand: {{ $brand }};
            --brand-soft: color-mix(in oklab, var(--brand) 10%, white);
            --ink: #1c1917; --muted: #78716c; --line: #e7e5e4; --bg: #f5f5f4; --card: #fff;
            --good: #15803d; --good-soft: #f0fdf4; --bad: #b42318; --bad-soft: #fef3f2; --wait: #b45309; --wait-soft: #fffbeb;
            color-scheme: light;
        }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100dvh; background: var(--bg); color: var(--ink); font: 16px/1.6 'IBM Plex Sans Arabic', system-ui, -apple-system, 'Segoe UI', sans-serif; -webkit-font-smoothing: antialiased; }
        main { max-width: 30rem; margin: 0 auto; padding: 1.25rem 1rem 2.5rem; display: flex; flex-direction: column; gap: .9rem; }
        main.wide { max-width: 50rem; }
        @media print { body { background: #fff; } main { padding: 0; } .no-print { display: none !important; } .card { border: 0; } main > * { animation: none !important; } }
        .brand { display: flex; align-items: center; gap: .7rem; padding: .25rem 0 .5rem; }
        .brand img { height: 2.4rem; width: auto; }
        .brand span.mark { display: grid; place-items: center; width: 2.4rem; height: 2.4rem; border-radius: .7rem; background: var(--brand); color: #fff; font-weight: 700; }
        .brand strong { font-size: 1.05rem; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 1rem; padding: 1.1rem 1.15rem; }
        h1, h2 { margin: 0; line-height: 1.35; }
        h2 { font-size: 1rem; }
        .muted { color: var(--muted); font-size: .88rem; }
        .row { display: flex; justify-content: space-between; align-items: center; gap: .75rem; flex-wrap: wrap; }
        .pill { display: inline-block; font-size: .78rem; font-weight: 600; padding: .1rem .6rem; border-radius: 999px; background: var(--wait-soft); color: var(--wait); }
        .pill[data-tone="good"] { background: var(--good-soft); color: var(--good); }
        .pill[data-tone="bad"] { background: var(--bad-soft); color: var(--bad); }
        .amount { margin: .6rem 0 .2rem; font-size: 2.1rem; font-weight: 700; letter-spacing: -.01em; font-variant-numeric: tabular-nums; }
        dl { margin: .5rem 0 0; display: grid; grid-template-columns: auto 1fr; gap: .3rem .9rem; font-size: .92rem; }
        dt { color: var(--muted); } dd { margin: 0; text-align: end; font-variant-numeric: tabular-nums; }
        form { margin: 0; }
        .pay-btn { width: 100%; display: flex; align-items: center; justify-content: space-between; gap: .75rem; margin-top: .6rem; padding: .85rem 1rem; border: 0; border-radius: .85rem; background: var(--brand); color: #fff; font: inherit; font-weight: 700; cursor: pointer; text-align: start; transition: transform .15s, box-shadow .2s, filter .2s; }
        .pay-btn small { display: block; font-weight: 400; opacity: .9; font-size: .8rem; }
        .pay-btn:hover { filter: brightness(1.06); box-shadow: 0 10px 20px -12px var(--brand); }
        .pay-btn:active { transform: scale(.98); }
        .pay-btn.secondary { background: var(--brand-soft); color: var(--ink); border: 1px solid color-mix(in oklab, var(--brand) 25%, white); }
        .methods { display: flex; flex-wrap: wrap; gap: .35rem; margin-top: .25rem; }
        .methods span { font-size: .72rem; font-weight: 600; padding: .05rem .45rem; border-radius: .35rem; background: rgb(255 255 255 / .18); }
        .pay-btn.secondary .methods span { background: #fff; border: 1px solid var(--line); }
        .secure { display: flex; gap: .4rem; align-items: flex-start; margin: .8rem 0 0; color: var(--muted); font-size: .8rem; }
        .note { white-space: pre-line; margin: .5rem 0 0; font-size: .92rem; }
        .banner { display: flex; gap: .7rem; align-items: flex-start; border-radius: 1rem; padding: .9rem 1rem; font-weight: 600; }
        .banner b { font-size: 1.4rem; line-height: 1; }
        .banner small { display: block; font-weight: 400; font-size: .85rem; }
        .banner[data-tone="good"] { background: var(--good-soft); color: var(--good); }
        .banner[data-tone="bad"] { background: var(--bad-soft); color: var(--bad); }
        .banner[data-tone="wait"] { background: var(--wait-soft); color: var(--wait); }
        .errors { color: var(--bad); font-size: .9rem; margin: 0; }
        .proof { margin: .7rem 0 0; padding: .6rem .8rem; border-radius: .75rem; font-size: .9rem; background: var(--wait-soft); color: var(--wait); overflow-wrap: anywhere; }
        .proof[data-s="approved"] { background: var(--good-soft); color: var(--good); }
        .proof[data-s="rejected"] { background: var(--bad-soft); color: var(--bad); }
        details.proof-form > summary { list-style: none; }
        details.proof-form > summary::-webkit-details-marker { display: none; }
        details.proof-form[open] > summary { display: none; }
        .proof-form form { display: flex; flex-direction: column; gap: .75rem; margin-top: .9rem; }
        .proof-form h3 { margin: 0; font-size: .98rem; }
        .field { display: flex; flex-direction: column; gap: .3rem; min-width: 0; }
        .field > span { font-size: .85rem; font-weight: 600; }
        .field small { color: var(--muted); font-size: .78rem; }
        .field small.bad { color: var(--bad); }
        .field input, .field textarea { width: 100%; font: inherit; font-size: 1rem; padding: .6rem .75rem; border: 1px solid var(--line); border-radius: .7rem; background: #fff; color: var(--ink); }
        .field input:focus-visible, .field textarea:focus-visible, .chips input:focus-visible + span { outline: 2px solid var(--brand); outline-offset: 1px; }
        .field input[type="file"] { padding: .5rem; background: var(--bg); }
        .pair { display: grid; grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr)); gap: .75rem; }
        .chips { display: flex; flex-wrap: wrap; gap: .4rem; border: 0; margin: 0; padding: 0; }
        .chips legend { font-size: .85rem; font-weight: 600; margin-bottom: .3rem; padding: 0; }
        .chips label { position: relative; }
        .chips input { position: absolute; opacity: 0; inset: 0; }
        .chips span { display: inline-block; padding: .4rem .8rem; border-radius: 999px; border: 1px solid var(--line); background: #fff; font-size: .88rem; cursor: pointer; }
        .chips input:checked + span { background: var(--brand-soft); border-color: var(--brand); color: var(--ink); font-weight: 700; }
        footer { text-align: center; color: var(--muted); font-size: .78rem; }
        .doc-link { display: inline-block; margin-top: .7rem; font-size: .88rem; font-weight: 600; color: var(--brand); }
        @media (prefers-reduced-motion: no-preference) {
            main > * { animation: rise .45s cubic-bezier(.2, .7, .2, 1) backwards; }
            main > :nth-child(2) { animation-delay: 60ms; } main > :nth-child(3) { animation-delay: 120ms; } main > :nth-child(4) { animation-delay: 180ms; } main > :nth-child(5) { animation-delay: 240ms; }
            .banner[data-tone="good"] b { animation: pop .6s cubic-bezier(.34, 1.56, .64, 1) .25s backwards; }
        }
        @keyframes rise { from { opacity: 0; transform: translateY(12px); } }
        @keyframes pop { from { opacity: 0; transform: scale(.3) rotate(-30deg); } }
    </style>
</head>
<body>
    <main @if ($wide) class="wide" @endif>
        <div class="brand">
            @if ($branding?->logoUrl)
                <img src="{{ $branding->logoUrl }}" alt="">
            @else
                <span class="mark">{{ mb_substr($branding?->name() ?? config('app.name'), 0, 1) }}</span>
            @endif
            <strong>{{ $branding?->name() ?? config('app.name') }}</strong>
        </div>
        {{ $slot }}
    </main>
</body>
</html>
