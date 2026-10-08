@php $counts = $this->counts(); @endphp
<x-filament-panels::page>
    <x-filament::tabs>
        @foreach (\Modules\People\Queries\ConsentBoard::TABS as $t)
            <x-filament::tabs.item :active="$tab === $t" wire:click="$set('tab', '{{ $t }}')" :badge="($counts[$t] ?? 0) ?: null"
                :badge-color="$t === 'granted' ? 'gray' : ($t === 'withdrawn' ? 'danger' : 'warning')">
                {{ __('people::consent.office.tabs.'.$t) }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    {{ $this->table }}
</x-filament-panels::page>
