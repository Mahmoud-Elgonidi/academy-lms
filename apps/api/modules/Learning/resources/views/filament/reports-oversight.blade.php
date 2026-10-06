@php $counts = $this->counts(); @endphp
<x-filament-panels::page>
    <x-filament::tabs>
        @foreach (\Modules\Learning\Filament\Pages\ReportsOversight::TABS as $t)
            <x-filament::tabs.item :active="$tab === $t" wire:click="$set('tab', '{{ $t }}')" :badge="($counts[$t] ?? 0) ?: null"
                :badge-color="$t === 'overdue' ? 'danger' : ($t === 'submitted' ? 'warning' : 'gray')">
                {{ __('learning::reports.oversight.tabs.'.$t) }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    {{ $this->table }}
</x-filament-panels::page>
