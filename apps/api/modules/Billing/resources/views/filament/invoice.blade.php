@php
    $d = $this->document();
    $inv = $d['invoice'];
    $money = fn (int $minor) => \Modules\SharedKernel\Money\Money::of($minor, $inv->currency)->format();
    $rate = rtrim(rtrim(number_format($inv->tax_rate_bp / 100, 2), '0'), '.');
@endphp

<x-filament-panels::page>
    <style>
        .iv { max-width: 860px; width: 100%; margin-inline: auto; background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl); padding: 2rem; display: flex; flex-direction: column; gap: 1.5rem; }
        .iv-top { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 1rem; align-items: flex-start; }
        .iv-top h2 { margin: 0; font-size: 1.35rem; font-weight: 800; }
        .iv-muted { color: var(--gray-500); font-size: .8125rem; }
        .iv-no { text-align: end; display: flex; flex-direction: column; gap: .2rem; align-items: flex-end; }
        .iv-no strong { font-size: 1.15rem; font-variant-numeric: tabular-nums; }
        .iv-pill { padding: .15rem .65rem; border-radius: 999px; font-size: .75rem; font-weight: 700; background: var(--warning-50); color: var(--warning-800); }
        .iv-pill[data-status="paid"] { background: var(--success-50); color: var(--success-700); }
        .iv-pill[data-status="overdue"] { background: var(--danger-50); color: var(--danger-700); }
        .iv-pill[data-status="void"] { background: var(--gray-100); color: var(--gray-600); text-decoration: line-through; }
        .iv-parties { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; padding: 1rem; border-radius: var(--radius-lg); background: var(--gray-50); }
        .iv-parties small { display: block; color: var(--gray-500); font-size: .75rem; margin-bottom: .15rem; }
        .iv-parties a { color: var(--primary-700); font-size: .8rem; }
        .iv table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        .iv th { text-align: start; font-size: .75rem; color: var(--gray-500); font-weight: 600; padding: .5rem 0; border-bottom: 1px solid var(--gray-200); }
        .iv td { padding: .75rem 0; border-bottom: 1px solid var(--gray-100); vertical-align: top; }
        .iv .num { text-align: end; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .iv-line small { display: block; color: var(--gray-500); font-size: .78rem; margin-top: .15rem; }
        .iv-totals { margin-inline-start: auto; min-width: min(320px, 100%); display: flex; flex-direction: column; gap: .35rem; font-size: .9rem; }
        .iv-totals div { display: flex; justify-content: space-between; gap: 1rem; }
        .iv-totals .iv-grand { font-size: 1.1rem; font-weight: 800; border-top: 1px solid var(--gray-200); padding-top: .5rem; }
        .iv-totals .iv-left { color: var(--danger-700); font-weight: 700; }
        .iv-pay h3 { margin: 0 0 .5rem; font-size: .95rem; font-weight: 700; }
        .iv-note { font-size: .8125rem; padding: .6rem .8rem; border-radius: var(--radius-lg); background: var(--primary-50); color: var(--primary-800); }
        .dark .iv { background: var(--gray-900); border-color: var(--gray-800); }
        .dark .iv-parties { background: var(--gray-800); }
        @media print {
            .fi-sidebar, .fi-topbar, .fi-header, .fi-page-header-main-ctn > .fi-header, nav { display: none !important; }
            .fi-main { padding: 0 !important; }
            .iv { border: 0; padding: 0; max-width: none; }
        }
    </style>

    <article class="iv">
        <header class="iv-top">
            <div>
                <h2 class="lms-heading">{{ $d['academy'] }}</h2>
                @if ($d['taxNumber']) <div class="iv-muted">{{ __('billing::invoices.doc.tax_number', ['number' => $d['taxNumber']]) }}</div> @endif
            </div>
            <div class="iv-no">
                <span class="iv-muted">{{ $inv->tax_rate_bp > 0 ? __('billing::invoices.doc.tax_invoice') : __('billing::invoices.model') }}</span>
                <strong>{{ $inv->number }}</strong>
                <span class="iv-pill" data-status="{{ $d['status'] }}">{{ __('billing::billing.statuses.'.$d['status']) }}</span>
            </div>
        </header>

        <section class="iv-parties">
            <div>
                <small>{{ __('billing::invoices.doc.bill_to') }}</small>
                <strong>{{ $d['payer']->name ?? '—' }}</strong>
                @if ($d['payer']?->phone_e164) <div class="iv-muted" dir="ltr">{{ $d['payer']->phone_e164 }}</div> @endif
                @if ($d['payer']?->email) <div class="iv-muted">{{ $d['payer']->email }}</div> @endif
            </div>
            @if ($d['student'])
                <div>
                    <small>{{ __('billing::invoices.fields.student') }}</small>
                    <strong>{{ $d['student']->name }}</strong>
                    @if ($d['enrollmentUrl']) <div><a href="{{ $d['enrollmentUrl'] }}" wire:navigate>{{ __('billing::invoices.doc.open_account') }}</a></div> @endif
                </div>
            @endif
            <div>
                <small>{{ __('billing::invoices.fields.issued_at') }}</small>
                <strong>{{ $d['issued'] }}</strong>
            </div>
            <div>
                <small>{{ __('billing::invoices.fields.due') }}</small>
                <strong>{{ $d['due'] }}</strong>
                @if ($d['dueLine']) <div class="iv-muted">{{ $d['dueLine'] }}</div> @endif
            </div>
        </section>

        <table>
            <thead>
                <tr><th>{{ __('billing::invoices.doc.item') }}</th><th class="num">{{ __('billing::invoices.doc.qty') }}</th><th class="num">{{ __('billing::invoices.doc.amount') }}</th></tr>
            </thead>
            <tbody>
                @foreach ($inv->lines as $line)
                    <tr>
                        <td class="iv-line">
                            {{ $line->description() }}
                            @if ($line->kind === 'block')
                                <small>{{ __('billing::invoices.doc.block_terms', ['sessions' => $line->meta['sessions'] ?? '?', 'minutes' => $line->meta['session_minutes'] ?? '?', 'days' => $line->meta['validity_days'] ?? '?']) }}</small>
                            @endif
                        </td>
                        <td class="num">{{ $line->quantity }}</td>
                        <td class="num">{{ $money($line->amount_minor) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="iv-totals">
            @if ($inv->tax_rate_bp > 0)
                <div><span>{{ __('billing::invoices.doc.subtotal') }}</span><span class="num">{{ $money($inv->subtotal_minor) }}</span></div>
                <div><span>{{ __('billing::invoices.doc.vat', ['rate' => $rate]) }}</span><span class="num">{{ $money($inv->tax_minor) }}</span></div>
            @endif
            <div class="iv-grand"><span>{{ __('billing::invoices.doc.total') }}</span><span class="num">{{ $money($inv->total_minor) }}</span></div>
            @if ($inv->paid_minor > 0)
                <div><span>{{ __('billing::invoices.doc.paid') }}</span><span class="num">{{ $money($inv->paid_minor) }}</span></div>
            @endif
            @if ($inv->status === 'issued')
                <div class="iv-left"><span>{{ __('billing::invoices.doc.outstanding') }}</span><span class="num">{{ $inv->outstanding()->format() }}</span></div>
            @endif
            @if ($inv->tax_rate_bp > 0)
                <div class="iv-muted">{{ $inv->prices_include_tax ? __('billing::invoices.doc.vat_included') : __('billing::invoices.doc.vat_added') }}</div>
            @endif
        </div>

        @if ($d['payments'] !== [])
            <section class="iv-pay">
                <h3>{{ __('billing::invoices.doc.payments') }}</h3>
                <table>
                    <tbody>
                        @foreach ($d['payments'] as $p)
                            <tr>
                                <td>{{ $p['method'] }} @if ($p['reference']) <span class="iv-muted" dir="ltr">· {{ $p['reference'] }}</span> @endif</td>
                                <td class="iv-muted">{{ $p['when'] }}</td>
                                <td class="num">{{ $p['amount'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endif

        @if ($inv->status === 'void')
            <div class="iv-note">{{ __('billing::invoices.doc.voided', ['reason' => $inv->void_reason]) }}</div>
        @elseif ($inv->status === 'issued' && $inv->subscription_id)
            <div class="iv-note">{{ __('billing::invoices.doc.on_payment') }}</div>
        @endif
    </article>
</x-filament-panels::page>
