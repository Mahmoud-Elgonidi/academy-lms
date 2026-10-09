@php
    $a = $this->account();
    $h = $a['header'];
    $city = fn (string $tz) => \Modules\SharedKernel\Time\Timezones::city($tz);
    $low = $a['block'] > 0 && $a['sessionsLeft'] <= $a['renewAt'];
    $openInvoice = collect($a['invoices'])->first(fn ($i) => in_array($i['status'], ['issued', 'overdue'], true));
@endphp

<x-filament-panels::page>
    <style>
        .ac { display: flex; flex-direction: column; gap: 1.1rem; }
        .ac-sub { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; color: var(--gray-600); font-size: .875rem; margin-top: -.75rem; }
        .ac-pill { padding: .1rem .6rem; border-radius: 999px; font-size: .75rem; font-weight: 700; background: var(--gray-100); color: var(--gray-700); }
        .ac-pill[data-s="active"], .ac-pill[data-s="paid"], .ac-pill[data-s="funded"] { background: var(--success-50); color: var(--success-700); }
        .ac-pill[data-s="pending_payment"], .ac-pill[data-s="issued"], .ac-pill[data-s="unfunded"] { background: var(--warning-50); color: var(--warning-800); }
        .ac-pill[data-s="overdue"], .ac-pill[data-s="teacher_absent"] { background: var(--danger-50); color: var(--danger-700); }
        .ac-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: .75rem; }
        .ac-card { background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl); padding: .9rem 1rem; display: flex; flex-direction: column; gap: .2rem; }
        .ac-card small { color: var(--gray-500); font-size: .8rem; }
        .ac-card strong { font-size: 1.35rem; }
        .ac-card[data-tone="warn"] { border-color: var(--warning-300); background: var(--warning-50); }
        .ac-card a { color: var(--primary-700); font-size: .8rem; font-weight: 600; }
        .ac-meter { height: .45rem; border-radius: 999px; background: var(--gray-100); overflow: hidden; margin-top: .3rem; }
        .ac-meter i { display: block; height: 100%; background: var(--primary-500); border-radius: inherit; }
        .ac-card[data-tone="warn"] .ac-meter i { background: var(--warning-500); }
        .ac-cols { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1.1rem; align-items: start; }
        @media (min-width: 1200px) { .ac-cols { grid-template-columns: minmax(0, 1fr) minmax(0, 1.25fr); } }
        .ac-box { background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl); padding: 1rem; display: flex; flex-direction: column; gap: .7rem; }
        .ac-box h2 { margin: 0; font-size: 1rem; font-weight: 700; }
        .ac-muted { color: var(--gray-500); font-size: .8125rem; }
        .ac-times { display: flex; flex-wrap: wrap; gap: .5rem; }
        .ac-time { border: 1px solid var(--gray-200); border-radius: var(--radius-lg); padding: .45rem .7rem; display: flex; flex-direction: column; }
        .ac-time strong { font-size: .95rem; }
        .ac-time small { color: var(--gray-500); font-size: .75rem; }
        .ac table { width: 100%; border-collapse: collapse; font-size: .85rem; }
        .ac th { text-align: start; font-size: .72rem; color: var(--gray-500); font-weight: 600; padding: .35rem 0; border-bottom: 1px solid var(--gray-200); }
        .ac td { padding: .5rem 0; border-bottom: 1px solid var(--gray-100); vertical-align: middle; }
        .ac .num { text-align: end; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .ac .plus { color: var(--success-700); font-weight: 700; }
        .ac .minus { color: var(--gray-700); font-weight: 700; }
        .ac td a { color: var(--primary-700); font-weight: 600; }
        .ac-row-actions { display: flex; gap: .6rem; justify-content: flex-end; }
        .ac-pill[data-s="makeup"] { background: var(--info-50); color: var(--info-700); }
        .ac-pill[data-s="cancelled"] { background: var(--gray-100); color: var(--gray-600); }
        .dark .ac-card, .dark .ac-box { background: var(--gray-900); border-color: var(--gray-800); }
        .dark .ac-card[data-tone="warn"] { background: color-mix(in oklab, var(--warning-500) 12%, transparent); }
    </style>

    <div class="ac">
        <div class="ac-sub">
            <span>{{ $a['subject'] }} · {{ __('scheduling::enrollments.with', ['teacher' => $h['teacher']]) }}</span>
            <span class="ac-pill" data-s="{{ $h['status'] }}">{{ __('scheduling::enrollments.statuses.'.$h['status']) }}</span>
            @if ($a['plan']) <span>· {{ $a['plan'] }} · {{ $a['price'] }}</span> @endif
            @if ($a['sharedWith'] !== null)
                <span>· @if ($a['sharedWith'] === []){{ __('scheduling::enrollments.shared.alone') }}@else{{ __('scheduling::enrollments.shared.label') }}
                    @foreach ($a['sharedWith'] as $other)<a href="{{ route('filament.academy.resources.enrollments.view', ['record' => $other['id']]) }}" wire:navigate>{{ $other['subject'] }}</a>@if (! $loop->last)، @endif @endforeach
                @endif</span>
            @endif
            @if ($h['payer'] && $h['payer_id'] !== $h['student_id']) <span>· {{ __('scheduling::enrollments.payer', ['name' => $h['payer']]) }}</span> @endif
        </div>

        <div class="ac-cards">
            <div class="ac-card" @if ($low) data-tone="warn" @endif>
                <small>{{ __('scheduling::enrollments.cards.left') }}</small>
                <strong class="lms-heading">{{ trans_choice('scheduling::enrollments.sessions', $a['sessionsLeft'], ['n' => $a['sessionsLeft']]) }}</strong>
                <small>{{ __('scheduling::enrollments.cards.minutes', ['remaining' => $a['remainingMin'], 'reserved' => $a['reservedMin']]) }}</small>
                @if ($a['block'] > 0)
                    <div class="ac-meter"><i style="width: {{ min(100, round(100 * $a['sessionsLeft'] / max(1, $a['block']))) }}%"></i></div>
                @endif
            </div>
            <div class="ac-card" @if ($openInvoice && $openInvoice['status'] === 'overdue') data-tone="warn" @endif>
                <small>{{ __('scheduling::enrollments.cards.billing') }}</small>
                @if ($openInvoice)
                    <strong class="lms-heading">{{ $openInvoice['total'] }}</strong>
                    <small>{{ __('scheduling::enrollments.cards.invoice_open', ['number' => $openInvoice['number']]) }} · {{ __('billing::billing.statuses.'.$openInvoice['status']) }}</small>
                    <a href="{{ $openInvoice['url'] }}" wire:navigate>{{ __('scheduling::enrollments.cards.open_invoice') }}</a>
                @elseif ($a['renewAt'] > 0)
                    <strong class="lms-heading">{{ __('scheduling::enrollments.cards.nothing_due') }}</strong>
                    <small>{{ trans_choice('scheduling::enrollments.cards.renews', $a['renewAt'], ['n' => $a['renewAt']]) }}</small>
                @else
                    <strong class="lms-heading">—</strong>
                @endif
            </div>
            <div class="ac-card">
                <small>{{ __('scheduling::enrollments.cards.progress') }}</small>
                <strong class="lms-heading">{{ trans_choice('scheduling::enrollments.sessions', $a['counts']['done'], ['n' => $a['counts']['done']]) }}</strong>
                <small>{{ __('scheduling::enrollments.cards.upcoming', ['n' => $a['counts']['upcoming']]) }}</small>
            </div>
            <div class="ac-card">
                <small>{{ __('scheduling::enrollments.cards.expiry') }}</small>
                @if ($a['expiry'])
                    <strong class="lms-heading">{{ ($a['fmt'])($a['expiry']->expires_at, 'j M') }}</strong>
                    <small>{{ __('scheduling::enrollments.cards.expiring', ['minutes' => $a['expiry']->remaining_min]) }}</small>
                @else
                    <strong class="lms-heading">—</strong>
                    <small>{{ __('scheduling::enrollments.cards.no_expiry') }}</small>
                @endif
                @if ($a['makeup']['minutes'] > 0)
                    <small>{{ __('scheduling::enrollments.cards.makeup', ['minutes' => $a['makeup']['minutes'], 'date' => ($a['fmt'])($a['makeup']['expires_at'], 'j M')]) }}</small>
                @endif
            </div>
        </div>

        {{-- Other modules add sections here (Learning: curriculum and progress). --}}
        {{ \Filament\Support\Facades\FilamentView::renderHook('scheduling::enrollment.account', data: ['enrollmentId' => $this->record->id]) }}

        <div class="ac-cols">
            <div class="ac" style="gap: 1.1rem">
                <section class="ac-box">
                    <h2>{{ __('scheduling::enrollments.weekly.title') }}</h2>
                    <p class="ac-muted" style="margin:0">{{ __('scheduling::enrollments.weekly.hint', ['student' => $city($a['studentTz']), 'teacher' => $city($h['teacher_tz'] ?? 'UTC')]) }}</p>
                    <div class="ac-times">
                        @forelse ($a['weekly'] as $t)
                            <div class="ac-time">
                                <strong>{{ __('shared::time.days.'.$t['student_day']) }} {{ $t['student_time'] }}</strong>
                                <small>{{ __('scheduling::enrollments.weekly.teacher', ['day' => __('shared::time.days.'.$t['teacher_day']), 'time' => $t['teacher_time']]) }} · {{ $t['minutes'] }} {{ __('scheduling::enrollments.min') }}</small>
                            </div>
                        @empty
                            <span class="ac-muted">—</span>
                        @endforelse
                    </div>
                </section>

                <section class="ac-box">
                    <h2>{{ __('scheduling::enrollments.upcoming.title') }}</h2>
                    <p class="ac-muted" style="margin:0">{{ __('scheduling::enrollments.upcoming.hint', ['city' => $city($a['tz'])]) }}</p>
                    <table>
                        <tbody>
                            @forelse ($a['upcoming'] as $s)
                                <tr>
                                    <td>
                                        {{ $s['when'] }}
                                        @if ($s['type'] === 'makeup') <span class="ac-pill" data-s="makeup">{{ __('scheduling::enrollments.types.makeup') }}</span> @endif
                                        @if ($s['moved']) <span class="ac-muted">· {{ __('scheduling::enrollments.row.moved') }}</span> @endif
                                    </td>
                                    <td class="num">
                                        @if ($s['status'] !== 'scheduled')
                                            <span class="ac-pill" data-s="{{ $s['status'] }}">{{ __('scheduling::enrollments.session_statuses.'.$s['status']) }}</span>
                                        @else
                                            <span class="ac-pill" data-s="{{ $s['funding'] }}">{{ __('scheduling::enrollments.funding.'.$s['funding']) }}</span>
                                        @endif
                                    </td>
                                    <td class="num ac-row-actions">
                                        @if ($s['status'] === 'scheduled' && $s['future'])
                                            {{ ($this->rescheduleAction)(['session' => $s['id']]) }}
                                            {{ ($this->cancelSessionAction)(['session' => $s['id']]) }}
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td class="ac-muted">{{ __('scheduling::enrollments.upcoming.none') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </section>
            </div>

            <div class="ac" style="gap: 1.1rem">
                <section class="ac-box">
                    <h2>{{ __('scheduling::enrollments.statement.title') }}</h2>
                    <p class="ac-muted" style="margin:0">{{ __('scheduling::enrollments.statement.hint') }}</p>
                    <table>
                        <thead>
                            <tr>
                                <th>{{ __('scheduling::enrollments.statement.when') }}</th>
                                <th>{{ __('scheduling::enrollments.statement.what') }}</th>
                                <th class="num">{{ __('scheduling::enrollments.statement.minutes') }}</th>
                                <th class="num">{{ __('scheduling::enrollments.statement.left') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($a['statement'] as $e)
                                <tr>
                                    <td class="ac-muted">{{ $e['when'] }}</td>
                                    <td>
                                        {{ __('credits::credits.entries.'.$e['type']) }}@if ($e['type'] === 'grant' && $e['source']) · {{ __('credits::credits.sources.'.$e['source']) }}@endif
                                        @if ($e['mode']) <span class="ac-muted">· {{ __('credits::credits.release_modes.'.$e['mode']) }}</span> @endif
                                        @if ($e['ref']) <div class="ac-muted">{{ $e['ref'] }}</div> @endif
                                    </td>
                                    <td class="num {{ $e['sign'] === '+' ? 'plus' : ($e['sign'] === '−' ? 'minus' : 'ac-muted') }}">{{ $e['sign'] }}{{ $e['minutes'] }}</td>
                                    <td class="num">{{ $e['available'] + $e['reserved'] }}</td>
                                </tr>
                            @empty
                                <tr><td class="ac-muted" colspan="4">{{ __('scheduling::enrollments.statement.none') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </section>

                <section class="ac-box">
                    <h2>{{ __('scheduling::enrollments.invoices.title') }}</h2>
                    <table>
                        <tbody>
                            @forelse ($a['invoices'] as $i)
                                <tr>
                                    <td><a href="{{ $i['url'] }}" wire:navigate>{{ $i['number'] }}</a>@if ($i['family']) <span class="ac-muted">· {{ __('scheduling::enrollments.invoices.family') }}</span>@endif</td>
                                    <td class="ac-muted">{{ $i['issued'] }}</td>
                                    <td class="num">{{ $i['total'] }}</td>
                                    <td class="num"><span class="ac-pill" data-s="{{ $i['status'] }}">{{ __('billing::billing.statuses.'.$i['status']) }}</span></td>
                                </tr>
                            @empty
                                <tr><td class="ac-muted">—</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </section>
            </div>
        </div>
    </div>
</x-filament-panels::page>
