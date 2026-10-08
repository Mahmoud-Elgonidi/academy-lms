@php $l = 'platform::console.academies.'; $tiles = $this->tiles; $academies = $this->academies; @endphp
<x-filament-panels::page>
    @include('platform::console.styles')
    @include('platform::plans.styles')
    <div class="pc-stack">
        <div class="pc-tiles lms-stagger">
            <div class="pc-base pc-tile" data-main>
                <small>{{ __($l.'tiles.academies') }}</small>
                <strong data-count="{{ $tiles['active'] }}">{{ $tiles['active'] }}</strong>
                @if ($tiles['suspended'] > 0)<em>{{ __($l.'tiles.suspended', ['count' => $tiles['suspended']]) }}</em>@endif
            </div>
            <div class="pc-base pc-tile"><small>{{ __($l.'tiles.students') }}</small><strong data-count="{{ $tiles['students'] }}">{{ $tiles['students'] }}</strong></div>
            <div class="pc-base pc-tile"><small>{{ __($l.'tiles.lessons') }}</small><strong data-count="{{ $tiles['lessons'] }}">{{ $tiles['lessons'] }}</strong></div>
            <div class="pc-base pc-tile"><small>{{ __($l.'tiles.hours') }}</small><strong>{{ $tiles['hours'] }}</strong></div>
        </div>

        <div class="pc-bar">
            <div class="pc-search">
                <x-filament::input.wrapper prefix-icon="heroicon-o-magnifying-glass">
                    <x-filament::input type="search" wire:model.live.debounce.300ms="q" :placeholder="__($l.'search')" />
                </x-filament::input.wrapper>
            </div>
            <div class="pc-chips" role="group">
                @foreach (['all', 'active', 'suspended'] as $key)
                    <button type="button" wire:click="filter('{{ $key }}')" wire:key="f-{{ $key }}" aria-pressed="{{ $status === $key ? 'true' : 'false' }}">{{ __($l.'filter.'.$key) }}</button>
                @endforeach
            </div>
        </div>

        @if ($academies === [])
            <div class="pc-base pc-empty">{{ __($l.'empty') }}</div>
        @else
            <div class="pc-grid lms-stagger">
                @foreach ($academies as $a)
                    <a href="{{ $a['url'] }}" wire:navigate wire:key="a-{{ $a['id'] }}" class="pc-base pc-card lms-lift">
                        <header>
                            <div>
                                <h3>{{ $a['name'] }}</h3>
                                <small>{{ $a['host'] }}</small>
                            </div>
                            <span class="pc-status" data-state="{{ $a['active'] ? 'active' : 'suspended' }}">{{ __('platform::console.status.'.($a['active'] ? 'active' : 'suspended')) }}</span>
                        </header>
                        <span class="pc-people">{{ __($l.'people', ['teachers' => $a['usage']['teachers'], 'students' => $a['usage']['students'], 'guardians' => $a['usage']['guardians']]) }}</span>
                        @if ($a['plan'])
                            <span class="pp-tags pp-tags-start">
                                <span class="pp-tag" data-tone="brand">{{ $a['plan'] }}</span>
                                @if ($a['plan_state'] !== 'ok')<span class="pp-tag" data-tone="{{ $a['plan_state'] === 'full' ? 'danger' : 'warn' }}">{{ __('platform::plans.summary.state.'.$a['plan_state']) }}</span>@endif
                            </span>
                        @endif
                        <div class="pc-figures">
                            <div><small>{{ __('platform::console.overview.tiles.lessons') }}</small><strong>{{ $a['usage']['lessons'] }}</strong></div>
                            <div><small>{{ __('platform::console.overview.tiles.hours') }}</small><strong>{{ $a['hours'] }}</strong></div>
                            <div><small>{{ __($l.'received') }}</small>@forelse ($a['received'] as $m)<strong>{{ $m }}</strong>@empty<strong>—</strong>@endforelse</div>
                        </div>
                        <div class="pc-foot">
                            <span>{{ $a['last'] ? __($l.'last_lesson', ['when' => $a['last']]) : __($l.'no_lessons') }}</span>
                            <span>{{ __($l.'since', ['date' => $a['since']]) }}</span>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
