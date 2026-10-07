<!doctype html>
<html lang="{{ $lang }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ trans('communication::otp.mail.heading', [], $lang) }}</title>
</head>
<body style="margin:0; padding:24px 12px; background:#f4f4f5; font-family: Tahoma, 'Segoe UI', Arial, sans-serif; color:#18181b;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px; margin:0 auto; background:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e4e4e7;">
        <tr>
            <td style="background: {{ $colour }}; padding:16px 24px; color:#ffffff; font-size:16px; font-weight:bold; text-align: {{ $rtl ? 'right' : 'left' }};">
                @if ($logo)<img src="{{ $logo }}" alt="" height="28" style="vertical-align:middle; {{ $rtl ? 'margin-left' : 'margin-right' }}:8px;">@endif
                {{ $name }}
            </td>
        </tr>
        <tr>
            <td style="padding:24px; text-align: {{ $rtl ? 'right' : 'left' }}; line-height:1.7;">
                <h1 style="margin:0 0 8px; font-size:20px;">{{ trans('communication::otp.mail.heading', [], $lang) }}</h1>
                <p style="margin:0 0 16px; font-size:15px;">{{ trans('communication::otp.mail.body', ['minutes' => $minutes], $lang) }}</p>
                <p dir="ltr" style="margin:0 0 16px; text-align:center; font-size:32px; font-weight:bold; letter-spacing:8px; font-family: 'Courier New', monospace; background:#f4f4f5; border-radius:8px; padding:12px;">{{ $code }}</p>
                <p style="margin:0; font-size:13px; color:#71717a;">{{ trans('communication::otp.mail.never', ['name' => $name], $lang) }}</p>
            </td>
        </tr>
    </table>
</body>
</html>
