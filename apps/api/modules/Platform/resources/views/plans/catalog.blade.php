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

    {{-- The extra packs an academy may ask for in a month, over its plan: turned on from console › Plan requests. --}}
    @php $packs = $this->packs; $t = 'platform::topups.packs.'; @endphp
    <section class="pc-base pc-box pp-packs">
        <h3><span>{{ __($t.'title') }}</span></h3>
        <p class="pp-sub">{{ __($t.'subtitle') }}</p>
        <div class="pp-pack-groups">
            @foreach ($packs as $limit => $list)
                <div class="pp-pack-group" wire:key="packs-{{ $limit }}">
                    <header><strong>{{ __('shared::plans.limits.'.$limit) }}</strong>{{ ($this->newPackAction)(['limit' => $limit]) }}</header>
                    <ul>
                        @forelse ($list as $p)
                            <li @if ($p['archived']) data-archived @endif wire:key="pack-{{ $p['id'] }}">
                                <span><strong>{{ $p['amount'] }}</strong> · {{ $p['name'] }}@if ($p['price']) · {{ $p['price'] }}@endif @if ($p['archived'])<span class="pp-tag">{{ __('platform::plans.catalog.archived') }}</span>@endif</span>
                                {{ ($this->editPackAction)(['pack' => $p['id']]) }}
                            </li>
                        @empty
                            <li><small>{{ __($t.'none') }}</small></li>
                        @endforelse
                    </ul>
                </div>
            @endforeach
        </div>
    </section>
    <style>
        .pp-packs { margin-top: 1rem; }
        .pp-pack-groups { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 18rem), 1fr)); gap: .9rem; }
        .pp-pack-group header { display: flex; justify-content: space-between; align-items: center; gap: .5rem; font-size: .9rem; padding-bottom: .4rem; }
        .pp-pack-group ul { list-style: none; margin: 0; padding: 0; }
        .pp-pack-group li { display: flex; justify-content: space-between; align-items: center; gap: .5rem; padding: .45rem 0; border-top: 1px solid var(--gray-100); font-size: .86rem; }
        .pp-pack-group li[data-archived] { opacity: .6; }
        .dark .pp-pack-group li { border-color: var(--gray-800); }
    </style>
</x-filament-panels::page>
