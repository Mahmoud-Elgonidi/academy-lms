{{-- The platform's receipt for a pack an academy bought: to read and print (the browser makes the PDF). The platform's own document, in its neutral ink. --}}
@php $l = 'platform::recharge.receipt.'; @endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __($l.'title') }} {{ $rows['number'] }}</title>
    <style>
        @foreach ([400, 600, 700] as $w)
        @font-face { font-family: 'IBM Plex Sans Arabic'; font-weight: {{ $w }}; font-display: swap; src: url('{{ asset("fonts/stages/ibm-plex-sans-arabic/ibm-plex-sans-arabic-arabic-{$w}-normal.woff2") }}') format('woff2'); unicode-range: U+0600-06FF, U+0750-077F, U+FB50-FDFF, U+FE70-FEFC; }
        @font-face { font-family: 'IBM Plex Sans Arabic'; font-weight: {{ $w }}; font-display: swap; src: url('{{ asset("fonts/stages/ibm-plex-sans-arabic/ibm-plex-sans-arabic-latin-{$w}-normal.woff2") }}') format('woff2'); unicode-range: U+0000-00FF, U+2000-206F; }
        @endforeach
        :root { --ink: #1c1917; --muted: #78716c; --line: #e7e5e4; --bg: #f5f5f4; --card: #fff; --soft: #fafaf9; color-scheme: light; }
        @media (prefers-color-scheme: dark) { :root { --ink: #f5f5f4; --muted: #a8a29e; --line: #292524; --bg: #0c0a09; --card: #1c1917; --soft: #161412; color-scheme: dark; } }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100dvh; background: var(--bg); color: var(--ink); font: 15px/1.6 'IBM Plex Sans Arabic', system-ui, -apple-system, 'Segoe UI', sans-serif; padding: 2rem 1rem; }
        .sheet { max-width: 40rem; margin: 0 auto; background: var(--card); border: 1px solid var(--line); border-radius: 1rem; padding: 2rem 1.75rem; }
        header { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; border-bottom: 1px solid var(--line); padding-bottom: 1.1rem; margin-bottom: 1.2rem; }
        h1 { margin: 0; font-size: 1.35rem; }
        .num { font-family: ui-monospace, 'SFMono-Regular', Menlo, monospace; font-size: .95rem; color: var(--muted); }
        .paid { border: 1px solid var(--line); border-radius: 999px; padding: .2rem .8rem; font-size: .8rem; font-weight: 600; white-space: nowrap; }
        dl { display: grid; grid-template-columns: minmax(8rem, auto) 1fr; gap: .55rem 1.25rem; margin: 0; }
        dt { color: var(--muted); }
        dd { margin: 0; font-weight: 600; }
        .total { margin-top: 1.4rem; background: var(--soft); border: 1px solid var(--line); border-radius: .8rem; padding: 1rem 1.1rem; display: flex; justify-content: space-between; align-items: baseline; }
        .total strong { font-size: 1.4rem; }
        footer { margin-top: 1.4rem; color: var(--muted); font-size: .82rem; display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; }
        button { font: inherit; padding: .5rem 1rem; border-radius: .6rem; border: 1px solid var(--line); background: var(--card); color: var(--ink); cursor: pointer; }
        @media print { body { background: #fff; padding: 0; } .sheet { border: 0; } button { display: none; } }
    </style>
</head>
<body>
<main class="sheet">
    <header>
        <div>
            <h1>{{ __($l.'title') }}</h1>
            <div class="num" dir="ltr">{{ $rows['number'] }}</div>
        </div>
        <span class="paid">{{ __($l.'paid') }}</span>
    </header>
    <dl>
        <dt>{{ __($l.'date') }}</dt><dd>{{ $rows['date'] }}</dd>
        <dt>{{ __($l.'seller') }}</dt><dd>{{ $rows['seller'] }}</dd>
        <dt>{{ __($l.'buyer') }}</dt><dd>{{ $rows['buyer'] }}</dd>
        <dt>{{ __($l.'pack') }}</dt><dd>{{ $rows['pack'] }}</dd>
        <dt>{{ __($l.'what') }}</dt><dd>{{ $rows['what'] }}</dd>
        <dt>{{ __($l.'method') }}</dt><dd>{{ $rows['method'] }}</dd>
    </dl>
    <div class="total"><span>{{ __($l.'amount') }}</span><strong dir="ltr">{{ $rows['amount'] }}</strong></div>
    <footer>
        <span>{{ __($l.'note') }}</span>
        <button type="button" data-print>{{ __($l.'print') }}</button>
    </footer>
</main>
<script>
    document.querySelector('[data-print]')?.addEventListener('click', () => window.print());
</script>
</body>
</html>
