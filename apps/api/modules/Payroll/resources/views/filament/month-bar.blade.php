{{-- The month on screen, a step back or forward (never past the current month), and whether it is closed. --}}
@php $l = 'payroll::payroll.'; $state = $this->state; @endphp
<div class="py-base py-bar">
    <div class="py-step">
        <button type="button" wire:click="stepMonth(-1)" title="{{ __($l.'month.prev') }}" aria-label="{{ __($l.'month.prev') }}">
            <x-filament::icon icon="heroicon-m-chevron-left" />
        </button>
        <span class="py-month">{{ $this->monthLabel() }}</span>
        <button type="button" wire:click="stepMonth(1)" @disabled($this->isCurrentMonth()) title="{{ __($l.'month.next') }}" aria-label="{{ __($l.'month.next') }}">
            <x-filament::icon icon="heroicon-m-chevron-right" />
        </button>
    </div>
    @if ($state['closed'])
        <span class="py-pill" data-state="closed">
            <x-filament::icon icon="heroicon-m-lock-closed" />
            {{ isset($showWho) && $showWho ? __($l.'state.closed_by', ['date' => $state['closed_at']->setTimezone(app(\Modules\SharedKernel\Time\ViewerTimezone::class)->effective())->locale(app()->getLocale())->translatedFormat('j M'), 'by' => $state['closed_by']]) : __($l.'state.closed') }}
        </span>
    @else
        <span class="py-pill" data-state="open"><x-filament::icon icon="heroicon-m-lock-open" />{{ __($l.'state.open') }}</span>
    @endif
</div>
