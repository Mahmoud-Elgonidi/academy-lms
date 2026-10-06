<!doctype html>
<html lang="{{ $lang }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $heading }}</title>
</head>
<body style="margin:0; padding:24px 12px; background:#f4f4f5; font-family: Tahoma, 'Segoe UI', Arial, sans-serif; color:#18181b;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; margin:0 auto; background:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e4e4e7;">
        <tr>
            <td style="background: {{ $colour }}; padding:16px 24px; color:#ffffff; font-size:16px; font-weight:bold; text-align: {{ $rtl ? 'right' : 'left' }};">
                @if ($logo)<img src="{{ $logo }}" alt="" height="28" style="vertical-align:middle; {{ $rtl ? 'margin-left' : 'margin-right' }}:8px;">@endif
                {{ $academy }}
            </td>
        </tr>
        <tr>
            <td style="padding:24px; text-align: {{ $rtl ? 'right' : 'left' }}; line-height:1.7;">
                <p style="margin:0 0 4px; color:#71717a; font-size:14px;">{{ trans('communication::communication.mail.greeting', ['name' => $recipientName], $lang) }}</p>
                <h1 style="margin:0 0 12px; font-size:20px;">{{ $heading }}</h1>
                <p style="margin:0; font-size:15px; white-space:pre-line;">{{ $text }}</p>
            </td>
        </tr>
        <tr>
            <td style="padding:14px 24px; border-top:1px solid #e4e4e7; color:#a1a1aa; font-size:12px; text-align: {{ $rtl ? 'right' : 'left' }};">
                {{ trans('communication::communication.mail.footer', ['academy' => $academy], $lang) }}
            </td>
        </tr>
    </table>
</body>
</html>
