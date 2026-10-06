@php
    $r = $this->review;
    [$from, $to] = $r['range'];
    $arrow = __('scheduling::shifts.arrow');
@endphp

<x-filament-panels::page>
    <style>
        .rv { display: flex; flex-direction: column; gap: 1.1rem; }
        .rv-sub { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; color: var(--gray-600); font-size: .875rem; margin-top: -.75rem; }
        .rv-pill { padding: .1rem .6rem; border-radius: 999px; font-size: .75rem; font-weight: 600; background: var(--warning-50); color: var(--warning-800); }
        .rv-pill[data-status="approved"] { background: var(--primary-50); color: var(--primary-700); }
        .rv-pill[data-status="countered"], .rv-pill[data-status="changes_requested"] { background: var(--info-50); color: var(--info-700); }
        .rv-pill[data-status="rejected"], .rv-pill[data-status="withdrawn"] { background: var(--gray-100); color: var(--gray-700); }
        .rv-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: .75rem; }
        .rv-card { background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl); padding: .9rem 1rem; display: flex; flex-direction: column; gap: .15rem; }
        .rv-card small { color: var(--gray-500); font-size: .8rem; }
        .rv-card strong { font-size: 1.25rem; }
        .rv-good { color: var(--primary-700); }
        .rv-bad { color: var(--danger-700); }
        .rv-cols { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1.1rem; align-items: start; }
        @media (min-width: 1250px) { .rv-cols { grid-template-columns: minmax(0, 1.55fr) minmax(360px, 1fr); } }
        .rv-box { background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl); padding: 1rem; display: flex; flex-direction: column; gap: .75rem; }
        .rv-box h2 { margin: 0; font-size: 1rem; font-weight: 700; }
        .rv-box .rv-muted { color: var(--gray-500); font-size: .8125rem; margin: 0; }
        .rv-legend { display: flex; flex-wrap: wrap; gap: .75rem; font-size: .75rem; color: var(--gray-600); }
        .rv-legend i { display: inline-block; width: .8rem; height: .55rem; border-radius: 3px; margin-inline-end: .3rem; vertical-align: middle; }
        .rv-week { display: grid; grid-template-columns: 4.5rem minmax(0, 1fr) 4.5rem; gap: .35rem .6rem; align-items: center; }
        .rv-scale { position: relative; height: 1rem; font-size: .65rem; color: var(--gray-500); }
        .rv-scale span { position: absolute; transform: translateX(50%); }
        [dir="ltr"] .rv-scale span { transform: translateX(-50%); }
        .rv-day { font-size: .8125rem; font-weight: 600; }
        .rv-track { position: relative; height: 1.7rem; border-radius: 6px; background: repeating-linear-gradient(90deg, var(--gray-50), var(--gray-50) calc(100% / {{ $to - $from }} - 1px), var(--gray-200) calc(100% / {{ $to - $from }} - 1px), var(--gray-200) calc(100% / {{ $to - $from }})); }
        .rv-span { position: absolute; top: 3px; bottom: 3px; border-radius: 4px; }
        .rv-span[data-kind="same"], .rv-legend [data-kind="same"] { background: var(--gray-400); }
        .rv-span[data-kind="added"], .rv-legend [data-kind="added"] { background: var(--primary-500); }
        .rv-span[data-kind="removed"], .rv-legend [data-kind="removed"] { background: repeating-linear-gradient(135deg, var(--danger-200), var(--danger-200) 4px, var(--danger-400) 4px, var(--danger-400) 8px); }
        .rv-dot { position: absolute; top: -4px; width: .7rem; height: .7rem; border-radius: 50%; background: var(--danger-600); border: 2px solid white; }
        .rv-legend [data-kind="dot"] { background: var(--danger-600); border-radius: 50%; width: .6rem; height: .6rem; }
        .rv-hours { font-size: .75rem; color: var(--gray-500); font-variant-numeric: tabular-nums; }
        .rv-conflict { border: 1px solid var(--gray-200); border-radius: var(--radius-lg); padding: .75rem; display: flex; flex-direction: column; gap: .45rem; }
        .rv-conflict[data-resolved="false"] { border-color: var(--danger-200); background: var(--danger-50); }
        .rv-conflict-head { display: flex; justify-content: space-between; gap: .5rem; }
        .rv-conflict small { color: var(--gray-600); font-size: .78rem; }
        .rv-choices { display: flex; flex-wrap: wrap; gap: .35rem; }
        .rv-choices button { padding: .3rem .7rem; border-radius: var(--radius-md); border: 1px solid var(--gray-300); background: white; font-size: .78rem; }
        .rv-choices button[aria-pressed="true"] { background: var(--primary-600); border-color: var(--primary-600); color: white; font-weight: 600; }
        .rv-choices button[disabled] { opacity: .5; cursor: not-allowed; }
        .rv-choices .rv-move { padding: .3rem .7rem; border-radius: var(--radius-md); border: 1px solid var(--primary-300); color: var(--primary-700); font-size: .78rem; font-weight: 600; }
        .rv-state { font-size: .72rem; font-weight: 700; }
        .rv-thread { display: flex; flex-direction: column; gap: .6rem; max-height: 26rem; overflow-y: auto; }
        .rv-msg { max-width: 88%; padding: .55rem .75rem; border-radius: var(--radius-lg); font-size: .85rem; background: var(--gray-100); align-self: flex-start; }
        .rv-msg[data-role="admin"] { background: var(--primary-50); align-self: flex-end; }
        .rv-msg[data-role="system"] { background: transparent; border: 1px dashed var(--gray-300); color: var(--gray-600); align-self: center; font-size: .78rem; }
        .rv-msg header { font-size: .72rem; color: var(--gray-500); margin-bottom: .15rem; }
        .rv-reply { display: flex; flex-direction: column; gap: .4rem; }
        .rv-reply textarea { width: 100%; border: 1px solid var(--gray-300); border-radius: var(--radius-lg); padding: .5rem .7rem; font: inherit; font-size: .875rem; background: transparent; }
        .rv-reply div { display: flex; justify-content: space-between; align-items: center; gap: .5rem; }
        .rv-note { font-size: .8125rem; padding: .6rem .8rem; border-radius: var(--radius-lg); background: var(--primary-50); color: var(--primary-800); }
        .rv-note[data-tone="warn"] { background: var(--warning-50); color: var(--warning-800); }
        .dark .rv-card, .dark .rv-box { background: var(--gray-900); border-color: var(--gray-800); }
        .dark .rv-conflict[data-resolved="false"] { background: color-mix(in oklab, var(--danger-500) 10%, transparent); border-color: var(--danger-800); }
        .dark .rv-choices button { background: var(--gray-900); }
    </style>

    <div class="rv">
        <div class="rv-sub">
            <span>{{ $r['subjects'] ?: '—' }} · {{ $r['city'] }} ({{ $r['tz'] }})</span>
            @if ($r['sent']) <span>· {{ __('scheduling::review.sent_at', ['when' => $r['sent']]) }}</span> @endif
            <span class="rv-pill" data-status="{{ $r['status']->value }}">{{ __('scheduling::shifts.statuses.'.$r['status']->value) }}</span>
            @if ($r['revision'] > 1) <span>· {{ __('scheduling::review.revision', ['n' => $r['revision'], 'by' => __('scheduling::shifts.roles.'.$r['author'])]) }}</span> @endif
        </div>

        <div class="rv-cards">
            <div class="rv-card"><small>{{ __('scheduling::review.starts') }}</small><strong class="lms-heading">{{ $r['from'] }}</strong></div>
            <div class="rv-card">
                <small>{{ __('scheduling::review.weekly_hours') }}</small>
                <strong class="lms-heading">{{ $r['hours_before'] }} {{ $arrow }} {{ $r['hours_after'] }}</strong>
                <small @class(['rv-good' => $r['delta'] > 0, 'rv-bad' => $r['delta'] < 0])>{{ $r['delta'] === 0 ? __('scheduling::review.same_hours') : ($r['delta'] > 0 ? '+' : '−').\Modules\Scheduling\Domain\Shifts\ProposalRules::hours(abs($r['delta'])).' '.__('scheduling::review.hours') }}</small>
            </div>
            <div class="rv-card">
                <small>{{ __('scheduling::review.notice', ['days' => $r['notice_days']]) }}</small>
                @if (! $r['reduces'])
                    <strong class="rv-good">{{ __('scheduling::review.notice_not_needed') }}</strong><small>{{ __('scheduling::review.only_adds') }}</small>
                @else
                    <strong @class(['rv-good' => $r['notice_ok'], 'rv-bad' => ! $r['notice_ok']])>{{ $r['notice_ok'] ? __('scheduling::review.notice_ok') : __('scheduling::review.notice_short') }}</strong>
                    <small>{{ trans_choice('scheduling::review.lead_days', $r['lead_days'], ['days' => $r['lead_days']]) }}</small>
                @endif
            </div>
            <div class="rv-card">
                <small>{{ __('scheduling::review.impact') }}</small>
                <strong @class(['rv-good' => $r['unresolved'] === 0, 'rv-bad' => $r['unresolved'] > 0])>{{ count($r['conflicts']) === 0 ? __('scheduling::review.no_conflicts') : trans_choice('scheduling::review.conflicts_count', count($r['conflicts']), ['count' => count($r['conflicts'])]) }}</strong>
                @if (count($r['conflicts']) > 0)<small>{{ $r['unresolved'] > 0 ? __('scheduling::review.unresolved_count', ['count' => $r['unresolved']]) : __('scheduling::review.all_resolved') }}</small>@endif
            </div>
        </div>

        <div class="rv-cols">
            <div class="rv" style="gap: 1.1rem">
                <section class="rv-box">
                    <div>
                        <h2 class="lms-heading">{{ __('scheduling::review.compare') }}</h2>
                        <p class="rv-muted">{{ __('scheduling::review.compare_hint', ['city' => $r['city'], 'from' => sprintf('%02d:00', $from), 'to' => sprintf('%02d:00', $to)]) }}</p>
                    </div>
                    <div class="rv-legend">
                        <span><i data-kind="same"></i>{{ __('scheduling::review.legend.same') }}</span>
                        <span><i data-kind="added"></i>{{ __('scheduling::review.legend.added') }}</span>
                        <span><i data-kind="removed"></i>{{ __('scheduling::review.legend.removed') }}</span>
                        <span><i data-kind="dot"></i>{{ __('scheduling::review.legend.booked') }}</span>
                    </div>
                    <div class="rv-week">
                        <span></span>
                        <div class="rv-scale">
                            @for ($h = $from; $h <= $to; $h += 2)
                                <span style="inset-inline-start: {{ 100 * ($h - $from) / ($to - $from) }}%">{{ sprintf('%02d', $h) }}</span>
                            @endfor
                        </div>
                        <span class="rv-hours">{{ __('scheduling::review.hours') }}</span>
                        @foreach ($r['days'] as $day)
                            <span class="rv-day">{{ __('scheduling::shifts.days.'.$day) }}</span>
                            <div class="rv-track">
                                @foreach ($r['spans'][$day] as $span)
                                    <span class="rv-span" data-kind="{{ $span['kind'] }}" style="{{ $this->place($span['start'], $span['end']) }}" title="{{ $span['start'] }}–{{ $span['end'] }}"></span>
                                @endforeach
                                @foreach ($r['conflicts'] as $c)
                                    @if ($c['weekday'] === $day)
                                        <span class="rv-dot" style="{{ $this->place($c['start'], $c['start']) }}" title="{{ $c['student'] }} {{ $c['start'] }}"></span>
                                    @endif
                                @endforeach
                            </div>
                            <span class="rv-hours">{{ $r['day_hours'][$day][0] }} {{ $arrow }} {{ $r['day_hours'][$day][1] }}</span>
                        @endforeach
                    </div>
                </section>

                @if (count($r['conflicts']) > 0)
                    <section class="rv-box">
                        <div>
                            <h2 class="lms-heading">{{ __('scheduling::review.impact_title') }}</h2>
                            <p class="rv-muted">{{ trans_choice('scheduling::review.impact_hint', count($r['conflicts']), ['count' => count($r['conflicts'])]) }}</p>
                        </div>
                        @foreach ($r['conflicts'] as $c)
                            <div class="rv-conflict" data-resolved="{{ $c['resolution'] ? 'true' : 'false' }}" wire:key="c-{{ md5($c['key']) }}">
                                <div class="rv-conflict-head">
                                    <div>
                                        <strong>{{ $c['student'] }}</strong> · {{ $c['subject'] }}<br>
                                        <small>{{ $c['teacher_label'] }} · {{ $c['student_label'] }} · {{ trans_choice('scheduling::review.sessions_count', $c['count'], ['count' => $c['count']]) }} ({{ $c['range'] }})</small>
                                    </div>
                                    <span class="rv-state {{ $c['resolution'] ? 'rv-good' : 'rv-bad' }}">{{ $c['resolution'] ? __('scheduling::review.resolved') : __('scheduling::review.needs_resolution') }}</span>
                                </div>
                                @if ($r['status']->isOpen())
                                    <div class="rv-choices">
                                        @if ($c['move_url'])
                                            <a href="{{ $c['move_url'] }}" wire:navigate class="rv-move" title="{{ __('scheduling::review.resolution_hints.moved') }}">{{ __('scheduling::review.resolutions.moved') }}</a>
                                        @else
                                            <button type="button" disabled title="{{ __('scheduling::review.move_unenrolled') }}">{{ __('scheduling::review.resolutions.moved') }}</button>
                                        @endif
                                        @foreach (['exception', 'delay'] as $kind)
                                            <button type="button" wire:click="resolve(@js($c['key']), '{{ $kind }}')" aria-pressed="{{ $c['resolution'] === $kind ? 'true' : 'false' }}" title="{{ __('scheduling::review.resolution_hints.'.$kind) }}">
                                                {{ __('scheduling::review.resolutions.'.$kind) }}
                                            </button>
                                        @endforeach
                                        @if ($c['resolution'])
                                            <button type="button" wire:click="resolve(@js($c['key']), null)">{{ __('scheduling::review.undo') }}</button>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </section>
                @endif
            </div>

            <section class="rv-box">
                <h2 class="lms-heading">{{ __('scheduling::review.thread') }}</h2>
                @if ($r['status']->value === 'countered')
                    <div class="rv-note" data-tone="warn">{{ __('scheduling::review.waiting_teacher') }}</div>
                @elseif ($r['status']->value === 'submitted' && $r['unresolved'] === 0)
                    <div class="rv-note">{{ __('scheduling::review.ready', ['date' => $r['from']]) }}</div>
                @endif
                <div class="rv-thread">
                    @forelse ($r['comments'] as $m)
                        <div class="rv-msg" data-role="{{ $m['role'] }}">
                            @if ($m['role'] !== 'system')<header>{{ $m['author'] }} · {{ $m['ago'] }}</header>@endif
                            {{ $m['body'] }}
                        </div>
                    @empty
                        <p class="rv-muted">{{ __('scheduling::review.no_messages') }}</p>
                    @endforelse
                </div>
                @if ($r['status']->isOpen())
                    <form class="rv-reply" wire:submit="sendReply">
                        <textarea wire:model="reply" rows="3" placeholder="{{ __('scheduling::review.reply_placeholder') }}" aria-label="{{ __('scheduling::review.reply_placeholder') }}"></textarea>
                        <div>
                            <small class="rv-muted">{{ __('scheduling::review.reply_hint') }}</small>
                            <x-filament::button type="submit" size="sm">{{ __('scheduling::review.send') }}</x-filament::button>
                        </div>
                    </form>
                @endif
            </section>
        </div>
    </div>
</x-filament-panels::page>
