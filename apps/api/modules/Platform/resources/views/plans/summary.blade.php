{{-- An academy's plan: each limit's meter, the areas in and out; on the console also its count time and history. --}}
@php $l = 'platform::plans.summary.'; @endphp
<section class="pc-base pc-box">
    <h3>
        <span>{{ __($l.'title') }} <span class="pp-tag" data-tone="brand">{{ $plan['name'] }}</span></span>
        @if ($console ?? false)
            <button type="button" class="pp-link" wire:click="countNow" wire:loading.attr="disabled">{{ __($l.'count_now') }}</button>
        @endif
    </h3>
    <p class="pp-sub">
        @if ($plan['since'] ?? null){{ __($l.'since', ['date' => $plan['since']]) }}@endif
        @if ($plan['price'] ?? null) · {{ $plan['price'] }} {{ __('platform::plans.catalog.per_month') }}@endif
        @if ($console ?? false) · {{ $plan['measured_text'] ? __($l.'counted', ['when' => $plan['measured_text']]) : __($l.'not_counted') }}@endif
    </p>
    <div class="pp-meters">
        @foreach ($plan['meters'] as $m)
            <div class="pp-meter" data-state="{{ $m['state'] }}" wire:key="meter-{{ $m['key'] }}">
                <header><span>{{ $m['label'] }}</span><strong>{{ $m['text'] }}</strong></header>
                @if ($m['percent'] !== null)
                    <div class="pp-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $m['percent'] }}" aria-label="{{ $m['label'] }}"><span class="lms-bar-fill" style="width: {{ max(2, $m['percent']) }}%"></span></div>
                @endif
                <small class="pp-state">
                    @if (! $m['counted']){{ __($l.'state.uncounted') }}@elseif ($m['state'] === 'full'){{ __($l.'state.full') }}@elseif ($m['state'] === 'near'){{ __($l.'state.near') }}@elseif ($m['unlimited']){{ __($l.'state.unlimited') }}@else{{ __($l.'state.ok') }}@endif
                    @if ($m['monthly']) · {{ __($l.'monthly') }}@endif
                    @if ($m['own']) · {{ __($l.'own') }}@endif
                </small>
            </div>
        @endforeach
    </div>
    <ul class="pp-features pp-features-row">
        @foreach ($plan['features'] as $f)
            <li data-on="{{ $f['on'] ? 'yes' : 'no' }}">
                <x-filament::icon :icon="$f['on'] ? 'heroicon-m-check-circle' : 'heroicon-m-minus-circle'" class="pp-icon" />
                {{ $f['label'] }}@if ($f['own'] && ($console ?? false)) <small>({{ __($l.'own_feature') }})</small>@endif
            </li>
        @endforeach
    </ul>
    @if (($console ?? false) && count($plan['history'] ?? []) > 1)
        <details class="pp-details">
            <summary>{{ __($l.'history') }}</summary>
            <ul class="pp-history">
                @foreach ($plan['history'] as $h)
                    <li><span><strong>{{ $h['name'] }}</strong>@if ($h['reason']) <small>· {{ $h['reason'] }}</small>@endif</span><small>{{ $h['from_text'] }}{{ $h['to_text'] ? ' – '.$h['to_text'] : ' – '.__($l.'now') }}</small></li>
                @endforeach
            </ul>
        </details>
    @endif
</section>
