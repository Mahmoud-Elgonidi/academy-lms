{{-- Signed in with an account this panel does not take (App\Http\Middleware\AuthenticateForPanel): who, and the ways out. --}}
@php $rtl = app()->getLocale() === 'ar'; @endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('shared::panels.wrong.title', ['area' => $area]) }}</title>
    <style>
        :root { --ink: #1c1917; --muted: #78716c; --line: #e7e5e4; --bg: #f5f5f4; --card: #fff; --brand: #0d735e; color-scheme: light; }
        @media (prefers-color-scheme: dark) { :root { --ink: #f5f5f4; --muted: #a8a29e; --line: #292524; --bg: #0c0a09; --card: #1c1917; --brand: #3fb894; color-scheme: dark; } }
        body { margin: 0; min-height: 100dvh; display: grid; place-items: center; padding: 1rem; background: var(--bg); color: var(--ink); font: 16px/1.6 system-ui, -apple-system, 'Segoe UI', Tahoma, sans-serif; }
        main { max-width: 27rem; background: var(--card); border: 1px solid var(--line); border-radius: 1.1rem; padding: 1.6rem 1.3rem; text-align: center; display: grid; gap: .9rem; }
        h1 { margin: 0; font-size: 1.2rem; }
        p { margin: 0; color: var(--muted); font-size: .95rem; }
        form { margin: 0; }
        .actions { display: grid; gap: .5rem; }
        button, a.btn { display: block; width: 100%; box-sizing: border-box; border-radius: .7rem; padding: .7rem 1rem; font: inherit; font-weight: 600; text-decoration: none; cursor: pointer; }
        button { background: var(--brand); color: #fff; border: 1px solid var(--brand); }
        a.btn { background: transparent; color: var(--ink); border: 1px solid var(--line); }
        :focus-visible { outline: 2px solid var(--brand); outline-offset: 2px; }
    </style>
</head>
<body>
<main>
    <h1>{{ __('shared::panels.wrong.title', ['area' => $area]) }}</h1>
    <p>{{ __('shared::panels.wrong.body', ['name' => $name, 'area' => $area]) }}</p>
    <div class="actions">
        <form method="post" action="{{ $logout }}">
            @csrf
            <button type="submit">{{ __('shared::panels.wrong.sign_out') }}</button>
        </form>
        @if ($own)
            <a class="btn" href="{{ $own }}">{{ __('shared::panels.wrong.own', ['area' => $ownArea]) }}</a>
        @endif
    </div>
</main>
</body>
</html>
