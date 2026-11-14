@php $l = 'platform::recharge.console.'; $t = $this->totals; @endphp
<x-filament-panels::page>
    @include('platform::console.styles')
    @include('platform::plans.styles')
    @include('platform::plans.usage-styles')
    <div class="pc-stack">
        <div class="pc-bar">
            <span class="pp-sub">{{ __($l.'period') }}</span>
            <div class="pc-chips" role="group" aria-label="{{ __($l.'period') }}">
                @foreach (\Modules\Platform\Filament\Console\Pages\RechargeRevenuePage::PERIODS as $p)
                    <button type="button" aria-pressed="{{ $this->period === $p ? 'true' : 'false' }}" wire:click="showPeriod('{{ $p }}')">{{ __($l.'periods.'.$p) }}</button>
                @endforeach
            </div>
        </div>

        {{-- Sold, and what it brought in: each currency on its own. --}}
        <div class="pc-tiles lms-stagger">
            @foreach ($t['sold'] as $s)
                <div class="pc-base pc-tile"><small>{{ __($l.'sold') }} · {{ $s['label'] }}</small><strong>{{ $s['amount'] }}</strong><small>{{ $s['purchases'] }}</small></div>
            @endforeach
            <div class="pc-base pc-tile" data-main>
                <small>{{ __($l.'revenue') }}</small>
                <div class="pc-lines" dir="ltr">
                    @forelse ($t['by_currency'] as $money)<strong>{{ $money }}</strong>@empty<strong>—</strong>@endforelse
                </div>
            </div>
            @foreach ($t['unused'] as $u)
                <div class="pc-base pc-tile"><small>{{ __($l.'unused') }} · {{ $u['label'] }}</small><strong>{{ $u['left'] }}</strong><em>{{ $u['expiring'] }}</em></div>
            @endforeach
        </div>
        <p class="pp-sub">{{ __($l.'sold_hint') }} {{ __($l.'unused_hint') }}</p>

        <section class="pc-base pc-box">
            <h3><span>{{ __($l.'revenue') }}</span></h3>
            <p class="pp-sub">{{ __($l.'revenue_hint') }}</p>
            @if ($t['revenue'] === [])
                <p class="pp-sub">{{ __($l.'revenue_empty') }}</p>
            @else
                <div class="mu-table-wrap">
                    <table class="mu-table">
                        <thead><tr><th>{{ __($l.'cols.pack') }}</th><th>{{ __($l.'cols.count') }}</th><th>{{ __($l.'cols.total') }}</th></tr></thead>
                        <tbody>
                            @foreach ($t['revenue'] as $r)
                                <tr><td>{{ $r['pack'] }}</td><td class="mu-num">{{ number_format($r['purchases']) }}</td><td class="mu-num" dir="ltr">{{ $r['total'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="pc-base pc-box">
            <h3><span>{{ __($l.'academies') }}</span></h3>
            <div class="mu-table-wrap">
                <table class="mu-table">
                    <thead><tr>
                        <th>{{ __($l.'cols.academy') }}</th><th>{{ __($l.'cols.plan') }}</th>
                        <th>{{ __($l.'cols.allowance') }} · {{ \Modules\SharedKernel\Plans\Meter::VideoMinutes->label() }}</th>
                        <th>{{ __($l.'cols.allowance') }} · {{ \Modules\SharedKernel\Plans\Meter::AiCredits->label() }}</th>
                        <th>{{ __($l.'cols.bought') }}</th><th>{{ __($l.'cols.auto') }}</th><th>{{ __($l.'cols.last') }}</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($this->academies as $a)
                            <tr wire:key="academy-{{ $a['id'] }}">
                                <td><a class="pp-link" href="{{ $a['url'] }}" wire:navigate>{{ $a['name'] }}</a></td>
                                <td>{{ $a['plan'] }}</td>
                                <td>{{ $a['video']['allowance'] }}@if ($a['video']['debt'])<small class="mu-reason">{{ __($l.'cols.overage') }}: {{ $a['video']['debt'] }}</small>@endif</td>
                                <td>{{ $a['ai']['allowance'] }}@if ($a['ai']['debt'])<small class="mu-reason">{{ __($l.'cols.overage') }}: {{ $a['ai']['debt'] }}</small>@endif</td>
                                <td>{{ $a['video']['bought'] }}<small class="mu-reason">{{ $a['ai']['bought'] }}</small></td>
                                <td>{{ $a['auto'] }}</td>
                                <td>{{ $a['last'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <div class="pc-two">
            <section class="pc-base pc-box">
                <h3><span>{{ __($l.'failed') }}</span></h3>
                @if ($t['failed'] === [])
                    <p class="pp-sub">{{ __($l.'failed_empty') }}</p>
                @else
                    <div class="mu-table-wrap">
                        <table class="mu-table">
                            <thead><tr><th>{{ __($l.'cols.when') }}</th><th>{{ __($l.'cols.academy') }}</th><th>{{ __($l.'cols.why') }}</th></tr></thead>
                            <tbody>
                                @foreach ($t['failed'] as $f)
                                    <tr wire:key="failed-{{ $f['id'] }}">
                                        <td>{{ $f['when'] }}</td>
                                        <td><a class="pp-link" href="{{ $f['academy_url'] }}" wire:navigate>{{ $f['academy'] }}</a><small class="mu-reason">{{ $f['what'] }} · {{ $f['method'] }}</small></td>
                                        <td>{{ $f['why'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="pc-base pc-box">
                <h3><span>{{ __($l.'receipts') }}</span></h3>
                @if ($t['receipts'] === [])
                    <p class="pp-sub">{{ __($l.'receipts_empty') }}</p>
                @else
                    <div class="mu-table-wrap">
                        <table class="mu-table">
                            <thead><tr><th>{{ __($l.'cols.number') }}</th><th>{{ __($l.'cols.academy') }}</th><th>{{ __($l.'cols.amount') }}</th></tr></thead>
                            <tbody>
                                @foreach ($t['receipts'] as $r)
                                    <tr wire:key="receipt-{{ $r['id'] }}">
                                        <td><a class="pp-link" href="{{ $r['url'] }}" target="_blank" rel="noopener" dir="ltr">{{ $r['number'] }}</a><small class="mu-reason">{{ $r['when'] }}</small></td>
                                        <td>{{ $r['academy'] }}<small class="mu-reason">{{ $r['what'] }} · {{ $r['method'] }}</small></td>
                                        <td class="mu-num" dir="ltr">{{ $r['amount'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-filament-panels::page>
