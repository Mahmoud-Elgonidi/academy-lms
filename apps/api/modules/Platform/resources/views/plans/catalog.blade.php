@php $l = 'platform::plans.catalog.'; $plans = $this->plans; @endphp
<x-filament-panels::page>
    @include('platform::console.styles')
    @include('platform::plans.styles')
    <div class="pc-grid lms-stagger">
        @foreach ($plans as $p)
            <article class="pc-base pp-plan" wire:key="plan-{{ $p['id'] }}" @if ($p['archived']) data-archived @endif>
                <header>
                    <div>
                        <h3>{{ $p['name'] }} <small>{{ $p['other'] }}</small></h3>
                        <code>{{ $p['code'] }}</code>
                    </div>
                    <div class="pp-tags">
                        @if ($p['default'])<span class="pp-tag" data-tone="brand">{{ __($l.'default') }}</span>@endif
                        @if ($p['archived'])<span class="pp-tag">{{ __($l.'archived') }}</span>@endif
                        @if ($p['highlight'] && ! $p['archived'])<span class="pp-tag" data-tone="brand">{{ __($l.'highlighted') }}</span>@endif
                        @if (! $p['on_site'] && ! $p['archived'])<span class="pp-tag">{{ __($l.'off_site') }}</span>@endif
                    </div>
                </header>
                <div class="pp-price">
                    <strong>{{ $p['price'] ?? __($l.'no_price') }}</strong>@if ($p['price'])<small>{{ __($l.'per_month') }}</small>@endif
                </div>
                <dl class="pp-limits">
                    @foreach ($p['limits'] as $limit)
                        <dt>{{ $limit['label'] }}</dt><dd>{{ $limit['value'] }}</dd>
                    @endforeach
                </dl>
                <ul class="pp-features">
                    @foreach ($p['features'] as $f)
                        <li data-on="{{ $f['on'] ? 'yes' : 'no' }}">
                            <x-filament::icon :icon="$f['on'] ? 'heroicon-m-check-circle' : 'heroicon-m-minus-circle'" class="pp-icon" />
                            {{ $f['label'] }}
                        </li>
                    @endforeach
                </ul>
                <footer>
                    <span>{{ trans_choice($l.'academies', $p['academies'], ['count' => $p['academies']]) }}</span>
                    {{ ($this->editAction)(['plan' => $p['id']]) }}
                </footer>
            </article>
        @endforeach
    </div>
</x-filament-panels::page>
