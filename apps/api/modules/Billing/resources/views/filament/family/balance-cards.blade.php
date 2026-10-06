{{-- Lessons left per course, and what is owed. $balances, $owed. --}}
@php $l = 'billing::family.'; @endphp
<div class="bf-grid lms-stagger">
    @foreach ($balances as $b)
        <div class="bf-card" @if ($b['sessions'] !== null && $b['sessions'] <= 1) data-low @endif>
            <small>{{ $b['subject'] }}</small>
            <span class="bf-big">{{ $b['sessions'] !== null ? trans_choice($l.'sessions_left', $b['sessions'], ['n' => $b['sessions']]) : __($l.'minutes_left', ['n' => $b['minutes']]) }}</span>
            @if ($b['sessions'] !== null && $b['sessions'] <= 1)
                <small>{{ __($l.'renew_soon') }}</small>
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
