@php $l = 'identity::onboarding.'; @endphp
<x-filament-panels::page>
    @if ($done)
        <style>
            .na-done { background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl, .75rem); padding: 1.2rem; display: flex; flex-direction: column; gap: .8rem; max-width: 46rem; }
            .na-done h3 { font-size: 1.1rem; font-weight: 700; }
            .na-done p { margin: 0; color: var(--gray-600); font-size: .9rem; }
            .na-link { display: flex; gap: .5rem; align-items: stretch; flex-wrap: wrap; }
            .na-link code { flex: 1 1 18rem; min-width: 0; padding: .6rem .75rem; border-radius: .6rem; background: var(--gray-50); border: 1px solid var(--gray-200); font-size: .78rem; direction: ltr; overflow-wrap: anywhere; }
            .na-actions { display: flex; flex-wrap: wrap; gap: .5rem; }
            .dark .na-done { background: var(--gray-900); border-color: var(--gray-800); }
            .dark .na-done p { color: var(--gray-400); }
            .dark .na-link code { background: rgb(255 255 255 / .04); border-color: var(--gray-800); }
        </style>
        <section class="na-done lms-stagger" x-data="{ copied: false }">
            <h3>{{ __($l.'done.title', ['name' => $done['name']]) }}</h3>
            <p>{{ __($l.'done.body', ['days' => $this->linkDays()]) }}</p>
            <div class="na-link">
                <code x-ref="link">{{ $done['link'] }}</code>
                <x-filament::button color="gray" icon="heroicon-o-clipboard-document" x-on:click="navigator.clipboard.writeText($refs.link.textContent.trim()); copied = true; setTimeout(() => copied = false, 2000)">
                    <span x-show="! copied">{{ __($l.'done.copy') }}</span><span x-show="copied" x-cloak>{{ __($l.'done.copied') }}</span>
                </x-filament::button>
            </div>
            <div class="na-actions">
                <x-filament::button tag="a" :href="$done['page']" icon="heroicon-o-building-library">{{ __($l.'done.open') }}</x-filament::button>
                <x-filament::button color="gray" wire:click="another" wire:key="another">{{ __($l.'done.another') }}</x-filament::button>
            </div>
        </section>
    @else
        <form wire:submit="create" class="flex flex-col gap-6">
            {{ $this->form }}
            <div><x-filament::button type="submit" icon="heroicon-o-plus">{{ __($l.'create') }}</x-filament::button></div>
        </form>
    @endif
</x-filament-panels::page>
