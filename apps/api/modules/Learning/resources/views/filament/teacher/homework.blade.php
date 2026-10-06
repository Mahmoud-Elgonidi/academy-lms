@php $counts = $this->counts(); @endphp
<x-filament-panels::page>
    <x-filament::tabs>
        @foreach (\Modules\Learning\Filament\Teacher\HomeworkToMark::TABS as $t)
            <x-filament::tabs.item :active="$tab === $t" wire:click="$set('tab', '{{ $t }}')" :badge="($counts[$t] ?? 0) ?: null"
                :badge-color="$t === 'submitted' ? 'warning' : 'gray'">
                {{ __('learning::teacher.homework.tabs.'.$t) }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    {{ $this->table }}
</x-filament-panels::page>
