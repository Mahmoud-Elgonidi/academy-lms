@php
    $d = $this->document();
    $inv = $d['invoice'];
    $money = fn (int $minor) => \Modules\SharedKernel\Money\Money::of($minor, $inv->currency)->format();
    $rate = rtrim(rtrim(number_format($inv->tax_rate_bp / 100, 2), '0'), '.');
    $proofs = $this->proofs();
    $waiting = collect($proofs)->firstWhere('status', 'pending');
    $decided = array_values(array_filter($proofs, fn ($p) => $p['status'] !== 'pending'));
    $pr = 'billing::proofs.review.';
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
        .iv .num { text-align: end; font-variant-numeric: tabular-nums; white-space: nowrap; padding-inline-start: 1rem; }
        .iv-line small { display: block; color: var(--gray-500); font-size: .78rem; margin-top: .15rem; }
        .iv-off td { color: var(--success-700); }
        .dark .iv-off td { color: var(--success-400); }
        .iv-sum { display: flex; flex-wrap: wrap; gap: 1.25rem; align-items: flex-start; justify-content: space-between; }
        .iv-totals { margin-inline-start: auto; min-width: min(320px, 100%); display: flex; flex-direction: column; gap: .35rem; font-size: .9rem; }
        .iv-totals div { display: flex; justify-content: space-between; gap: 1rem; }
        .iv-totals .iv-grand { font-size: 1.1rem; font-weight: 800; border-top: 1px solid var(--gray-200); padding-top: .5rem; }
        .iv-totals .iv-left { color: var(--danger-700); font-weight: 700; }
        .iv-pay h3 { margin: 0 0 .5rem; font-size: .95rem; font-weight: 700; }
        .iv-note { font-size: .8125rem; padding: .6rem .8rem; border-radius: var(--radius-lg); background: var(--primary-50); color: var(--primary-800); }
        .pv { max-width: 860px; width: 100%; margin-inline: auto; display: flex; flex-direction: column; gap: .75rem; }
        .pv-wait { display: grid; grid-template-columns: minmax(0, 180px) minmax(0, 1fr); gap: 1.25rem; padding: 1.25rem; border-radius: var(--radius-xl); background: white; border: 1px solid var(--info-200, var(--primary-200)); box-shadow: 0 0 0 4px var(--info-50, var(--primary-50)); }
        .pv-shot { display: flex; align-items: center; justify-content: center; aspect-ratio: 3 / 4; border-radius: var(--radius-lg); overflow: hidden; background: var(--gray-100); border: 1px solid var(--gray-200); }
        .pv-shot img { width: 100%; height: 100%; object-fit: cover; }
        .pv-pdf { font-weight: 800; font-size: 1.25rem; color: var(--danger-600); letter-spacing: .05em; }
        .pv-body { display: flex; flex-direction: column; gap: .35rem; min-width: 0; font-size: .875rem; }
        .pv-tag { align-self: flex-start; padding: .15rem .6rem; border-radius: 999px; font-size: .72rem; font-weight: 700; background: var(--info-50, var(--primary-50)); color: var(--info-700, var(--primary-700)); }
        .pv-amount { font-size: 1.6rem; font-weight: 800; font-variant-numeric: tabular-nums; line-height: 1.2; }
        .pv-body a { color: var(--primary-700); font-weight: 600; }
        .pv-quote { padding: .5rem .7rem; border-radius: var(--radius-lg); background: var(--gray-50); overflow-wrap: anywhere; }
        .pv-actions { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .5rem; }
        .pv-past { padding: .75rem 1rem; border-radius: var(--radius-xl); background: white; border: 1px solid var(--gray-200); font-size: .82rem; }
        .pv-past h3 { margin: 0 0 .35rem; font-size: .85rem; font-weight: 700; }
        .pv-past li { display: flex; flex-wrap: wrap; gap: .25rem .75rem; padding: .4rem 0; border-top: 1px solid var(--gray-100); }
        .pv-past li:first-child { border-top: 0; }
        .pv-past [data-s="approved"] { color: var(--success-700); font-weight: 700; }
        .pv-past [data-s="rejected"] { color: var(--danger-700); font-weight: 700; }
        @media (max-width: 560px) { .pv-wait { grid-template-columns: 1fr; } .pv-shot { aspect-ratio: 4 / 3; } }
        .dark .pv-wait, .dark .pv-past { background: var(--gray-900); border-color: var(--gray-800); }
        .dark .pv-quote { background: var(--gray-800); }
        .dark .iv { background: var(--gray-900); border-color: var(--gray-800); }
        .dark .iv-parties { background: var(--gray-800); }
        @media print {
            .fi-sidebar, .fi-topbar, .fi-header, .fi-page-header-main-ctn > .fi-header, nav, .pv { display: none !important; }
            .fi-main { padding: 0 !important; }
            .iv { border: 0; padding: 0; max-width: none; }
        }
    </style>

    @if ($proofs !== [])
        <section class="pv" aria-label="{{ __($pr.'section') }}">
            @if ($waiting)
                <div class="pv-wait" wire:key="proof-{{ $waiting['id'] }}">
                    <a class="pv-shot" href="{{ $waiting['file'] }}" target="_blank" rel="noopener" title="{{ __($pr.'open_file') }}">
                        @if ($waiting['image'])
                            <img src="{{ $waiting['file'] }}" alt="{{ __($pr.'open_file') }}" loading="lazy">
                        @else
                            <span class="pv-pdf">PDF</span>
                        @endif
                    </a>
                    <div class="pv-body">
                        <span class="pv-tag">{{ __($pr.'waiting') }}</span>
                        <div><span class="pv-amount">{{ $waiting['amount'] }}</span> · {{ $waiting['method'] }}</div>
                        <div class="iv-muted">{{ __($pr.'sent_by', ['name' => $waiting['sentBy'], 'when' => $waiting['sentAt']]) }}@if ($waiting['viaLink']) · {{ __($pr.'via_link') }}@endif</div>
                        <div>{{ __($pr.'paid_on', ['date' => $waiting['paidOn']]) }}</div>
                        @if ($waiting['reference']) <div>{{ __($pr.'reference', ['ref' => '']) }}<span dir="ltr">{{ $waiting['reference'] }}</span></div> @endif
                        @if ($waiting['note']) <div class="pv-quote" dir="auto">{{ $waiting['note'] }}</div> @endif
                        <a href="{{ $waiting['file'] }}" target="_blank" rel="noopener">{{ __($pr.'open_file') }} ({{ $waiting['fileName'] }})</a>
                        <div class="pv-actions">
                            {{ $this->approveProofAction }}
                            {{ $this->rejectProofAction }}
                        </div>
                    </div>
                </div>
            @endif
            @if ($decided !== [])
                <div class="pv-past">
                    <h3>{{ __($pr.'section') }}</h3>
                    <ul>
                        @foreach ($decided as $p)
                            <li wire:key="proof-{{ $p['id'] }}">
                                <span>{{ $p['amount'] }} · {{ $p['method'] }}</span>
                                <span data-s="{{ $p['status'] }}">{{ __($pr.'decided', ['status' => __('billing::proofs.statuses.'.$p['status']), 'name' => $p['decidedBy'], 'when' => $p['decidedAt']]) }}</span>
                                @if ($p['reason']) <span class="iv-muted">{{ __($pr.'reason', ['reason' => $p['reason']]) }}</span> @endif
                                <a href="{{ $p['file'] }}" target="_blank" rel="noopener">{{ __($pr.'open_file') }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </section>
    @endif

    <article class="iv">
        <header class="iv-top">
            <div>
                <h2 class="lms-heading">{{ $d['seller'] ?? $d['academy'] }}</h2>
                @if ($d['taxNumber']) <div class="iv-muted">{{ __('billing::invoices.doc.tax_number', ['number' => $d['taxNumber']]) }}</div> @endif
            </div>
            <div class="iv-no">
                <span class="iv-muted">{{ $d['title'] }}@if ($d['simplified']) · {{ __('billing::invoices.doc.simplified_alt') }}@endif</span>
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
                    @if ($d['family'])
                        <div class="iv-muted">{{ trans_choice('billing::invoices.family', count($d['courses']), ['n' => count($d['courses'])]) }}</div>
                        @foreach ($d['courses'] as $course)
                            <div><a href="{{ route('filament.academy.resources.enrollments.view', ['record' => $course['enrollment_id']]) }}" wire:navigate>{{ $course['student'] }} · {{ $course['subject'] }}</a></div>
                        @endforeach
                    @elseif ($d['enrollmentUrl'])
                        <div><a href="{{ $d['enrollmentUrl'] }}" wire:navigate>{{ __('billing::invoices.doc.open_account') }}</a></div>
                    @endif
                </div>
            @endif
            <div>
                <small>{{ $d['simplified'] ? __('billing::invoices.doc.issued_at') : __('billing::invoices.fields.issued_at') }}</small>
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
                @foreach ($d['lines'] as $line)
                    <tr @class(['iv-off' => $line->kind === 'discount'])>
                        <td class="iv-line">
                            {{ $line->description() }}
                            @if ($line->kind === 'block')
                                <small>{{ __('billing::invoices.doc.block_terms', ['sessions' => $line->meta['sessions'] ?? '?', 'minutes' => $line->meta['session_minutes'] ?? '?', 'days' => $line->meta['validity_days'] ?? '?']) }}</small>
                            @elseif ($line->kind === 'extra')
                                <small>{{ __('billing::invoices.doc.extra_terms', ['unit' => $money($line->unit_minor), 'days' => $line->meta['validity_days'] ?? '?']) }}</small>
                            @endif
                        </td>
                        <td class="num">{{ $line->quantity }}</td>
                        <td class="num">{{ $money($line->amount_minor) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="iv-sum">
        @if ($d['qr']) @include('billing::invoice.qr', ['qr' => $d['qr']]) @endif
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

        @if ($d['checkouts'] !== [])
            <section class="iv-pay iv-online">
                <h3>{{ __('billing::payments.invoice.section') }}</h3>
                <table>
                    <tbody>
                        @foreach ($d['checkouts'] as $c)
                            <tr>
                                <td>{{ $c['gateway'] }} @if ($c['ref']) <span class="iv-muted" dir="ltr">· {{ $c['ref'] }}</span> @endif
                                    @if ($c['failure']) <div class="iv-muted">{{ $c['failure'] }}</div> @endif</td>
                                <td class="iv-muted">{{ $c['when'] }}</td>
                                <td>{{ __('billing::payments.statuses.'.$c['status']) }}</td>
                                <td class="num">{{ $c['amount'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endif

        @if ($inv->status === 'void' && $d['creditNote'])
            <div class="iv-note">{{ __('billing::invoices.doc.voided_by_note', ['number' => $d['creditNote']['number'], 'reason' => $inv->void_reason]) }}</div>
            @include('billing::invoice.credit-note', ['note' => $d['creditNote']])
        @elseif ($inv->status === 'void')
            <div class="iv-note">{{ __('billing::invoices.doc.voided', ['reason' => $inv->void_reason]) }}</div>
        @elseif ($inv->status === 'issued' && $d['courses'] !== [])
            <div class="iv-note">{{ __('billing::invoices.doc.on_payment') }}</div>
        @endif
    </article>
</x-filament-panels::page>
