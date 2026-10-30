{{-- Why nobody was found, and what would help (Find a teacher). --}}
@php $reason ??= 'no_time'; @endphp
<div class="ft-card ft-reason" data-tone="{{ in_array($reason, ['no_teachers', 'no_trial_teachers'], true) ? 'danger' : 'muted' }}" role="status" wire:key="reason-{{ $reason }}">
    <x-filament::icon :icon="match ($reason) { 'filters' => 'heroicon-o-funnel', 'times' => 'heroicon-o-clock', default => 'heroicon-o-user-minus' }" style="width: 1.75rem; height: 1.75rem" />
    <strong>{{ __('scheduling::find.reasons.'.$reason.'.title') }}</strong>
    <p class="ft-muted">{{ __('scheduling::find.reasons.'.$reason.'.hint') }}</p>
    <div class="ft-reason-actions">
        @switch($reason)
            @case('filters')
                <x-filament::button wire:click="clearFilters" icon="heroicon-m-x-mark" size="sm">{{ __('scheduling::find.reasons.clear_filters') }}</x-filament::button>
                @break
            @case('no_time')
                @if (! $this->isTrial() && (int) ($this->data['min_coverage'] ?? 8) === 8)
                    <x-filament::button wire:click="relax('coverage')" size="sm">{{ __('scheduling::find.reasons.allow_one') }}</x-filament::button>
                @endif
                @foreach ([30, 45, 60] as $minutes)
                    @if ((int) ($this->data['duration'] ?? 0) !== $minutes)
                        <x-filament::button wire:click="relax('duration', {{ $minutes }})" color="gray" outlined size="sm">{{ __('scheduling::find.reasons.try_length', ['minutes' => $minutes]) }}</x-filament::button>
                    @endif
                @endforeach
                @break
            @case('times')
                @if ($this->timesTab !== 'free')
                    <x-filament::button wire:click="showTimes('free')" icon="heroicon-m-sparkles" size="sm">{{ __('scheduling::find.reasons.see_free') }}</x-filament::button>
                @endif
                @break
        @endswitch
    </div>
</div>
