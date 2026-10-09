@php
    $l = 'privacy::legal.';
    $rtl = $locale === 'ar';
    $brand = $branding?->tokens['primary'] ?? '#0D735E';
    $content = __($l.$page, $names);
    $sections = $content['sections'];
    $title = $content['title'];
    $updated = \Carbon\CarbonImmutable::parse($version)->locale($locale)->translatedFormat('j F Y');
    $other = $locale === 'ar' ? 'en' : 'ar';
@endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ $names['academy'] }}</title>
    <meta name="description" content="{{ \Illuminate\Support\Str::limit($content['intro'][$side], 155) }}">
    @foreach (['ar', 'en'] as $lang)
        <link rel="alternate" hreflang="{{ $lang }}" href="{{ url()->current() }}?lang={{ $lang }}">
    @endforeach
    @if ($branding?->faviconUrl) <link rel="icon" href="{{ $branding->faviconUrl }}"> @endif
    <style>
        @foreach ([400, 600, 700] as $w)
        @font-face { font-family: 'IBM Plex Sans Arabic'; font-weight: {{ $w }}; font-display: swap; src: url('{{ asset("fonts/stages/ibm-plex-sans-arabic/ibm-plex-sans-arabic-arabic-{$w}-normal.woff2") }}') format('woff2'); unicode-range: U+0600-06FF, U+0750-077F, U+FB50-FDFF, U+FE70-FEFC; }
        @font-face { font-family: 'IBM Plex Sans Arabic'; font-weight: {{ $w }}; font-display: swap; src: url('{{ asset("fonts/stages/ibm-plex-sans-arabic/ibm-plex-sans-arabic-latin-{$w}-normal.woff2") }}') format('woff2'); unicode-range: U+0000-00FF, U+2000-206F; }
        @endforeach
        :root { --brand: {{ $brand }}; --brand-soft: color-mix(in oklab, var(--brand) 9%, var(--card)); --ink: #1c1917; --muted: #6b6560; --line: #e7e5e4; --bg: #f5f5f4; --card: #fff; color-scheme: light dark; }
        @media (prefers-color-scheme: dark) {
            :root { --ink: #f5f5f4; --muted: #b5aea8; --line: #3a3532; --bg: #161413; --card: #201d1b; --brand: color-mix(in oklab, {{ $brand }} 70%, white); }
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--ink); font: 16px/1.75 'IBM Plex Sans Arabic', system-ui, -apple-system, 'Segoe UI', sans-serif; -webkit-font-smoothing: antialiased; }
        a { color: var(--brand); }
        .wrap { max-width: 46rem; margin: 0 auto; padding: 1.25rem 1rem 3rem; }
        header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
        .brand { display: flex; align-items: center; gap: .7rem; min-width: 0; }
        .brand img { height: 2.3rem; width: auto; }
        .brand .mark { flex: none; display: grid; place-items: center; width: 2.3rem; height: 2.3rem; border-radius: .7rem; background: var(--brand); color: #fff; font-weight: 700; }
        .brand strong { overflow-wrap: anywhere; }
        .lang { font-size: .9rem; text-decoration: none; padding: .35rem .7rem; border: 1px solid var(--line); border-radius: 999px; color: var(--ink); }
        nav { display: flex; gap: .4rem; flex-wrap: wrap; margin: 1.25rem 0 1rem; }
        nav a { text-decoration: none; color: var(--muted); padding: .4rem .85rem; border-radius: 999px; font-size: .92rem; border: 1px solid transparent; }
        nav a[aria-current=page] { color: var(--brand); background: var(--brand-soft); border-color: color-mix(in oklab, var(--brand) 25%, transparent); font-weight: 600; }
        article { background: var(--card); border: 1px solid var(--line); border-radius: 1.1rem; padding: 1.6rem 1.3rem; }
        h1 { margin: 0; font-size: 1.6rem; line-height: 1.35; }
        .updated { color: var(--muted); font-size: .88rem; margin: .25rem 0 1rem; }
        .intro { font-size: 1.04rem; }
        section { padding-top: 1.1rem; margin-top: 1.1rem; border-top: 1px solid var(--line); }
        h2 { margin: 0 0 .35rem; font-size: 1.12rem; }
        p { margin: .2rem 0; }
        ul { margin: .2rem 0; padding-inline-start: 1.2rem; }
        li { margin: .3rem 0; }
        .note { color: var(--muted); font-size: .92rem; margin-top: .5rem; }
        .extra { margin-top: 1.25rem; padding: 1rem 1.1rem; border-radius: .9rem; background: var(--brand-soft); }
        .extra h2 { font-size: 1rem; }
        .extra p { white-space: pre-line; }
        footer { margin-top: 1.25rem; color: var(--muted); font-size: .86rem; display: flex; flex-direction: column; gap: .2rem; }
        footer .ltr { direction: ltr; unicode-bidi: plaintext; align-self: flex-start; }
        @media (prefers-reduced-motion: no-preference) {
            article, nav { animation: rise .45s cubic-bezier(.2, .7, .2, 1) backwards; }
            article { animation-delay: 60ms; }
        }
        @keyframes rise { from { opacity: 0; transform: translateY(10px); } }
    </style>
</head>
<body>
<div class="wrap">
    <header>
        <div class="brand">
            @if ($branding?->logoUrl)<img src="{{ $branding->logoUrl }}" alt="">@else<span class="mark" aria-hidden="true">{{ mb_substr($names['academy'], 0, 1) }}</span>@endif
            <strong>{{ $names['academy'] }}</strong>
        </div>
        <a class="lang" href="?lang={{ $other }}" hreflang="{{ $other }}" lang="{{ $other }}">{{ __($l.'languages.'.$other) }}</a>
    </header>

    <nav aria-label="{{ $title }}">
        @foreach (\Modules\Privacy\Http\Controllers\LegalController::PAGES as $key => $route)
            <a href="{{ route($route, ['lang' => $locale], false) }}" @if ($key === $page) aria-current="page" @endif>{{ __($l.'nav.'.$key) }}</a>
        @endforeach
    </nav>

    <article>
        <h1>{{ $title }}</h1>
        <p class="updated">{{ __($l.'updated', ['date' => $updated]) }}</p>
        <p class="intro">{{ $content['intro'][$side] }}</p>

        @foreach ($sections as $key => $section)
            <section id="{{ $key }}">
                <h2>{{ $section['title'] }}</h2>
                @if ($page === 'deletion' && in_array($key, ['goes', 'stays'], true))
                    <ul>
                        @foreach (__('privacy::requests.'.$key) as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                    </ul>
                    @if ($key === 'stays')<p class="note">{{ __('privacy::requests.stays_note') }}</p>@endif
                @elseif (isset($section['items']))
                    <ul>
                        @foreach ($section['items'] as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                    </ul>
                @else
                    <p>{{ $section['body'] }}</p>
                @endif
            </section>
        @endforeach

        @if ($extra)
            <div class="extra">
                <h2>{{ __($l.'from_academy', ['academy' => $names['academy']]) }}</h2>
                <p>{{ $extra }}</p>
            </div>
        @endif
    </article>

    <footer>
        <span>{{ $names['controller'] }}</span>
        @if ($address)<span>{{ $address }}</span>@endif
        @if ($contactEmail)<a class="ltr" href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>@endif
    </footer>
</div>
</body>
</html>
