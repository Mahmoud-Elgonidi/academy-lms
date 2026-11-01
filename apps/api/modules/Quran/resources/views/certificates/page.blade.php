{{-- A Quran certificate to read and print (CertificatePageController): the academy's name, logo and colour, a QR code to its public verification. --}}
@php
    $l = 'quran::certificates.page.';
    $rtl = str_starts_with(app()->getLocale(), 'ar');
    $brand = $branding->tokens['primary'] ?? '#0D735E';
    $academy = $branding->name();
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} · {{ $c->number }}</title>
    @if ($branding->faviconUrl) <link rel="icon" href="{{ $branding->faviconUrl }}"> @endif
    <style>
        @foreach ([400, 600, 700] as $w)
        @font-face { font-family: 'IBM Plex Sans Arabic'; font-weight: {{ $w }}; font-display: swap; src: url('{{ asset("fonts/stages/ibm-plex-sans-arabic/ibm-plex-sans-arabic-arabic-{$w}-normal.woff2") }}') format('woff2'); unicode-range: U+0600-06FF, U+0750-077F, U+FB50-FDFF, U+FE70-FEFC; }
        @font-face { font-family: 'IBM Plex Sans Arabic'; font-weight: {{ $w }}; font-display: swap; src: url('{{ asset("fonts/stages/ibm-plex-sans-arabic/ibm-plex-sans-arabic-latin-{$w}-normal.woff2") }}') format('woff2'); unicode-range: U+0000-00FF, U+2000-206F; }
        @endforeach
        :root {
            --brand: {{ $brand }};
            --brand-soft: color-mix(in oklab, var(--brand) 8%, white);
            --brand-line: color-mix(in oklab, var(--brand) 35%, white);
            --ink: #1c1917; --muted: #78716c; --line: #e7e5e4; --bg: #f5f5f4; --bad: #b42318; --bad-soft: #fef3f2;
            color-scheme: light;
        }
        @page { size: A4 landscape; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100dvh; background: var(--bg); color: var(--ink); font: 16px/1.6 'IBM Plex Sans Arabic', system-ui, -apple-system, 'Segoe UI', sans-serif; -webkit-font-smoothing: antialiased; }
        main { max-width: 62rem; margin: 0 auto; padding: 1.25rem 1rem 2.5rem; display: flex; flex-direction: column; gap: .9rem; }
        .actions { display: flex; gap: .6rem; flex-wrap: wrap; }
        .actions button { font: inherit; font-weight: 600; font-size: .9rem; padding: .55rem 1.1rem; border-radius: .7rem; border: 0; background: var(--brand); color: #fff; cursor: pointer; }
        .actions button:focus-visible { outline: 2px solid var(--brand); outline-offset: 2px; }
        .revoked { padding: .8rem 1rem; border-radius: .9rem; background: var(--bad-soft); color: var(--bad); font-weight: 600; }
        .sheet { position: relative; aspect-ratio: 297 / 210; width: 100%; background: #fff; border-radius: 1rem; padding: clamp(.6rem, 2.2vw, 1.4rem); box-shadow: 0 20px 40px -28px rgb(0 0 0 / .35); }
        .frame { height: 100%; border: 2px solid var(--brand); border-radius: .8rem; padding: .35rem; }
        .inner { height: 100%; border: 1px solid var(--brand-line); border-radius: .55rem; background: radial-gradient(circle at 50% 0%, var(--brand-soft), #fff 60%);
            display: flex; flex-direction: column; align-items: center; text-align: center; padding: clamp(.8rem, 3vw, 2.2rem) clamp(.8rem, 4vw, 3rem); gap: clamp(.2rem, 1vw, .7rem); }
        .academy { display: flex; align-items: center; gap: .6rem; }
        .academy img { height: clamp(1.8rem, 5vw, 3.2rem); width: auto; }
        .academy .mark { display: grid; place-items: center; width: clamp(1.8rem, 5vw, 3rem); height: clamp(1.8rem, 5vw, 3rem); border-radius: .7rem; background: var(--brand); color: #fff; font-weight: 700; }
        .academy strong { font-size: clamp(.9rem, 2.2vw, 1.25rem); }
        h1 { margin: clamp(.2rem, 1.5vw, 1rem) 0 0; font-size: clamp(1.3rem, 4.4vw, 2.6rem); line-height: 1.25; color: var(--brand); letter-spacing: -.01em; }
        .lead { color: var(--muted); font-size: clamp(.8rem, 1.8vw, 1rem); margin: 0; }
        .name { font-size: clamp(1.2rem, 4vw, 2.3rem); font-weight: 700; margin: 0; line-height: 1.3; padding: 0 1.5rem .2rem; border-bottom: 2px solid var(--brand-line); }
        .portion { font-size: clamp(.95rem, 2.4vw, 1.35rem); font-weight: 600; margin: 0; }
        .facts { display: flex; flex-wrap: wrap; justify-content: center; gap: .3rem clamp(.8rem, 3vw, 2.2rem); margin: clamp(.2rem, 1vw, .6rem) 0 0; font-size: clamp(.75rem, 1.6vw, .95rem); }
        .facts div { display: flex; flex-direction: column; }
        .facts dt { color: var(--muted); font-size: .85em; }
        .facts dd { margin: 0; font-weight: 600; }
        .foot { margin-top: auto; width: 100%; display: flex; justify-content: space-between; align-items: flex-end; gap: 1rem; }
        .sign { min-width: 30%; text-align: center; font-size: clamp(.75rem, 1.6vw, .95rem); }
        .sign .line { border-top: 1px solid var(--ink); margin-top: clamp(1rem, 4vw, 2.2rem); padding-top: .25rem; }
        .verify { display: flex; align-items: center; gap: .6rem; text-align: start; font-size: clamp(.6rem, 1.3vw, .78rem); color: var(--muted); }
        .verify svg { width: clamp(3.4rem, 9vw, 5.6rem); height: auto; flex: none; }
        .verify strong { display: block; color: var(--ink); font-variant-numeric: tabular-nums; letter-spacing: .04em; }
        .number { font-variant-numeric: tabular-nums; direction: ltr; unicode-bidi: isolate; }
        @media print {
            body { background: #fff; } main { max-width: none; padding: 0; } .no-print { display: none !important; }
            .sheet { border-radius: 0; box-shadow: none; width: 297mm; height: 210mm; aspect-ratio: auto; padding: 10mm; }
            * { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        }
        @media (max-width: 40rem) { .sheet { aspect-ratio: auto; } .foot { flex-direction: column; align-items: center; } }
        @media (prefers-reduced-motion: no-preference) {
            .sheet { animation: rise .5s cubic-bezier(.2, .7, .2, 1) backwards; }
            .name { animation: fade .6s ease .2s backwards; }
        }
        @keyframes rise { from { opacity: 0; transform: translateY(14px) scale(.99); } }
        @keyframes fade { from { opacity: 0; transform: translateY(6px); } }
    </style>
</head>
<body>
    <main>
        <div class="actions no-print">
            @unless ($revoked) <button type="button" data-print>{{ __($l.'print') }}</button> @endunless
        </div>
        @if ($revoked)
            <div class="revoked" role="status">{{ __($l.'revoked', ['day' => $revoked]) }}</div>
        @endif
        <article class="sheet" aria-label="{{ $title }}">
            <div class="frame"><div class="inner">
                <div class="academy">
                    @if ($branding->logoUrl)
                        <img src="{{ $branding->logoUrl }}" alt="">
                    @else
                        <span class="mark">{{ mb_substr($academy, 0, 1) }}</span>
                    @endif
                    <strong>{{ $academy }}</strong>
                </div>
                <h1>{{ $title }}</h1>
                <p class="lead">{{ __($l.'certifies') }}</p>
                <p class="name">{{ $student }}</p>
                <p class="lead">{{ __($l.'completed') }}</p>
                <p class="portion">{{ $portion }}</p>
                <dl class="facts">
                    <div><dt>{{ __($l.'program') }}</dt><dd>{{ $program }}</dd></div>
                    <div><dt>{{ __($l.'riwaya') }}</dt><dd>{{ $riwaya }}</dd></div>
                    <div><dt>{{ __($l.'grade') }}</dt><dd>{{ $grade }}</dd></div>
                    @if ($examiner !== '') <div><dt>{{ __($l.'examiner') }}</dt><dd>{{ $examiner }}</dd></div> @endif
                    <div><dt>{{ __($l.'issued') }}</dt><dd>{{ $issued }}</dd></div>
                    <div><dt>{{ __($l.'number') }}</dt><dd class="number">{{ $c->number }}</dd></div>
                </dl>
                <div class="foot">
                    <div class="verify">
                        @if ($qr) {!! $qr !!} @endif
                        <div>{{ __($l.'verify') }}<strong class="number">{{ $code }}</strong></div>
                    </div>
                    <div class="sign">
                        <div class="line">{{ $c->signature ?? __($l.'signature', ['academy' => $academy]) }}</div>
                    </div>
                </div>
            </div></div>
        </article>
    </main>
    <script>
        document.querySelector('[data-print]')?.addEventListener('click', () => window.print());
    </script>
</body>
</html>
