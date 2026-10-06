<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ str_starts_with(app()->getLocale(), 'ar') ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Demo Pay</title>
    <style>
        body { margin: 0; min-height: 100dvh; display: grid; place-items: center; background: #0f172a; color: #0f172a; font: 16px/1.5 system-ui, -apple-system, 'Segoe UI', sans-serif; padding: 1rem; }
        .sheet { width: 100%; max-width: 24rem; background: #fff; border-radius: 1.1rem; padding: 1.4rem; box-shadow: 0 30px 60px -20px rgb(0 0 0 / .5); }
        .tag { display: inline-block; font-size: .72rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #9a3412; background: #ffedd5; padding: .15rem .55rem; border-radius: 999px; }
        h1 { margin: .6rem 0 .1rem; font-size: 1.9rem; font-variant-numeric: tabular-nums; }
        p { margin: 0; color: #64748b; font-size: .9rem; }
        .card { margin: 1.1rem 0; border: 1px solid #e2e8f0; border-radius: .8rem; padding: .8rem; font-family: ui-monospace, monospace; color: #334155; direction: ltr; text-align: left; }
        .card div { display: flex; justify-content: space-between; margin-top: .3rem; color: #94a3b8; font-size: .85rem; }
        form { margin: 0; }
        button { width: 100%; padding: .85rem; border: 0; border-radius: .75rem; font: inherit; font-weight: 700; cursor: pointer; }
        .pay { background: #4f46e5; color: #fff; }
        .decline { margin-top: .5rem; background: #f1f5f9; color: #475569; }
    </style>
</head>
<body>
    <div class="sheet">
        <span class="tag">{{ __('billing::payments.demo.tag') }}</span>
        <h1>{{ $amount->format() }}</h1>
        <p>{{ $state['description'] }}</p>
        <div class="card">4242 4242 4242 4242<div><span>12 / 34</span><span>CVC 123</span></div></div>
        <form method="post" action="{{ $pay }}">@csrf<button class="pay">{{ __('billing::payments.demo.pay') }}</button></form>
        <form method="post" action="{{ $decline }}">@csrf<button class="decline">{{ __('billing::payments.demo.decline') }}</button></form>
        <p style="margin-top: .9rem; font-size: .78rem">{{ __('billing::payments.demo.hint') }}</p>
    </div>
</body>
</html>
