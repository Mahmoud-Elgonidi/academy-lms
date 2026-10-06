@php
    $l = 'evaluation::ratings.';
    $rtl = str_starts_with(app()->getLocale(), 'ar');
    $brand = $branding?->tokens['primary'] ?? '#0D735E';
    $choices = $rating ? \Modules\Evaluation\Models\LessonRating::TAGS[$rating->stars >= 4 ? 'good' : 'bad'] : [];
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __($l.'page.title') }}</title>
    @if ($branding?->faviconUrl) <link rel="icon" href="{{ $branding->faviconUrl }}"> @endif
    <style>
        @foreach ([400, 600, 700] as $w)
        @font-face { font-family: 'IBM Plex Sans Arabic'; font-weight: {{ $w }}; font-display: swap; src: url('{{ asset("fonts/stages/ibm-plex-sans-arabic/ibm-plex-sans-arabic-arabic-{$w}-normal.woff2") }}') format('woff2'); unicode-range: U+0600-06FF, U+0750-077F, U+FB50-FDFF, U+FE70-FEFC; }
        @font-face { font-family: 'IBM Plex Sans Arabic'; font-weight: {{ $w }}; font-display: swap; src: url('{{ asset("fonts/stages/ibm-plex-sans-arabic/ibm-plex-sans-arabic-latin-{$w}-normal.woff2") }}') format('woff2'); unicode-range: U+0000-00FF, U+2000-206F; }
        @endforeach
        :root { --brand: {{ $brand }}; --brand-soft: color-mix(in oklab, var(--brand) 10%, white); --ink: #1c1917; --muted: #78716c; --line: #e7e5e4; --bg: #f5f5f4; --card: #fff; --star: #f59e0b; --good: #15803d; --bad: #b42318; color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100dvh; background: var(--bg); color: var(--ink); font: 16px/1.6 'IBM Plex Sans Arabic', system-ui, -apple-system, 'Segoe UI', sans-serif; -webkit-font-smoothing: antialiased; }
        main { max-width: 30rem; margin: 0 auto; padding: 1.25rem 1rem 2.5rem; display: flex; flex-direction: column; gap: .9rem; }
        .brand { display: flex; align-items: center; gap: .7rem; }
        .brand img { height: 2.4rem; width: auto; }
        .brand .mark { display: grid; place-items: center; width: 2.4rem; height: 2.4rem; border-radius: .7rem; background: var(--brand); color: #fff; font-weight: 700; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 1rem; padding: 1.2rem 1.15rem; }
        h1 { margin: 0; font-size: 1.25rem; line-height: 1.4; }
        .muted { color: var(--muted); font-size: .9rem; margin: .2rem 0 0; }
        form { margin: 0; }
        .stars { display: flex; justify-content: center; gap: .35rem; margin: 1rem 0 .3rem; }
        .stars button { width: 3.2rem; height: 3.2rem; border: 0; border-radius: .9rem; background: #fafaf9; color: #d6d3d1; cursor: pointer; display: grid; place-items: center; transition: transform .15s, background .2s; }
        .stars button svg { width: 2.2rem; height: 2.2rem; }
        .stars button.on { color: var(--star); background: #fffbeb; }
        .stars button:hover { transform: scale(1.08); }
        .stars button:active { transform: scale(.94); }
        .hint { text-align: center; color: var(--muted); font-size: .85rem; margin: 0; }
        .words { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .6rem; }
        .words label { display: inline-flex; align-items: center; gap: .3rem; font-size: .88rem; padding: .3rem .75rem; border: 1px solid var(--line); border-radius: 999px; cursor: pointer; }
        .words input { accent-color: var(--brand); }
        textarea { width: 100%; min-height: 5.5rem; margin-top: .7rem; padding: .7rem .8rem; border: 1px solid var(--line); border-radius: .8rem; font: inherit; resize: vertical; }
        .btn { width: 100%; margin-top: .8rem; padding: .8rem 1rem; border: 0; border-radius: .85rem; background: var(--brand); color: #fff; font: inherit; font-weight: 700; cursor: pointer; }
        .banner { display: flex; gap: .6rem; align-items: center; border-radius: 1rem; padding: .85rem 1rem; font-weight: 600; background: #f0fdf4; color: var(--good); }
        .banner.bad { background: #fef3f2; color: var(--bad); }
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
        @if ($branding?->logoUrl)<img src="{{ $branding->logoUrl }}" alt="">@else<span class="mark">{{ mb_substr($branding?->name() ?? config('app.name'), 0, 1) }}</span>@endif
        <strong>{{ $branding?->name() ?? config('app.name') }}</strong>
    </div>

    @if ($problem)
        <div class="banner bad" role="status">{{ $problem }}</div>
    @else
        @if ($saved)<div class="banner" role="status">✓ {{ __($l.'page.saved') }}</div>@endif
        @if ($errors->any())<div class="banner bad" role="alert">{{ $errors->first() }}</div>@endif

        <section class="card">
            <h1>{{ __($l.'page.heading', ['student' => $lesson->student]) }}</h1>
            <p class="muted">{{ collect([$subject, $lesson->teacher, $when])->filter()->implode(' · ') }}</p>
            <form method="post" action="{{ $action }}">
                @csrf
                <div class="stars" role="radiogroup" aria-label="{{ __($l.'ask') }}">
                    @foreach (range(1, 5) as $n)
                        <button type="submit" name="stars" value="{{ $n }}" class="{{ $rating && $n <= $rating->stars ? 'on' : '' }}" aria-label="{{ trans_choice($l.'stars', $n, ['count' => $n]) }}">
                            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005Z"/></svg>
                        </button>
                    @endforeach
                </div>
                <p class="hint">{{ $rating ? __($l.'page.change') : __($l.'page.tap') }}</p>
            </form>
        </section>

        @if ($rating)
            <section class="card">
                <form method="post" action="{{ $action }}">
                    @csrf
                    <strong>{{ __($l.'page.more') }}</strong>
                    <div class="words">
                        @foreach ($choices as $tag)
                            <label><input type="checkbox" name="tags[]" value="{{ $tag }}" @checked(in_array($tag, $rating->tags, true))> {{ __($l.'tags.'.$tag) }}</label>
                        @endforeach
                    </div>
                    <textarea name="comment" maxlength="500" placeholder="{{ __($l.'page.comment') }}">{{ $rating->comment }}</textarea>
                    <button type="submit" class="btn">{{ __($l.'page.send') }}</button>
                </form>
            </section>
        @endif
    @endif
    <footer>{{ __($l.'page.private') }}</footer>
</main>
</body>
</html>
