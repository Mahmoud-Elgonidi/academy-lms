@php($ar = fn (string $key, array $r = []) => __('platform::health.'.$key, $r, 'ar'))
@php($en = fn (string $key, array $r = []) => __('platform::health.'.$key, $r, 'en'))
{{ $failing === [] ? $ar('mail.well') : ($reminder ? $ar('mail.still') : $ar('mail.intro')) }}

@foreach ($failing as $name => $check)
- {{ $ar('checks.'.$name) }}: {{ $check['value'] }}@if ($check['hint']) — {{ $check['hint'] }}@endif

@endforeach
@foreach ($recovered as $name)
- {{ $ar('checks.'.$name) }}: {{ $ar('mail.recovered') }}
@endforeach

{{ $ar('mail.look') }} {{ $url }}

----

{{ $failing === [] ? $en('mail.well') : ($reminder ? $en('mail.still') : $en('mail.intro')) }}

@foreach ($failing as $name => $check)
- {{ $en('checks.'.$name) }}: {{ $check['status'] }}
@endforeach
@foreach ($recovered as $name)
- {{ $en('checks.'.$name) }}: {{ $en('mail.recovered') }}
@endforeach

{{ $en('mail.look') }} {{ $url }}
