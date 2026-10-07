@php $l = 'payroll::payroll.'; $totals = $this->totals; $unpriced = $this->unpriced; @endphp
<x-filament-panels::page>
    @include('payroll::filament.styles')
    <div class="py-stack">
        @include('payroll::filament.month-bar', ['showWho' => true])

        <div class="py-tiles lms-stagger">
            <div class="py-base py-tile" data-main>
                <small>{{ __($l.'tiles.total') }}</small>
                <span class="py-totals">@forelse ($totals as $t)<strong>{{ $t['total']->format() }}</strong>@empty<strong class="py-muted">—</strong>@endforelse</span>
            </div>
            <div class="py-base py-tile"><small>{{ __($l.'tiles.lessons') }}</small><strong data-count="{{ array_sum(array_column($totals, 'lessons')) }}">{{ array_sum(array_column($totals, 'lessons')) }}</strong></div>
            <div class="py-base py-tile"><small>{{ __($l.'tiles.hours') }}</small><strong>{{ \Modules\Payroll\Filament\PayrollView::hours(array_sum(array_column($totals, 'minutes'))) }}</strong></div>
            <div class="py-base py-tile"><small>{{ __($l.'columns.bonuses') }}</small>@forelse ($totals as $t)<strong @class(['py-pos' => ! $t['bonuses']->isZero()])>{{ $t['bonuses']->format() }}</strong>@empty<strong class="py-muted">—</strong>@endforelse</div>
            <div class="py-base py-tile"><small>{{ __($l.'columns.deductions') }}</small>@forelse ($totals as $t)<strong @class(['py-neg' => ! $t['deductions']->isZero()])>{{ $t['deductions']->format() }}</strong>@empty<strong class="py-muted">—</strong>@endforelse</div>
        </div>

        @if ($unpriced > 0)
            <div class="py-note" data-tone="warning">
                <span>{{ trans_choice($l.'unpriced.text', $unpriced, ['count' => $unpriced]) }}</span>
                <x-filament::button size="sm" color="gray" tag="a" :href="\Modules\Payroll\Filament\Resources\RateCards\RateCardResource::getUrl()">{{ __($l.'unpriced.rates') }}</x-filament::button>
            </div>
        @endif

        @include('payroll::filament.lines', ['canReverse' => true])
    </div>
</x-filament-panels::page>
