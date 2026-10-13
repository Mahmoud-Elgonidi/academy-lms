@php $l = 'identity::team.'; $tiles = $this->tiles(); @endphp
<x-filament-panels::page>
    {{-- The academy's colours through Filament's variables; the tiles come in one by one and count up (shared motion layer). --}}
    <style>
        .tm-tiles { display: grid; grid-template-columns: repeat(auto-fit, minmax(10.5rem, 1fr)); gap: .6rem; }
        .tm-tile { display: flex; flex-direction: column; gap: .15rem; padding: .8rem .95rem; background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl, .75rem); border-inline-start: 3px solid var(--primary-500); }
        .tm-tile small { color: var(--gray-500); font-size: .78rem; }
        .tm-tile strong { font-size: 1.45rem; line-height: 1.2; font-variant-numeric: tabular-nums; color: var(--gray-950); }
        .tm-tile[data-key="invited"] { border-inline-start-color: var(--warning-500); }
        .tm-tile[data-key="two_step"] { border-inline-start-color: var(--warning-500); }
        .tm-tile[data-key="two_step"][data-some] strong { color: var(--warning-700); }
        .tm-tile[data-key="off"] { border-inline-start-color: var(--gray-300); }
        .dark .tm-tile { background: var(--gray-900); border-color: var(--gray-800); }
        .dark .tm-tile strong { color: white; }
        .dark .tm-tile[data-key="two_step"][data-some] strong { color: var(--warning-400); }
        .tm-stack { display: flex; flex-direction: column; gap: 1rem; }
    </style>
    <div class="tm-stack">
        <div class="tm-tiles lms-stagger">
            @foreach ($tiles as $key => $count)
                <div class="tm-tile" data-key="{{ $key }}"@if ($count > 0) data-some @endif wire:key="tile-{{ $key }}">
                    <small>{{ __($l.'tiles.'.$key) }}</small>
                    <strong data-count="{{ $count }}">{{ $count }}</strong>
                </div>
            @endforeach
        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
