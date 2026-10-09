@php $l = 'scheduling::teacher.week.'; $w = $this->week; @endphp
<x-filament-panels::page>
    @include('scheduling::filament.family.styles')
    <style>
        .tw-bar { display: flex; align-items: center; justify-content: space-between; gap: .75rem; flex-wrap: wrap; }
        .tw-range { font-weight: 700; }
        .tw-sum { color: var(--gray-500); font-size: .9rem; }
    </style>
    <div class="tw-bar">
        <div>
            <span class="tw-range">{{ $w['from'] }} – {{ $w['to'] }}</span>
            <span class="tw-sum">· {{ trans_choice($l.'count', $w['count'], ['n' => $w['count']]) }} · {{ __($l.'hours', ['h' => round($w['minutes'] / 60, 1)]) }}</span>
        </div>
        <div style="display: flex; gap: .5rem">
            <x-filament::button color="gray" size="sm" wire:click="move(-1)" icon="heroicon-m-chevron-right">{{ __($l.'previous') }}</x-filament::button>
            @if ($this->offset !== 0)
                <x-filament::button color="gray" size="sm" wire:click="$set('offset', 0)">{{ __($l.'this') }}</x-filament::button>
            @endif
            <x-filament::button color="gray" size="sm" wire:click="move(1)" icon="heroicon-m-chevron-left" icon-position="after">{{ __($l.'next') }}</x-filament::button>
        </div>
    </div>
    @forelse ($w['days'] as $day)
        <h2 class="fl-h2">{{ $day['label'] }}</h2>
        <div class="fl-list lms-stagger">
            @foreach ($day['lessons'] as $lesson)
                @include('scheduling::filament.family.lesson-card', ['lesson' => $lesson, 'dispute' => $this->disputeAction])
            @endforeach
        </div>
    @empty
        <div class="fl-empty">{{ __($l.'none') }}</div>
    @endforelse
</x-filament-panels::page>
