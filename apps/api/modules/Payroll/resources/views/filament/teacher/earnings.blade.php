@php $l = 'payroll::payroll.'; $totals = $this->totals; $unpriced = $this->unpriced; $state = $this->state; $rates = $this->rates; @endphp
<x-filament-panels::page>
    @include('payroll::filament.styles')
    <div class="py-stack">
        @include('payroll::filament.month-bar', ['showWho' => false])

        <div class="py-tiles lms-stagger">
            <div class="py-base py-tile" data-main>
                <small>{{ __($l.'tiles.total') }}</small>
                <span class="py-totals">@forelse ($totals as $t)<strong>{{ $t['total']->format() }}</strong>@empty<strong class="py-muted">—</strong>@endforelse</span>
            </div>
            <div class="py-base py-tile"><small>{{ __($l.'tiles.lessons') }}</small><strong data-count="{{ array_sum(array_column($totals, 'lessons')) }}">{{ array_sum(array_column($totals, 'lessons')) }}</strong></div>
            <div class="py-base py-tile"><small>{{ __($l.'tiles.hours') }}</small><strong>{{ \Modules\Payroll\Filament\PayrollView::hours(array_sum(array_column($totals, 'minutes'))) }}</strong></div>
        </div>

        <div class="py-note" data-tone="{{ $state['closed'] ? 'success' : 'info' }}">
            <span>{{ $state['closed'] ? __($l.'mine.closed_note', ['date' => $state['closed_at']->setTimezone(app(\Modules\SharedKernel\Time\ViewerTimezone::class)->effective())->locale(app()->getLocale())->translatedFormat('j F')]) : __($l.'mine.open_note') }}</span>
        </div>
        @if ($unpriced > 0)
            <div class="py-note" data-tone="warning"><span>{{ trans_choice($l.'mine.unpriced', $unpriced, ['count' => $unpriced]) }}</span></div>
        @endif

        @include('payroll::filament.lines', ['canReverse' => false])

        <section class="py-base py-box">
            <h3>{{ __($l.'mine.rates') }}</h3>
            @if ($rates === [])
                <p class="py-empty">{{ __($l.'mine.no_card') }}</p>
            @else
                <div class="py-rates">
                    @foreach ($rates as $rate)<div><span>{{ $rate['what'] }}</span><span>{{ $rate['price'] }}</span></div>@endforeach
                </div>
            @endif
        </section>
    </div>
</x-filament-panels::page>
