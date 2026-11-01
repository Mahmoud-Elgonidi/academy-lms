{{--
    The strip of the mushaf's parts (Programs\JuzStrip): $parts (how many), $scope (a program's: cells with in and edge, or null),
    $shares (part => share memorized), $at (the part the plan is in), $height, $legend, $label (for screen readers).
--}}
@php
    $scope = $scope ?? null;
    $shares = $shares ?? [];
    $at = $at ?? null;
    $parts = max(1, (int) ($parts ?? 30));
    $cells = $scope['cells'] ?? array_map(fn ($j) => ['juz' => $j, 'in' => false, 'edge' => null], range(1, $parts));
    $w = 'quran::screens.strip.';
@endphp
<div>
    <div class="qs-strip" style="--parts: {{ $parts }}; --h: {{ $height ?? '1.5rem' }}" role="img" aria-label="{{ $label ?? __($w.'label') }}">
        @foreach ($cells as $c)
            <span @if ($c['in']) data-in @endif @if ($c['edge']) data-edge="{{ $c['edge'] }}" @endif @if ($at === $c['juz']) data-at @endif
                title="{{ __($w.'juz', ['juz' => $c['juz']]) }}@if (($shares[$c['juz']] ?? 0) > 0) · {{ __($w.'percent', ['n' => round(100 * $shares[$c['juz']])]) }}@endif">
                @if (($shares[$c['juz']] ?? 0) > 0)<i style="--share: {{ $shares[$c['juz']] }}"></i>@endif
            </span>
        @endforeach
    </div>
    <div class="qs-strip-axis" aria-hidden="true"><span>{{ __($w.'first') }}</span><span>{{ __($w.'last', ['n' => $parts]) }}</span></div>
    @if ($legend ?? false)
        <div class="qs-strip-legend">
            @if ($scope)<span><i style="background: var(--primary-100)"></i>{{ __($w.'scope') }}</span><span><i style="box-shadow: inset 0 0 0 2px var(--primary-600)"></i>{{ __($w.'start') }}</span>@endif
            @if ($shares !== [])<span><i style="background: var(--primary-500)"></i>{{ __($w.'memorized') }}</span>@endif
            @if ($at)<span><i style="box-shadow: inset 0 0 0 2px var(--warning-500)"></i>{{ __($w.'here') }}</span>@endif
        </div>
    @endif
</div>
