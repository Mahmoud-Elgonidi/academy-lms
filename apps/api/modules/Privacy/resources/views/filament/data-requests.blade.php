@php
    $l = 'privacy::screens.office.';
    $board = $this->board;
    $outcome = $this->outcome();
@endphp
<x-filament-panels::page>
    <style>
        .dr-h2 { font-size: 1rem; font-weight: 700; margin: 0 0 .6rem; }
        .dr-stack { display: flex; flex-direction: column; gap: 1rem; }
        .dr-card { background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl, .75rem); padding: 1rem 1.1rem; display: flex; flex-direction: column; gap: .7rem; }
        .dr-card[data-focus] { border-color: var(--primary-400); box-shadow: 0 0 0 3px var(--primary-50); }
        .dr-card[data-late] { border-color: var(--danger-300); }
        .dr-head { display: flex; justify-content: space-between; gap: .75rem; align-items: flex-start; flex-wrap: wrap; }
        .dr-head strong { display: block; font-size: 1.02rem; }
        .dr-muted { color: var(--gray-500); font-size: .84rem; }
        .dr-pill { font-size: .72rem; font-weight: 600; padding: .1rem .55rem; border-radius: 999px; background: var(--gray-100); color: var(--gray-700); white-space: nowrap; }
        .dr-pill[data-tone="warn"] { background: var(--warning-50); color: var(--warning-800); }
        .dr-pill[data-tone="bad"] { background: var(--danger-50); color: var(--danger-700); }
        .dr-pill[data-tone="good"] { background: var(--success-50); color: var(--success-700); }
        .dr-reason { margin: 0; padding: .55rem .7rem; border-radius: var(--radius-lg, .5rem); background: var(--gray-50); font-size: .9rem; white-space: pre-line; }
        .dr-block { border-radius: var(--radius-lg, .5rem); padding: .6rem .75rem; font-size: .88rem; }
        .dr-block[data-tone="warn"] { background: var(--warning-50); color: var(--warning-900); }
        .dr-block[data-tone="good"] { background: var(--success-50); color: var(--success-800); }
        .dr-block strong { display: block; font-size: .8rem; margin-bottom: .2rem; }
        .dr-block ul { margin: 0; padding-inline-start: 1.1rem; }
        .dr-actions { display: flex; gap: .6rem; align-items: center; flex-wrap: wrap; border-top: 1px solid var(--gray-100); padding-top: .65rem; }
        .dr-outcome { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 18rem), 1fr)); gap: .75rem; font-size: .85rem; }
        .dr-outcome ul { margin: .2rem 0 0; padding-inline-start: 1.1rem; color: var(--gray-600); }
        .dr-empty { padding: 1.25rem; text-align: center; color: var(--gray-500); border: 1px dashed var(--gray-300); border-radius: var(--radius-xl, .75rem); }
        .dr-scroll { overflow-x: auto; background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl, .75rem); }
        .dr-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
        .dr-table th { text-align: start; color: var(--gray-500); font-weight: 500; font-size: .75rem; padding: .45rem .6rem; border-bottom: 1px solid var(--gray-200); white-space: nowrap; }
        .dr-table td { padding: .55rem .6rem; border-bottom: 1px solid var(--gray-100); vertical-align: top; }
        .dr-table td.num { white-space: nowrap; font-variant-numeric: tabular-nums; }
        .dark .dr-card, .dark .dr-scroll { background: var(--gray-900); border-color: var(--gray-800); }
        .dark .dr-reason { background: var(--gray-800); }
    </style>

    <section>
        <h2 class="dr-h2">{{ __($l.'open') }}</h2>
        @if ($board['open'] === [])
            <div class="dr-empty">{{ __($l.'none_open') }}</div>
        @else
            <div class="dr-stack lms-stagger">
                @foreach ($board['open'] as $r)
                    <article class="dr-card" wire:key="open-{{ $r['id'] }}" @if ($r['id'] === $this->request) data-focus @endif @if ($r['overdue']) data-late @endif>
                        <div class="dr-head">
                            <div>
                                <strong>{{ $r['subject'] }}</strong>
                                <span class="dr-muted">
                                    {{ $r['requested_by'] === $r['subject_id'] ? __($l.'themselves') : __($l.'asked_by', ['name' => $r['requester']]) }}
                                    · {{ __($l.'asked_on', ['when' => $this->when($r['created_at'])]) }}
                                </span>
                            </div>
                            @if ($r['status'] === 'approved')
                                <span class="dr-pill" data-tone="bad">{{ __($l.'erase_at', ['when' => $this->when($r['erase_after'], true)]) }}</span>
                            @elseif ($r['overdue'])
                                <span class="dr-pill" data-tone="bad">{{ __($l.'overdue', ['when' => $this->when($r['due_at'])]) }}</span>
                            @else
                                <span class="dr-pill" data-tone="warn">{{ __($l.'due', ['when' => $this->when($r['due_at'])]) }}</span>
                            @endif
                        </div>

                        @if ($r['reason'])
                            <p class="dr-reason"><span class="dr-muted">{{ __($l.'reason') }}:</span> {{ $r['reason'] }}</p>
                        @endif

                        @if ($r['status'] === 'pending')
                            @if ($r['held_back'] !== [])
                                <div class="dr-block" data-tone="warn"><strong>{{ __($l.'held_back') }}</strong><ul>@foreach ($r['held_back'] as $b)<li>{{ $b }}</li>@endforeach</ul></div>
                            @endif
                            @if ($r['blockers'] !== [])
                                <div class="dr-block" data-tone="warn"><strong>{{ __($l.'blocked') }}</strong><ul>@foreach ($r['blockers'] as $b)<li>{{ $b }}</li>@endforeach</ul></div>
                            @else
                                <div class="dr-block" data-tone="good">{{ __($l.'clear') }}</div>
                            @endif
                        @endif

                        <div class="dr-actions">
                            @if ($r['status'] === 'pending')
                                {{ ($this->approveAction)(['id' => $r['id']])->disabled($r['blockers'] !== []) }}
                                {{ ($this->refuseAction)(['id' => $r['id']]) }}
                            @endif
                            {{ ($this->cancelAction)(['id' => $r['id']]) }}
                        </div>
                    </article>
                @endforeach
            </div>
            <div class="dr-outcome" style="margin-top: 1rem">
                <div><strong>{{ __($l.'goes') }}</strong><ul>@foreach ($outcome['goes'] as $g)<li>{{ $g }}</li>@endforeach</ul></div>
                <div><strong>{{ __($l.'stays') }}</strong><ul>@foreach ($outcome['stays'] as $g)<li>{{ $g }}</li>@endforeach</ul><p class="dr-muted">{{ __('privacy::requests.stays_note') }}</p></div>
            </div>
        @endif
    </section>

    <section>
        <h2 class="dr-h2">{{ __($l.'recent') }}</h2>
        @if ($board['recent'] === [])
            <div class="dr-empty">{{ __($l.'none_recent') }}</div>
        @else
            <div class="dr-scroll">
                <table class="dr-table">
                    <thead><tr>
                        @foreach (['kind', 'person', 'requester', 'status', 'asked', 'answered'] as $c)<th>{{ __($l.'columns.'.$c) }}</th>@endforeach
                        <th></th>
                    </tr></thead>
                    <tbody>
                        @foreach ($board['recent'] as $r)
                            <tr wire:key="recent-{{ $r['id'] }}">
                                <td>{{ __('privacy::requests.kinds.'.$r['kind']) }}</td>
                                <td>{{ $r['subject'] ?: '—' }}</td>
                                <td>{{ $r['requested_by'] === $r['subject_id'] ? __($l.'themselves') : $r['requester'] }}</td>
                                <td>
                                    <span class="dr-pill" data-tone="{{ match ($r['status']) { 'ready', 'done' => 'good', 'refused', 'failed' => 'bad', default => '' } }}">{{ __('privacy::requests.statuses.'.$r['status']) }}</span>
                                    @if ($r['answer'])<div class="dr-muted">{{ $r['answer'] }}</div>@endif
                                </td>
                                <td class="num">{{ $this->when($r['created_at']) }}</td>
                                <td class="num">{{ $this->when($r['decided_at'] ?? $r['done_at']) ?? '—' }}@if ($r['decided_by'])<div class="dr-muted">{{ $r['decided_by'] }}</div>@endif</td>
                                <td>@if ($r['download_url'])<x-filament::link :href="$r['download_url']" icon="heroicon-o-arrow-down-tray" size="sm">{{ __('privacy::screens.mine.copy.download') }}</x-filament::link>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <x-filament-actions::modals />
</x-filament-panels::page>
