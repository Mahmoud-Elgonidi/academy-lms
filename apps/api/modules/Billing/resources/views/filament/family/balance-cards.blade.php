{{-- Lessons left per course, and what is owed. $balances, $owed; $extras: offer extra lessons (the billing page, a Livewire component with extraLessonsAction). --}}
@php $l = 'billing::family.'; @endphp
<div class="bf-grid lms-stagger">
    @foreach ($balances as $b)
        <div class="bf-card" @if ($b['sessions'] !== null && $b['sessions'] <= 1) data-low @endif>
            <small>{{ $b['subject'] }}@if ($b['shared'] ?? false) · {{ __($l.'shared') }}@endif</small>
            <span class="bf-big">{{ $b['sessions'] !== null ? trans_choice($l.'sessions_left', $b['sessions'], ['n' => $b['sessions']]) : __($l.'minutes_left', ['n' => $b['minutes']]) }}</span>
            @if ($b['sessions'] !== null && $b['sessions'] <= 1)
                <small>{{ __($l.'renew_soon') }}</small>
            @endif
            @if (($extras ?? false) && ($b['extra'] ?? null))
                <div class="bf-extra">
                    @if ($b['extra']['waiting'])
                        <small>{{ __('billing::discounts.family.extra_waiting', ['number' => $b['extra']['waiting']['number']]) }}</small>
                    @else
                        <small>{{ __('billing::discounts.family.extra_hint', ['price' => $b['extra']['price'], 'minutes' => $b['extra']['minutes']]) }}</small>
                        {{ ($this->extraLessonsAction)(['enrollment' => $b['enrollment_id']]) }}
                    @endif
                </div>
            @endif
        </div>
    @endforeach
    @if ($owed)
        <a class="bf-card lms-lift" data-tone="owed" href="{{ \Modules\Billing\Filament\Family\FamilyBilling::getUrl() }}">
            <small>{{ __($l.'owed') }}</small>
            <span class="bf-big">{{ $owed }}</span>
        </a>
    @endif
</div>
