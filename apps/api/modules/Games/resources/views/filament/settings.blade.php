@php $l = 'games::games.settings.'; $stats = $this->stats; @endphp
<x-filament-panels::page>
    <style>
        .gs-set { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1rem; align-items: start; }
        @media (min-width: 1024px) { .gs-set { grid-template-columns: minmax(0, 1fr) minmax(0, 1.4fr); } }
        .gs-box { background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl, .75rem); padding: 1rem; display: flex; flex-direction: column; gap: .8rem; }
        .gs-box h3 { font-weight: 700; }
        .gs-box label { display: flex; flex-direction: column; gap: .3rem; font-size: .9rem; }
        .gs-box label.gs-row { flex-direction: row; align-items: center; gap: .5rem; }
        .gs-box input[type="number"] { border: 1px solid var(--gray-300); border-radius: .5rem; padding: .4rem .6rem; max-width: 10rem; background: white; }
        .gs-box small { color: var(--gray-500); font-size: .78rem; }
        .gs-tiles { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .5rem; }
        .gs-tiles div { display: flex; flex-direction: column; padding: .6rem .7rem; border-radius: .6rem; background: var(--gray-50); }
        .gs-tiles strong { font-size: 1.3rem; font-variant-numeric: tabular-nums; }
        .gs-tiles small { color: var(--gray-500); }
        .gs-cat { display: flex; flex-direction: column; gap: .5rem; }
        .gs-cat li { display: flex; flex-direction: column; gap: .2rem; padding: .6rem .75rem; border-radius: .6rem; background: var(--gray-50); }
        .gs-cat small { color: var(--gray-600); }
        .gs-tags { display: flex; flex-wrap: wrap; gap: .3rem; }
        .gs-tags span { font-size: .7rem; font-weight: 600; padding: .1rem .5rem; border-radius: 999px; background: white; border: 1px solid var(--gray-200); }
        .gs-tags span[data-kind] { color: var(--primary-700); border-color: var(--primary-200); }
        .gs-hint { padding: .6rem .8rem; border-radius: .6rem; background: var(--info-50, var(--primary-50)); color: var(--info-800, var(--primary-800)); font-size: .85rem; }
        .dark .gs-box { background: var(--gray-900); border-color: var(--gray-800); }
        .dark .gs-tiles div, .dark .gs-cat li { background: var(--gray-800); }
    </style>
    <div class="gs-set lms-stagger">
        <section class="gs-box">
            <form wire:submit="save" class="gs-box" style="border: 0; padding: 0">
                <label class="gs-row"><input type="checkbox" wire:model="enabled"> {{ __($l.'enabled') }}</label>
                <label>{{ __($l.'minutes') }}
                    <input type="number" min="5" max="600" step="5" wire:model="minutes">
                    <small>{{ __($l.'minutes_hint') }}</small>
                </label>
                @error('daily_minutes')<small style="color: var(--danger-600)">{{ $message }}</small>@enderror
                <div><x-filament::button type="submit">{{ __('games::games.limits.save') }}</x-filament::button></div>
            </form>
            <h3>{{ __($l.'stats') }}</h3>
            <div class="gs-tiles">
                <div><strong data-count="{{ $stats['rounds'] }}">{{ $stats['rounds'] }}</strong><small>{{ __($l.'rounds') }}</small></div>
                <div><strong data-count="{{ $stats['players'] }}">{{ $stats['players'] }}</strong><small>{{ __($l.'players') }}</small></div>
                <div><strong data-count="{{ $stats['creatures'] }}">{{ $stats['creatures'] }}</strong><small>{{ __($l.'creatures') }}</small></div>
            </div>
            <p class="gs-hint">{{ __($l.'practice_hint') }}</p>
        </section>
        <section class="gs-box">
            <h3>{{ __($l.'catalog') }}</h3>
            <ul class="gs-cat">
                @foreach ($this->catalog as $g)
                    <li>
                        <strong>{{ $g['title'] }} <small>· {{ $g['island'] }}</small></strong>
                        <small>{{ $g['summary'] }}</small>
                        <div class="gs-tags">
                            @foreach ($g['kinds'] as $k)<span data-kind>{{ $k }}</span>@endforeach
                            @foreach ($g['stages'] as $s)<span>{{ $s }}</span>@endforeach
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>
</x-filament-panels::page>
