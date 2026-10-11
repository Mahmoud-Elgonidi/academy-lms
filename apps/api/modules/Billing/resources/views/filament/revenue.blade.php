@php
    use Modules\Billing\Support\RevenuePeriod;
    use Modules\SharedKernel\Money\Money;

    $l = 'billing::revenue.';
    $period = $this->period;
    $code = $this->code();
    $s = $this->summary;
    $today = \Carbon\CarbonImmutable::now()->setTimezone($period->timezone)->toDateString();
@endphp
<x-filament-panels::page>
    @include('billing::filament.revenue-styles')
    <div class="rv-stack">
        <div data-live-channel="{{ $this->liveChannel() }}" hidden></div>

        {{-- The period (presets or two days), the currency, and what it is compared with. --}}
        <div class="rv-base rv-bar">
            <div class="rv-chips" role="group" aria-label="{{ __($l.'title') }}">
                @foreach (RevenuePeriod::PRESETS as $preset)
                    <button type="button" wire:click="setRange('{{ $preset }}')" @class(['rv-chip', 'is-on' => $period->preset === $preset]) aria-pressed="{{ $period->preset === $preset ? 'true' : 'false' }}">
                        {{ __($l.'presets.'.$preset) }}
                    </button>
                @endforeach
            </div>
            @if ($period->preset === 'custom')
                <div class="rv-dates">
                    <label><span>{{ __($l.'range.from') }}</span><input type="date" wire:model.live="from" max="{{ $today }}"></label>
                    <label><span>{{ __($l.'range.to') }}</span><input type="date" wire:model.live="to" max="{{ $today }}"></label>
                </div>
            @endif
            <div class="rv-when">
                <strong>{{ $this->periodLabel($period) }}</strong>
                <small>{{ __($l.'range.compared', ['period' => $this->periodLabel($period->previous())]) }}</small>
            </div>
            @if (count($this->currencies) > 1)
                <div class="rv-chips" role="group" aria-label="{{ __($l.'currency') }}">
                    @foreach ($this->currencies as $c)
                        <button type="button" wire:click="setCurrency('{{ $c }}')" @class(['rv-chip', 'is-on' => $code === $c]) aria-pressed="{{ $code === $c ? 'true' : 'false' }}">{{ $c }}</button>
                    @endforeach
                </div>
            @endif
        </div>

        @if ($code === null || $s === null)
            <div class="rv-base rv-empty-big">
                <strong>{{ __($l.'empty.title') }}</strong>
                <p>{{ __($l.'empty.hint') }}</p>
            </div>
        @else
            @php
                $forecast = $this->forecast;
                $bars = $this->bars;
                $max = max(1, ...array_map(fn ($b) => max($b['collected'], $b['invoiced']), $bars ?: [['collected' => 0, 'invoiced' => 0]]));
                $quiet = array_sum(array_column($bars, 'collected')) + array_sum(array_column($bars, 'invoiced')) === 0;
                $every = max(1, (int) ceil(count($bars) / 7));
                $by = $this->breakdown;
            @endphp

            {{-- The tiles. --}}
            <div class="rv-tiles lms-stagger">
                @php $change = $this->change($s['collected'], $s['collected_before']); @endphp
                <div class="rv-base rv-tile" data-main>
                    <small>{{ __($l.'tiles.collected') }}</small>
                    <strong>{{ $s['collected']->format() }}</strong>
                    @if ($change)<span class="rv-change" data-tone="{{ $change['tone'] }}">{{ $change['text'] }}</span>@endif
                    <p>{{ trans_choice($l.'tiles.collected_hint', $s['payments'], ['n' => $s['payments']]) }}@if ($s['payers'] > 0) {{ trans_choice($l.'tiles.payers', $s['payers'], ['n' => $s['payers']]) }}@endif</p>
                    @if (! $s['tax']->isZero())<p>{{ __($l.'tiles.net', ['net' => $s['net']->format(), 'tax' => $s['tax']->format()]) }}</p>@endif
                </div>
                @php $change = $this->change($s['invoiced'], $s['invoiced_before']); @endphp
                <div class="rv-base rv-tile">
                    <small>{{ __($l.'tiles.invoiced') }}</small>
                    <strong>{{ $s['invoiced']->format() }}</strong>
                    @if ($change)<span class="rv-change" data-tone="{{ $change['tone'] }}">{{ $change['text'] }}</span>@endif
                    <p>{{ trans_choice($l.'tiles.invoices', $s['invoices'], ['n' => $s['invoices']]) }}</p>
                </div>
                <div class="rv-base rv-tile" @if ($s['overdue_count'] > 0) data-tone="late" @endif>
                    <small>{{ __($l.'tiles.outstanding') }}</small>
                    <strong>{{ $s['outstanding']->format() }}</strong>
                    <p>{{ trans_choice($l.'tiles.outstanding_hint', $s['outstanding_count'], ['n' => $s['outstanding_count']]) }}</p>
                    @if ($s['overdue_count'] > 0)
                        <a class="rv-late" href="{{ $this->invoicesUrl('overdue') }}" wire:navigate>{{ __($l.'tiles.overdue', ['amount' => $s['overdue']->format()]) }}</a>
                    @endif
                </div>
                <div class="rv-base rv-tile">
                    <small>{{ __($l.'tiles.discounts') }}</small>
                    <strong>{{ $s['discounts']->format() }}</strong>
                    <p>{{ __($l.'tiles.discounts_hint') }}</p>
                </div>
                <div class="rv-base rv-tile">
                    <small>{{ __($l.'tiles.forecast', ['days' => $forecast['days']]) }}</small>
                    <strong>{{ $forecast['total']->format() }}</strong>
                    <p>{{ trans_choice($l.'tiles.forecast_hint', $forecast['count'], ['n' => $forecast['count']]) }}</p>
                </div>
            </div>

            {{-- Collected and invoiced, bar by bar (time runs with the reading direction). --}}
            <section class="rv-base rv-box">
                <header class="rv-head">
                    <h3>{{ __($l.'chart.title') }}</h3>
                    <div class="rv-legend">
                        <span data-k="collected">{{ __($l.'chart.collected') }}</span>
                        <span data-k="invoiced">{{ __($l.'chart.invoiced') }}</span>
                        <small>{{ __($l.'chart.steps.'.$period->step()) }}</small>
                    </div>
                </header>
                @if ($quiet)
                    <p class="rv-muted">{{ __($l.'chart.empty') }}</p>
                @else
                    <div class="rv-chart" aria-hidden="true">
                        <div class="rv-axis"><span>{{ Money::of($max, $code)->format() }}</span><span>{{ Money::of(intdiv($max, 2), $code)->format() }}</span><span>0</span></div>
                        <div class="rv-plot">
                            @foreach ($bars as $i => $bar)
                                <div class="rv-col" style="--rv-i: {{ $i }}" wire:key="bar-{{ $period->preset }}-{{ $bar['first']->toDateString() }}" title="{{ __($l.'chart.bar', ['label' => $bar['label'], 'collected' => $bar['collected_text'], 'invoiced' => $bar['invoiced_text']]) }}">
                                    <div class="rv-pair">
                                        <span class="rv-stick" data-k="invoiced" style="height: {{ $bar['invoiced'] > 0 ? max(1.5, round($bar['invoiced'] * 100 / $max, 2)) : 0 }}%"></span>
                                        <span class="rv-stick" data-k="collected" style="height: {{ $bar['collected'] > 0 ? max(1.5, round($bar['collected'] * 100 / $max, 2)) : 0 }}%"></span>
                                    </div>
                                    <small @class(['rv-x', 'is-shown' => $i % $every === 0])>{{ $period->step() === 'week' ? $bar['first']->locale(app()->getLocale())->translatedFormat('j M') : $bar['label'] }}</small>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="rv-sr">
                        <table>
                            <caption>{{ __($l.'chart.title') }}</caption>
                            <tbody>
                                @foreach ($bars as $bar)
                                    <tr><th scope="row">{{ $bar['label'] }}</th><td>{{ __($l.'chart.collected') }}: {{ $bar['collected_text'] }}</td><td>{{ __($l.'chart.invoiced') }}: {{ $bar['invoiced_text'] }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            {{-- Where the money came from. --}}
            <div class="rv-grid lms-stagger">
                @foreach (['methods', 'kinds', 'subjects', 'plans'] as $group)
                    @php $rows = $by[$group]; $total = max(1, array_sum(array_column($rows, 'minor'))); @endphp
                    <section class="rv-base rv-box" wire:key="by-{{ $group }}">
                        <h3>{{ __($l.'by.'.$group) }}</h3>
                        @forelse ($rows as $row)
                            @php $pct = (int) round($row['minor'] * 100 / $total); @endphp
                            <div class="rv-share">
                                <div class="rv-share-top">
                                    <span>{{ $row['label'] }}@isset($row['count'])<small>{{ trans_choice($l.'by.count', $row['count'], ['n' => $row['count']]) }}</small>@endisset</span>
                                    <span><strong>{{ Money::of($row['minor'], $code)->format() }}</strong><small>{{ $pct }}%</small></span>
                                </div>
                                <div class="rv-track"><span class="lms-bar-fill" style="width: {{ $pct }}%"></span></div>
                            </div>
                        @empty
                            <p class="rv-muted">{{ __($l.'by.empty') }}</p>
                        @endforelse
                    </section>
                @endforeach
            </div>
            <p class="rv-foot">{{ __($l.'by.family_hint') }}</p>

            {{-- What to chase, and what just came in. --}}
            <div class="rv-two">
                <section class="rv-base rv-box">
                    <header class="rv-head">
                        <h3>{{ __($l.'overdue.title') }}</h3>
                        @if ($s['overdue_count'] > 0)<a href="{{ $this->invoicesUrl('overdue') }}" wire:navigate>{{ __($l.'overdue.all') }}</a>@endif
                    </header>
                    @forelse ($this->overdue as $invoice)
                        <a class="rv-row lms-lift" href="{{ $invoice['url'] }}" wire:navigate wire:key="late-{{ $invoice['id'] }}">
                            <div>
                                <strong>{{ $invoice['payer'] }}</strong>
                                <small>{{ $invoice['number'] }}@if ($invoice['students'] !== '') · {{ $invoice['students'] }}@endif</small>
                            </div>
                            <div>
                                <strong>{{ $invoice['amount'] }}</strong>
                                <small class="rv-late-text">{{ trans_choice('billing::invoices.late_by', $invoice['days_late'], ['n' => $invoice['days_late']]) }}</small>
                            </div>
                        </a>
                    @empty
                        <p class="rv-muted">{{ __($l.'overdue.empty') }}</p>
                    @endforelse
                </section>

                <section class="rv-base rv-box">
                    <header class="rv-head">
                        <h3>{{ __($l.'recent.title') }}</h3>
                        <a href="{{ $this->invoicesUrl('paid') }}" wire:navigate>{{ __($l.'recent.all') }}</a>
                    </header>
                    @forelse ($this->recent as $payment)
                        <a class="rv-row lms-lift" href="{{ $payment['url'] }}" wire:navigate wire:key="pay-{{ $payment['id'] }}">
                            <div>
                                <strong>{{ $payment['payer'] }}</strong>
                                <small>{{ $payment['method'] }} · {{ $payment['number'] }}@if ($payment['students'] !== '') · {{ $payment['students'] }}@endif</small>
                            </div>
                            <div>
                                <strong class="rv-in">{{ $payment['amount'] }}</strong>
                                <small>{{ $payment['when'] }}</small>
                            </div>
                        </a>
                    @empty
                        <p class="rv-muted">{{ __($l.'recent.empty') }}</p>
                    @endforelse
                </section>
            </div>

            {{-- Renewals coming. --}}
            <section class="rv-base rv-box">
                <header class="rv-head">
                    <h3>{{ __($l.'forecast.title') }}</h3>
                    <span class="rv-sum">{{ $forecast['total']->format() }}</span>
                </header>
                <p class="rv-note">{{ __($l.'forecast.hint') }}</p>
                @forelse ($forecast['items'] as $item)
                    <div class="rv-row" wire:key="soon-{{ $item['subscription_id'] }}">
                        <div>
                            <strong>{{ $item['student'] }} · {{ $item['subject'] }}</strong>
                            <small>{{ $item['payer'] }}</small>
                        </div>
                        <div>
                            <strong>{{ $item['amount'] }}</strong>
                            <small>{{ $item['when'] }}</small>
                        </div>
                    </div>
                @empty
                    <p class="rv-muted">{{ __($l.'forecast.empty', ['days' => $forecast['days']]) }}</p>
                @endforelse
                @if ($forecast['count'] > count($forecast['items']))
                    <p class="rv-muted">{{ trans_choice($l.'forecast.more', $forecast['count'] - count($forecast['items']), ['n' => $forecast['count'] - count($forecast['items'])]) }}</p>
                @endif
            </section>
        @endif
    </div>
</x-filament-panels::page>

@script
<script>
    // Payments land from the gateways, the family's proofs and colleagues: the screen follows the academy's revenue channel (LmsLive).
    const el = $wire.$el.querySelector('[data-live-channel]');
    if (el && window.LmsLive) {
        window.LmsLive.watch({ el, channel: el.dataset.liveChannel, event: '.changed', refresh: () => $wire.$refresh(), every: 120000 });
    }
</script>
@endscript
