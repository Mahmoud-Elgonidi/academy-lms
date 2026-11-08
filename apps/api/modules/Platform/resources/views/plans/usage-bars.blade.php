{{-- Horizontal bars, most first: one magnitude, one hue (the brand's), the value as text beside each. --}}
@if ($rows === [])
    <p class="pp-sub">{{ __('platform::metering.charts.none') }}</p>
@else
    <ul class="mu-bars">
        @foreach ($rows as $r)
            <li title="{{ $r['name'] }}: {{ $r['text'] }}">
                <span class="mu-bar-name">{{ $r['name'] }}</span>
                <span class="mu-bar-track"><span class="lms-bar-fill" style="width: {{ max(2, $r['pct']) }}%"></span></span>
                <span class="mu-bar-value">{{ $r['text'] }}</span>
            </li>
        @endforeach
    </ul>
@endif
