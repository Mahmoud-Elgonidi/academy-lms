@php
    $l = 'identity::welcome.';
    $rtl = str_starts_with(app()->getLocale(), 'ar');
    $brand = $branding?->tokens['primary'] ?? '#0D735E';
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __($l.'title', ['academy' => $branding?->name() ?? config('app.name')]) }}</title>
    @if ($branding?->faviconUrl) <link rel="icon" href="{{ $branding->faviconUrl }}"> @endif
    <style>
        @foreach ([400, 600, 700] as $w)
        @font-face { font-family: 'IBM Plex Sans Arabic'; font-weight: {{ $w }}; font-display: swap; src: url('{{ asset("fonts/stages/ibm-plex-sans-arabic/ibm-plex-sans-arabic-arabic-{$w}-normal.woff2") }}') format('woff2'); unicode-range: U+0600-06FF, U+0750-077F, U+FB50-FDFF, U+FE70-FEFC; }
        @font-face { font-family: 'IBM Plex Sans Arabic'; font-weight: {{ $w }}; font-display: swap; src: url('{{ asset("fonts/stages/ibm-plex-sans-arabic/ibm-plex-sans-arabic-latin-{$w}-normal.woff2") }}') format('woff2'); unicode-range: U+0000-00FF, U+2000-206F; }
        @endforeach
        :root { --brand: {{ $brand }}; --brand-soft: color-mix(in oklab, var(--brand) 10%, white); --ink: #1c1917; --muted: #78716c; --line: #e7e5e4; --bg: #f5f5f4; --card: #fff; --bad: #b42318; color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100dvh; background: var(--bg); color: var(--ink); font: 16px/1.6 'IBM Plex Sans Arabic', system-ui, -apple-system, 'Segoe UI', sans-serif; -webkit-font-smoothing: antialiased; }
        main { max-width: 27rem; margin: 0 auto; padding: 1.5rem 1rem 2.5rem; display: flex; flex-direction: column; gap: .9rem; }
        .brand { display: flex; align-items: center; gap: .7rem; }
        .brand img { height: 2.4rem; width: auto; }
        .brand .mark { display: grid; place-items: center; width: 2.4rem; height: 2.4rem; border-radius: .7rem; background: var(--brand); color: #fff; font-weight: 700; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 1rem; padding: 1.3rem 1.2rem; }
        h1 { margin: 0; font-size: 1.3rem; line-height: 1.4; }
        .muted { color: var(--muted); font-size: .92rem; margin: .3rem 0 0; }
        .who { margin-top: .9rem; padding: .6rem .8rem; border-radius: .8rem; background: var(--brand-soft); font-size: .9rem; }
        .who strong { display: block; direction: ltr; text-align: {{ $rtl ? 'right' : 'left' }}; unicode-bidi: plaintext; }
        label { display: block; font-weight: 600; font-size: .9rem; margin: 1rem 0 .3rem; }
        input[type=password] { width: 100%; padding: .75rem .85rem; border: 1px solid var(--line); border-radius: .8rem; font: inherit; }
        input[type=password]:focus { outline: 2px solid color-mix(in oklab, var(--brand) 45%, transparent); border-color: var(--brand); }
        .btn { display: block; width: 100%; margin-top: 1.1rem; padding: .85rem 1rem; border: 0; border-radius: .85rem; background: var(--brand); color: #fff; font: inherit; font-weight: 700; cursor: pointer; text-align: center; text-decoration: none; }
        .banner { border-radius: 1rem; padding: .85rem 1rem; font-weight: 600; background: #fef3f2; color: var(--bad); }
        footer { text-align: center; color: var(--muted); font-size: .78rem; }
        @media (prefers-reduced-motion: no-preference) {
            main > * { animation: rise .45s cubic-bezier(.2, .7, .2, 1) backwards; }
            main > :nth-child(2) { animation-delay: 60ms; } main > :nth-child(3) { animation-delay: 120ms; }
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

    @if ($errors->any())<div class="banner" role="alert">{{ $errors->first() }}</div>@endif

    <section class="card">
        <h1>{{ __($l.'heading', ['name' => $person->name]) }}</h1>
        @if ($ready)
            <p class="muted">{{ __($l.'intro') }}</p>
            <div class="who">{{ __($l.'you_sign_in_with') }} <strong>{{ $login }}</strong></div>
            <form method="post" action="{{ $action }}">
                @csrf
                <label for="password">{{ __($l.'password') }}</label>
                <input id="password" name="password" type="password" autocomplete="new-password" minlength="{{ \Modules\Identity\Actions\SetFirstPassword::MIN }}" required>
                <label for="password_confirmation">{{ __($l.'confirm') }}</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                <button type="submit" class="btn">{{ __($l.'save') }}</button>
            </form>
        @else
            <p class="muted">{{ __($l.'already') }}</p>
            <a class="btn" href="{{ $home }}">{{ __($l.'sign_in') }}</a>
        @endif
    </section>
    <footer>{{ __($l.'private') }}</footer>
</main>
</body>
</html>
