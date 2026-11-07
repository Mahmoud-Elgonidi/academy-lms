@php($ar = fn (string $key, array $r = []) => __('platform::topups.mail.team.'.$key, $r, 'ar'))
@php($en = fn (string $key, array $r = []) => __('platform::topups.mail.team.'.$key, $r, 'en'))
{{ $ar('intro_'.$kind, ['academy' => $request['academy']]) }}

- {{ $request['what'] }}
@if ($request['note'])

{{ $request['note'] }}
@endif

{{ $ar('answer') }} {{ $url }}

----

{{ $en('intro_'.$kind, ['academy' => $request['academy']]) }}

{{ $en('answer') }} {{ $url }}
