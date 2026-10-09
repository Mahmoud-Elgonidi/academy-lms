{{-- The invoice as a document for the payer, to read and print: for a Saudi tax invoice, its title in both languages, time of issue and QR code, and the credit note that cancelled it. --}}
@php
    $l = 'billing::invoices.doc.';
    $inv = $d['invoice'];
    $money = fn (int $minor) => \Modules\SharedKernel\Money\Money::of($minor, $inv->currency)->format();
    $rate = rtrim(rtrim(number_format($inv->tax_rate_bp / 100, 2), '0'), '.');
    $tone = ['paid' => 'good', 'void' => 'bad', 'overdue' => 'bad'][$d['status']] ?? 'wait';
@endphp
<x-billing::pay.layout :branding="$branding" :title="$d['title'].' '.$inv->number" :wide="true">
    <style>
        .doc { display: flex; flex-direction: column; gap: 1.1rem; }
        .doc-top { display: flex; flex-wrap: wrap; justify-content: space-between; gap: .8rem; align-items: flex-start; }
        .doc-top h1 { font-size: 1.15rem; }
        .doc-no { display: flex; flex-direction: column; align-items: flex-end; text-align: end; gap: .15rem; }
        .doc-no strong { font-size: 1.05rem; font-variant-numeric: tabular-nums; }
        .doc-parties { display: grid; grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr)); gap: .8rem; padding: .85rem; border-radius: .8rem; background: var(--bg); }
        .doc-parties small { display: block; color: var(--muted); font-size: .75rem; }
        .doc table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        .doc th { text-align: start; font-size: .75rem; color: var(--muted); font-weight: 600; padding: .45rem 0; border-bottom: 1px solid var(--line); }
        .doc td { padding: .65rem 0; border-bottom: 1px solid var(--line); vertical-align: top; }
        .doc .num { text-align: end; font-variant-numeric: tabular-nums; white-space: nowrap; padding-inline-start: 1rem; }
        .doc tr.off td { color: #15803d; }
        .doc-sum { display: flex; flex-wrap: wrap; gap: 1.2rem; justify-content: space-between; align-items: flex-start; }
        .doc-totals { margin-inline-start: auto; min-width: min(18rem, 100%); display: flex; flex-direction: column; gap: .3rem; font-size: .92rem; }
        .doc-totals div { display: flex; justify-content: space-between; gap: 1rem; }
        .doc-totals .grand { font-weight: 700; font-size: 1.05rem; border-top: 1px solid var(--line); padding-top: .4rem; }
        .doc-actions { display: flex; gap: .6rem; flex-wrap: wrap; }
        .doc-actions button, .doc-actions a { font: inherit; font-weight: 600; font-size: .9rem; padding: .55rem 1rem; border-radius: .7rem; border: 1px solid var(--line); background: var(--card); color: var(--ink); cursor: pointer; text-decoration: none; }
        .doc-actions button { background: var(--brand); border-color: var(--brand); color: #fff; }
        .doc-note { font-size: .88rem; padding: .6rem .8rem; border-radius: .7rem; background: var(--bad-soft); color: var(--bad); }
    </style>

    <div class="doc-actions no-print">
        <button type="button" onclick="window.print()">{{ __($l.'print') }}</button>
        @if ($back) <a href="{{ $back }}">{{ __($l.'back') }}</a> @endif
    </div>

    <article class="card doc">
        <header class="doc-top">
            <div>
                <h1>{{ $d['seller'] ?? $d['academy'] }}</h1>
                @if ($d['taxNumber']) <div class="muted">{{ __($l.'tax_number', ['number' => $d['taxNumber']]) }}</div> @endif
            </div>
            <div class="doc-no">
                <span class="muted">{{ $d['title'] }}@if ($d['simplified']) · {{ __($l.'simplified_alt') }}@endif</span>
                <strong dir="ltr">{{ $inv->number }}</strong>
                <span class="pill" data-tone="{{ $tone }}">{{ __('billing::billing.statuses.'.$d['status']) }}</span>
            </div>
        </header>

        <section class="doc-parties">
            <div><small>{{ __($l.'bill_to') }}</small><strong>{{ $d['payer']->name ?? '—' }}</strong></div>
            @if ($d['student']) <div><small>{{ __('billing::invoices.fields.student') }}</small><strong>{{ $d['student']->name }}</strong></div> @endif
            <div><small>{{ $d['simplified'] ? __($l.'issued_at') : __('billing::invoices.fields.issued_at') }}</small><strong>{{ $d['issued'] }}</strong></div>
            @if ($inv->status === 'issued') <div><small>{{ __('billing::invoices.fields.due') }}</small><strong>{{ $d['due'] }}</strong></div> @endif
        </section>

        <table>
            <thead><tr><th>{{ __($l.'item') }}</th><th class="num">{{ __($l.'qty') }}</th><th class="num">{{ __($l.'amount') }}</th></tr></thead>
            <tbody>
                @foreach ($d['lines'] as $line)
                    <tr @class(['off' => $line->kind === 'discount'])><td>{{ $line->description() }}</td><td class="num">{{ $line->quantity }}</td><td class="num">{{ $money($line->amount_minor) }}</td></tr>
                @endforeach
            </tbody>
        </table>

        <div class="doc-sum">
            @if ($d['qr']) @include('billing::invoice.qr', ['qr' => $d['qr']]) @endif
            <div class="doc-totals">
                @if ($inv->tax_rate_bp > 0)
                    <div><span>{{ __($l.'subtotal') }}</span><span class="num">{{ $money($inv->subtotal_minor) }}</span></div>
                    <div><span>{{ __($l.'vat', ['rate' => $rate]) }}</span><span class="num">{{ $money($inv->tax_minor) }}</span></div>
                @endif
                <div class="grand"><span>{{ __($l.'total') }}</span><span class="num">{{ $money($inv->total_minor) }}</span></div>
                @if ($inv->paid_minor > 0) <div><span>{{ __($l.'paid') }}</span><span class="num">{{ $money($inv->paid_minor) }}</span></div> @endif
                @if ($inv->tax_rate_bp > 0) <div class="muted">{{ $inv->prices_include_tax ? __($l.'vat_included') : __($l.'vat_added') }}</div> @endif
            </div>
        </div>

        @if ($inv->status === 'void' && $d['creditNote'])
            <div class="doc-note">{{ __($l.'voided_by_note', ['number' => $d['creditNote']['number'], 'reason' => $inv->void_reason]) }}</div>
            @include('billing::invoice.credit-note', ['note' => $d['creditNote']])
        @elseif ($inv->status === 'void')
            <div class="doc-note">{{ __($l.'voided', ['reason' => $inv->void_reason]) }}</div>
        @endif
    </article>
</x-billing::pay.layout>
