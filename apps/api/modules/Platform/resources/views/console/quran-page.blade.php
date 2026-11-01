@php
    $l = 'platform::settings.quran.';
    $c = 'platform::console.quran.';
    $q = $this->quran;
    $a = $this->academies;
@endphp
<x-filament-panels::page>
    @include('platform::console.styles')
    <style>
        .pq-text { padding: 1.1rem; display: flex; flex-direction: column; gap: .8rem; }
        .pq-head { display: flex; align-items: flex-start; justify-content: space-between; gap: .75rem; flex-wrap: wrap; }
        .pq-head h2 { margin: 0; font-size: 1rem; font-weight: 700; }
        .pq-head p { margin: .2rem 0 0; font-size: .84rem; color: var(--gray-500); max-inline-size: 46rem; }
        .pq-state { display: inline-flex; align-items: center; gap: .35rem; font-size: .75rem; font-weight: 700; padding: .2rem .65rem; border-radius: 999px; background: var(--success-50); color: var(--success-700); }
        .pq-state[data-off] { background: var(--warning-50); color: var(--warning-700); }
        .pq-hash { font-family: ui-monospace, monospace; font-size: .75rem; word-break: break-all; }
        .pq-list { display: flex; flex-direction: column; }
        .pq-row { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .7rem 1rem; border-top: 1px solid var(--gray-100); color: inherit; }
        .pq-row:first-child { border-top: 0; }
        .pq-row small { color: var(--gray-500); font-size: .78rem; }
        .pq-how { font-size: .72rem; font-weight: 600; padding: .12rem .55rem; border-radius: 999px; background: var(--primary-50); color: var(--primary-700); white-space: nowrap; }
        .pq-how[data-how="academy"] { background: var(--info-50); color: var(--info-700); }
        .pq-how[data-how="no_plan"] { background: var(--gray-100); color: var(--gray-700); }
        .dark .pq-state { background: color-mix(in oklab, var(--success-500) 15%, transparent); color: var(--success-300); }
        .dark .pq-state[data-off] { background: color-mix(in oklab, var(--warning-500) 15%, transparent); color: var(--warning-300); }
        .dark .pq-row { border-color: var(--gray-800); }
        .dark .pq-how { background: color-mix(in oklab, var(--primary-500) 16%, transparent); color: var(--primary-300); }
        .dark .pq-how[data-how="academy"] { background: color-mix(in oklab, var(--info-500) 16%, transparent); color: var(--info-300); }
        .dark .pq-how[data-how="no_plan"] { background: var(--gray-800); color: var(--gray-300); }
    </style>
    <div class="pc-stack lms-stagger">
        <div class="pc-tiles">
            <div class="pc-base pc-tile" data-main><small>{{ __($c.'tiles.on') }}</small><strong data-count="{{ count($a['on']) }}">{{ count($a['on']) }}</strong></div>
            <div class="pc-base pc-tile"><small>{{ __($c.'tiles.off') }}</small><strong data-count="{{ $a['off'] }}">{{ $a['off'] }}</strong></div>
            <div class="pc-base pc-tile"><small>{{ __($l.'verses') }}</small><strong>{{ $q['available'] ? number_format((int) $q['verses']) : '—' }}</strong></div>
        </div>

        <section class="pc-base pq-text" aria-labelledby="pq-text">
            <div class="pq-head">
                <div>
                    <h2 id="pq-text">{{ __($l.'title') }}</h2>
                    <p>{{ __($l.'about') }}</p>
                </div>
                <span class="pq-state" @unless ($q['available']) data-off @endunless>{{ $q['available'] ? __($c.'ready') : __($c.'missing') }}</span>
            </div>
            @if ($q['available'])
                <dl class="pc-dl">
                    <dt>{{ __($l.'where') }}</dt><dd>{{ __($l.'where_'.$q['where']) }}{{ $q['fetched_at'] ? ' · '.$q['fetched_at'] : '' }}</dd>
                    <dt>{{ __($l.'copy') }}</dt><dd dir="ltr">{{ $q['notice'] }}</dd>
                    <dt>{{ __($l.'verses') }}</dt><dd>{{ number_format((int) $q['verses']) }}</dd>
                    @if ($q['text_sha256'])<dt>SHA-256</dt><dd dir="ltr" class="pq-hash">{{ $q['text_sha256'] }}</dd>@endif
                </dl>
            @else
                <p class="pc-note" style="margin: 0">{{ __($l.'missing') }}</p>
            @endif
            @if (\Modules\Platform\Filament\Console\Pages\QuranConsole::isOwner())
                <div>{{ $this->fetchQuranAction }}</div>
            @else
                <small style="color: var(--gray-500)">{{ __($c.'owners') }}</small>
            @endif
        </section>

        <section class="pc-base" aria-labelledby="pq-academies">
            <div class="pq-head" style="padding: 1rem 1rem .4rem">
                <div>
                    <h2 id="pq-academies">{{ __($c.'academies') }}</h2>
                    <p>{{ __($c.'academies_hint') }}</p>
                </div>
                <x-filament::link :href="\Modules\Platform\Filament\Console\Pages\Plans::getUrl()" wire:navigate>{{ __($c.'plans') }}</x-filament::link>
            </div>
            @if ($a['on'] === [])
                <div class="pc-empty">{{ __($c.'none') }}</div>
            @else
                <div class="pq-list">
                    @foreach ($a['on'] as $row)
                        <a class="pq-row" href="{{ $row['url'] }}" wire:navigate wire:key="pq-{{ $row['id'] }}">
                            <div><strong>{{ $row['name'] }}</strong><br><small dir="ltr">{{ $row['slug'] }}</small></div>
                            <span class="pq-how" data-how="{{ $row['how'] }}">{{ __($c.'how.'.$row['how'], ['plan' => $row['plan']]) }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</x-filament-panels::page>
