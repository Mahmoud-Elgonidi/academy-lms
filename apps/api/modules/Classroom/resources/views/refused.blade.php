<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('classroom::classroom.refused.title') }} · {{ $brand->name() }}</title>
    <style>
        :root { --brand: {{ $brand->tokens['primary'] ?? '#0F766E' }}; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f4f4f5; font-family: 'IBM Plex Sans Arabic', Tahoma, Arial, sans-serif; color: #18181b; padding: 16px; box-sizing: border-box; }
        .card { max-width: 420px; background: #fff; border-radius: 16px; padding: 28px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,.08); border-top: 4px solid var(--brand); }
        h1 { font-size: 20px; margin: 0 0 8px; } p { margin: 0; color: #52525b; line-height: 1.7; }
        @media (prefers-color-scheme: dark) { body { background: #18181b; color: #f4f4f5; } .card { background: #27272a; } p { color: #a1a1aa; } }
    </style>
</head>
<body><div class="card"><h1>{{ __('classroom::classroom.refused.title') }}</h1><p>{{ ($consent ?? false) ? __('classroom::classroom.refused.consent') : __('classroom::classroom.refused.body') }}</p></div></body>
</html>
