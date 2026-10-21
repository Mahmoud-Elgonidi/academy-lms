@php($ar = fn (string $key, array $r = []) => __('platform::site.'.$key, $r, 'ar'))
@php($en = fn (string $key, array $r = []) => __('platform::site.'.$key, $r, 'en'))
@php($r = $request)
{{ $ar('mail.intro') }}

- {{ $ar('request.fields.academy_name') }}: {{ $r['academy_name'] }}
- {{ $ar('request.fields.contact_name') }}: {{ $r['contact_name'] }}
- {{ $ar('request.fields.email') }}: {{ $r['email'] }}
@if ($r['phone'])
- {{ $ar('request.fields.phone') }}: {{ $r['phone'] }}
@endif
@if ($r['country'])
- {{ $ar('request.fields.country') }}: {{ $r['country'] }}
@endif
@if ($r['students'])
- {{ $ar('request.fields.students') }}: {{ $r['students'] }}
@endif
@if ($r['plan_code'])
- {{ $ar('request.fields.plan') }}: {{ $r['plan_code'] }}
@endif
@if ($r['message'])

{{ $r['message'] }}
@endif
@if ($r['utm_source'] || $r['utm_campaign'])

utm: {{ trim(($r['utm_source'] ?? '').' / '.($r['utm_campaign'] ?? ''), ' /') }}
@endif

{{ $ar('mail.answer') }} {{ $url }}

----

{{ $en('mail.intro') }} {{ $r['academy_name'] }} ({{ $r['email'] }}).

{{ $en('mail.answer') }} {{ $url }}
