@php($ar = fn (string $key, array $r = []) => __('platform::health.'.$key, $r, 'ar'))
@php($en = fn (string $key, array $r = []) => __('platform::health.'.$key, $r, 'en'))
{{ $failing === [] ? $ar('mail.well') : ($reminder ? $ar('mail.still') : $ar('mail.intro')) }}

@foreach ($failing as $name => $check)
- {{ \Modules\Platform\Health\SystemHealth::label($name, 'ar') }}: {{ $check['value'] }}@if ($check['hint']) — {{ $check['hint'] }}@endif

@endforeach
@foreach ($recovered as $name)
- {{ \Modules\Platform\Health\SystemHealth::label($name, 'ar') }}: {{ $ar('mail.recovered') }}
@endforeach

{{ $ar('mail.look') }} {{ $url }}

----

{{ $failing === [] ? $en('mail.well') : ($reminder ? $en('mail.still') : $en('mail.intro')) }}

@foreach ($failing as $name => $check)
- {{ \Modules\Platform\Health\SystemHealth::label($name, 'en') }}: {{ $check['status'] }}
@endforeach
@foreach ($recovered as $name)
- {{ \Modules\Platform\Health\SystemHealth::label($name, 'en') }}: {{ $en('mail.recovered') }}
@endforeach

{{ $en('mail.look') }} {{ $url }}
