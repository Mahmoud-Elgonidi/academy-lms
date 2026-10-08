@php
    $counts = $this->counts();
    $l = 'classroom::recordings.';
    $settingsUrl = $this->settingsUrl();
    $records = \Modules\Classroom\Models\ClassroomSettings::current()->records();
@endphp
<x-filament-panels::page>
    @if (! $records && $settingsUrl)
        <div class="rec-off">
            <span>{{ __($l.'office.lead_off') }}</span>
            <x-filament::link :href="$settingsUrl" icon="heroicon-o-adjustments-horizontal">{{ __($l.'office.turn_on') }}</x-filament::link>
        </div>
    @endif

    @if (count($this->tabs()) > 1)
        <x-filament::tabs>
            @foreach ($this->tabs() as $t)
                <x-filament::tabs.item :active="$tab === $t" wire:click="$set('tab', '{{ $t }}')" :badge="($counts[$t] ?? 0) ?: null"
                    :badge-color="$t === 'missing' ? 'warning' : 'gray'">
                    {{ __($l.'office.tabs.'.$t) }}
                </x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>
    @endif

    @if ($playing)
        <section class="rec-player" wire:key="player-{{ $playing['id'] }}" aria-label="{{ __($l.'office.player') }}">
            <header>
                <div>
                    <strong>{{ $playing['title'] }}</strong>
                    <small>{{ __($l.'office.logged') }}</small>
                </div>
                <x-filament::icon-button icon="heroicon-o-x-mark" color="gray" wire:click="closePlayer" :label="__($l.'office.close')" />
            </header>
            @if ($playing['media'] === 'audio')
                <audio controls autoplay controlsList="nodownload" src="{{ $playing['url'] }}" oncontextmenu="return false"></audio>
            @else
                <video controls autoplay playsinline controlsList="nodownload noplaybackrate" disablePictureInPicture src="{{ $playing['url'] }}" oncontextmenu="return false"></video>
            @endif
        </section>
    @endif

    {{ $this->table }}

    <style>
        .rec-off { display: flex; flex-wrap: wrap; gap: .5rem 1rem; align-items: center; justify-content: space-between; padding: .75rem 1rem; border-radius: .75rem;
            background: var(--warning-50); color: var(--warning-800); border: 1px solid var(--warning-200); }
        .dark .rec-off { background: color-mix(in srgb, var(--warning-500) 12%, transparent); color: var(--warning-200); border-color: color-mix(in srgb, var(--warning-500) 30%, transparent); }
        .rec-player { border-radius: 1rem; padding: 1rem; background: var(--gray-950); color: #fff; display: grid; gap: .75rem; }
        .rec-player header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; }
        .rec-player header div { display: grid; gap: .15rem; min-width: 0; }
        .rec-player header strong { font-size: .95rem; overflow-wrap: anywhere; }
        .rec-player header small { color: var(--gray-400); font-size: .75rem; }
        .rec-player video { width: 100%; max-height: 70vh; border-radius: .75rem; background: #000; }
        .rec-player audio { width: 100%; }
        @media (prefers-reduced-motion: no-preference) {
            .rec-player { animation: rec-in .25s ease-out backwards; }
            @keyframes rec-in { from { opacity: 0; transform: translateY(-6px); } }
        }
    </style>
</x-filament-panels::page>
