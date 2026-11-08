@php $l = 'platform::metering.'; $cards = $this->cards; $charts = $this->charts; $statement = $this->statement; @endphp
<x-filament-panels::page>
    @include('platform::console.styles')
    @include('platform::plans.styles')
    @include('platform::plans.usage-styles')
    <div class="pc-stack">
        {{-- One card for each meter: this month's allowance, the bought balance and its lots, what is left and for how long. --}}
        <div class="mu-cards lms-stagger">
            @foreach ($cards as $c)
                <section class="pc-base mu-card" data-state="{{ $c['state'] }}" wire:key="card-{{ $c['key'] }}">
                    <header>
                        <h3>{{ $c['label'] }}</h3>
                        <span class="pp-tag" data-tone="{{ ['ok' => 'brand', 'near' => 'warn', 'full' => 'danger'][$c['state']] }}">{{ __($l.'state.'.$c['state']) }}</span>
                    </header>

                    <div class="mu-headline">
                        <span>{{ __($l.'card.available') }}</span>
                        <strong>{{ $c['limited'] ? $c['available'] : __($l.'card.unlimited_short') }}</strong>
                    </div>

                    @if ($c['allowance'])
                        <div class="mu-part">
                            <div class="mu-row"><span>{{ __($l.'card.this_month') }}</span><span>{{ $c['allowance'] }}</span></div>
                            <div class="pp-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $c['percent'] }}" aria-label="{{ __($l.'card.this_month') }}"><span class="lms-bar-fill" style="width: {{ max(2, $c['percent']) }}%"></span></div>
                            <small>{{ __($l.'card.allowance_left', ['left' => $c['allowance_left']]) }} · {{ $c['renews'] }}</small>
                        </div>
                    @else
                        <div class="mu-part"><div class="mu-row"><span>{{ __($l.'card.used_month') }}</span><span>{{ $c['used'] }}</span></div></div>
                    @endif
                    @if ($c['running'])<small class="mu-note">{{ $c['running'] }}</small>@endif
                    @if ($c['debt'])<p class="mu-debt">{{ $c['debt'] }}</p>@endif

                    @if ($c['limited'])
                        <div class="mu-part">
                            <div class="mu-row"><span>{{ __($l.'card.bought') }}</span><span>{{ $c['bought'] }}</span></div>
                            @forelse ($c['lots'] as $lot)
                                <div class="mu-lot" wire:key="lot-{{ $lot['id'] }}">
                                    <span><strong>{{ $lot['what'] }}</strong> · {{ $lot['left'] }}</span>
                                    <small>{{ $lot['ends'] }}</small>
                                    <div class="pp-bar mu-lot-bar"><span class="lms-bar-fill" style="width: {{ max(2, $lot['percent']) }}%"></span></div>
                                </div>
                            @empty
                                <small>{{ __($l.'card.no_bought') }}</small>
                            @endforelse
                        </div>
                    @endif

                    <p class="mu-runout">{{ $c['runout'] }}</p>
                    @if ($c['hard'])<small class="mu-note">{{ __($l.'card.hard') }}</small>@endif

                    @if ($c['limited'])
                        <footer class="mu-actions">
                            @if ($c['waiting'])
                                <small class="pp-waiting">{{ __('platform::topups.buy.waiting') }}</small>
                            @else
                                <a class="mu-button" data-primary href="{{ $c['buy_url'] }}" wire:navigate>{{ __($l.'card.buy') }}</a>
                            @endif
                            <a class="mu-button" href="{{ $c['upgrade_url'] }}" wire:navigate>{{ __('platform::topups.upgrade.label') }}</a>
                        </footer>
                    @endif
                </section>
            @endforeach
        </div>

        {{-- The month day by day beside the month before, and who used it. --}}
        @foreach ($charts as $key => $chart)
            <section class="pc-base pc-box mu-chart" wire:key="chart-{{ $key }}">
                <h3><span>{{ __($l.'charts.daily', ['meter' => $chart['label']]) }}</span></h3>
                <p class="pp-sub">{{ __($l.'charts.totals', ['now' => $chart['this_total'], 'before' => $chart['last_total']]) }}</p>
                <ul class="mu-legend">
                    <li><i data-series="now"></i>{{ __($l.'charts.this_month') }}</li>
                    <li><i data-series="before"></i>{{ __($l.'charts.last_month') }}</li>
                </ul>
                <div class="mu-days" role="img" aria-label="{{ __($l.'charts.daily', ['meter' => $chart['label']]) }}">
                    @foreach ($chart['days'] as $d)
                        <div class="mu-day" title="{{ __($l.'charts.day_tip', ['day' => $d['day'], 'now' => $d['now'] ?? '—', 'before' => $d['before'] ?? '—']) }}">
                            <span class="mu-col" data-series="before" style="height: {{ $d['before_pct'] }}%"></span>
                            <span class="mu-col lms-bar-fill" data-series="now" style="height: {{ $d['now_pct'] }}%"></span>
                            @if ($d['day'] === 1 || $d['day'] % 5 === 0)<small>{{ $d['day'] }}</small>@endif
                        </div>
                    @endforeach
                </div>
                <div class="mu-split">
                    @if ($chart['by_subject'] !== [])
                        <div>
                            <h4>{{ __($l.'charts.by_helper') }}</h4>
                            @include('platform::plans.usage-bars', ['rows' => $chart['by_subject']])
                        </div>
                    @endif
                    <div>
                        <h4>{{ __($l.'charts.by_person.'.$key) }}</h4>
                        @include('platform::plans.usage-bars', ['rows' => $chart['by_person']])
                    </div>
                </div>
            </section>
        @endforeach

        @if ($this->rates !== [])
            <section class="pc-base pc-box">
                <h3><span>{{ __($l.'rates.academy_title') }}</span></h3>
                <p class="pp-sub">{{ __($l.'rates.academy_sub') }}</p>
                <ul class="mu-rates">
                    @foreach ($this->rates as $r)
                        <li><span>{{ $r['label'] }}</span><strong>{{ \Modules\Platform\Filament\Console\Pages\AiCreditRatesPage::rateText($r['base'], $r['per_minute']) }}</strong></li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="pc-base pc-box">
            <h3>
                <span>{{ __($l.'alerts.title') }}</span>
                {{ $this->halfAlertAction }}
            </h3>
            <p class="pp-sub">{{ __($l.'alerts.'.($this->halfAlert ? 'on' : 'off')) }}</p>
        </section>

        {{-- The ledger as a statement: every line with the balance after it. --}}
        <section class="pc-base pc-box">
            <h3>
                <span>{{ __($l.'statement.title') }}</span>
                <span class="mu-tabs" role="tablist">
                    @foreach ($cards as $c)
                        <button type="button" role="tab" aria-selected="{{ $this->meter === $c['key'] ? 'true' : 'false' }}" wire:click="showMeter('{{ $c['key'] }}')">{{ $c['label'] }}</button>
                    @endforeach
                </span>
            </h3>
            <p class="pp-sub">{{ __($l.'statement.subtitle', ['unit' => $statement['unit']]) }}</p>
            @if ($statement['lines'] === [])
                <p class="pp-sub">{{ __($l.'statement.empty') }}</p>
            @else
                <div class="mu-table-wrap">
                    <table class="mu-table">
                        <thead><tr><th>{{ __($l.'statement.when') }}</th><th>{{ __($l.'statement.what') }}</th><th>{{ __($l.'statement.amount') }}</th><th>{{ __($l.'statement.after') }}</th></tr></thead>
                        <tbody>
                            @foreach ($statement['lines'] as $line)
                                <tr>
                                    <td>{{ $line['when'] }}</td>
                                    <td>{{ $line['what'] }}@if ($line['ends'])<small> · {{ $line['ends'] }}</small>@endif @if ($line['reason'])<small class="mu-reason">{{ $line['reason'] }}</small>@endif</td>
                                    <td class="mu-num" data-sign="{{ $line['sign'] }}" dir="ltr">{{ $line['amount'] }}</td>
                                    <td class="mu-num" dir="ltr">{{ $line['after'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($statement['more'])
                    <button type="button" class="pp-link" wire:click="more" wire:loading.attr="disabled">{{ __($l.'statement.more') }}</button>
                @endif
            @endif
        </section>
    </div>
</x-filament-panels::page>
