@php
    $l = 'people::consent.';
    $rtl = str_starts_with(app()->getLocale(), 'ar');
    $brand = $branding?->tokens['primary'] ?? '#0D735E';
    $p = ['student' => $student, 'academy' => $academy];
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __($l.'link.title') }}</title>
    @if ($branding?->faviconUrl) <link rel="icon" href="{{ $branding->faviconUrl }}"> @endif
    <style>
        @foreach ([400, 600, 700] as $w)
        @font-face { font-family: 'IBM Plex Sans Arabic'; font-weight: {{ $w }}; font-display: swap; src: url('{{ asset("fonts/stages/ibm-plex-sans-arabic/ibm-plex-sans-arabic-arabic-{$w}-normal.woff2") }}') format('woff2'); unicode-range: U+0600-06FF, U+0750-077F, U+FB50-FDFF, U+FE70-FEFC; }
        @font-face { font-family: 'IBM Plex Sans Arabic'; font-weight: {{ $w }}; font-display: swap; src: url('{{ asset("fonts/stages/ibm-plex-sans-arabic/ibm-plex-sans-arabic-latin-{$w}-normal.woff2") }}') format('woff2'); unicode-range: U+0000-00FF, U+2000-206F; }
        @endforeach
        :root { --brand: {{ $brand }}; --brand-soft: color-mix(in oklab, var(--brand) 9%, white); --ink: #1c1917; --muted: #78716c; --line: #e7e5e4; --bg: #f5f5f4; --card: #fff; --good: #15803d; --bad: #b42318; color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100dvh; background: var(--bg); color: var(--ink); font: 16px/1.65 'IBM Plex Sans Arabic', system-ui, -apple-system, 'Segoe UI', sans-serif; -webkit-font-smoothing: antialiased; }
        main { max-width: 34rem; margin: 0 auto; padding: 1.25rem 1rem 2.5rem; display: flex; flex-direction: column; gap: .9rem; }
        .brand { display: flex; align-items: center; gap: .7rem; }
        .brand img { height: 2.4rem; width: auto; }
        .brand .mark { display: grid; place-items: center; width: 2.4rem; height: 2.4rem; border-radius: .7rem; background: var(--brand); color: #fff; font-weight: 700; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 1rem; padding: 1.2rem 1.15rem; }
        h1 { margin: 0 0 .35rem; font-size: 1.25rem; line-height: 1.4; }
        .intro { margin: 0; color: #44403c; }
        .points { list-style: none; margin: 1rem 0 0; padding: 0; display: grid; gap: .55rem; }
        .points li { background: var(--brand-soft); border-radius: .8rem; padding: .7rem .85rem; }
        .points h2 { margin: 0 0 .15rem; font-size: .95rem; }
        .points p { margin: 0; font-size: .92rem; color: #44403c; }
        form { margin: 1rem 0 0; }
        .agree { display: flex; gap: .6rem; align-items: flex-start; padding: .8rem .85rem; border: 1px solid var(--line); border-radius: .8rem; cursor: pointer; font-weight: 600; font-size: .95rem; }
        .agree input { width: 1.2rem; height: 1.2rem; margin: .2rem 0 0; accent-color: var(--brand); flex: none; }
        .btn { width: 100%; margin-top: .8rem; padding: .85rem 1rem; border: 0; border-radius: .85rem; background: var(--brand); color: #fff; font: inherit; font-weight: 700; cursor: pointer; }
        .banner { display: flex; gap: .6rem; align-items: center; border-radius: 1rem; padding: .85rem 1rem; font-weight: 600; background: #f0fdf4; color: var(--good); }
        .banner.bad { background: #fef3f2; color: var(--bad); }
        .banner.calm { background: var(--card); color: var(--ink); border: 1px solid var(--line); font-weight: 500; }
        footer { text-align: center; color: var(--muted); font-size: .78rem; }
        @media (prefers-reduced-motion: no-preference) {
            main > * { animation: rise .45s cubic-bezier(.2, .7, .2, 1) backwards; }
            main > :nth-child(2) { animation-delay: 60ms; } main > :nth-child(3) { animation-delay: 120ms; } main > :nth-child(4) { animation-delay: 180ms; }
            .banner { animation: pop .5s cubic-bezier(.34, 1.56, .64, 1) backwards; }
        }
        @keyframes rise { from { opacity: 0; transform: translateY(12px); } }
        @keyframes pop { from { opacity: 0; transform: scale(.9); } }
    </style>
</head>
<body>
<main>
    <div class="brand">
        @if ($branding?->logoUrl)<img src="{{ $branding->logoUrl }}" alt="">@else<span class="mark">{{ mb_substr($academy, 0, 1) }}</span>@endif
        <strong>{{ $academy }}</strong>
    </div>

    @if (! $linked)
        <div class="banner bad" role="status">{{ __($l.'link.expired') }}</div>
    @elseif (! $state->needed)
        <div class="banner calm" role="status">{{ __($l.'link.not_needed', $p) }}</div>
    @elseif ($state->granted)
        <div class="banner" role="status">✓ {{ $saved ? __($l.'link.saved', $p) : __($l.'link.already', ['when' => $since]) }}</div>
    @else
        @if ($errors->any())<div class="banner bad" role="alert">{{ $errors->first() }}</div>@endif
        <section class="card">
            <h1>{{ __($l.'text.title', $p) }}</h1>
            <p class="intro">{{ __($l.'text.intro', $p) }}</p>
            <ul class="points">
                @foreach (array_keys(trans($l.'text.sections')) as $key)
                    <li>
                        <h2>{{ __($l."text.sections.{$key}.title") }}</h2>
                        <p>{{ __($l."text.sections.{$key}.body", $p) }}</p>
                    </li>
                @endforeach
            </ul>
            <form method="post" action="{{ $action }}">
                @csrf
                <label class="agree">
                    <input type="checkbox" name="agree" value="1" required>
                    <span>{{ __($l.'text.agree', $p) }}</span>
                </label>
                <button type="submit" class="btn">{{ __($l.'actions.agree') }}</button>
            </form>
        </section>
    @endif

    <footer>{{ __($l.'family.lead', ['age' => $age]) }}</footer>
</main>
</body>
</html>
