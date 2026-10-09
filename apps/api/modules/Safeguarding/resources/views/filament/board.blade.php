@php
    $l = 'safeguarding::screens.office.';
    $c = 'safeguarding::concerns.';
    $b = $this->board;
    $s = $this->selected;
@endphp
<x-filament-panels::page>
    <div data-live-channel="{{ $b['channel'] }}" hidden></div>
    <style>
        .sg-wrap { display: grid; grid-template-columns: minmax(0, 20rem) minmax(0, 1fr); gap: 1.1rem; align-items: start; }
        @media (max-width: 64rem) { .sg-wrap { grid-template-columns: minmax(0, 1fr); } }
        .sg-h2 { font-size: .95rem; font-weight: 700; margin: 0 0 .5rem; }
        .sg-list { display: flex; flex-direction: column; gap: .5rem; }
        .sg-item { text-align: start; width: 100%; background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-lg, .5rem); padding: .6rem .75rem; display: flex; flex-direction: column; gap: .2rem; cursor: pointer; }
        .sg-item[data-on] { border-color: var(--primary-500); box-shadow: 0 0 0 3px var(--primary-50); }
        .sg-item[data-urgent] { border-inline-start: 4px solid var(--danger-500); }
        .sg-item .sg-top { display: flex; justify-content: space-between; gap: .5rem; align-items: center; }
        .sg-muted { color: var(--gray-500); font-size: .8rem; }
        .sg-pill { font-size: .7rem; font-weight: 600; padding: .08rem .5rem; border-radius: 999px; background: var(--gray-100); color: var(--gray-700); white-space: nowrap; }
        .sg-pill[data-tone="new"] { background: var(--danger-50); color: var(--danger-700); }
        .sg-pill[data-tone="open"] { background: var(--warning-50); color: var(--warning-800); }
        .sg-pill[data-tone="closed"] { background: var(--success-50); color: var(--success-700); }
        .sg-card { background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl, .75rem); padding: 1.1rem 1.2rem; display: flex; flex-direction: column; gap: .9rem; }
        .sg-title { display: flex; justify-content: space-between; gap: .75rem; flex-wrap: wrap; align-items: flex-start; }
        .sg-title h2 { margin: 0; font-size: 1.15rem; font-weight: 700; }
        .sg-facts { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 12rem), 1fr)); gap: .6rem; font-size: .88rem; }
        .sg-facts dt { color: var(--gray-500); font-size: .76rem; }
        .sg-facts dd { margin: 0; font-weight: 600; }
        .sg-body { margin: 0; padding: .75rem .9rem; border-radius: var(--radius-lg, .5rem); background: var(--gray-50); white-space: pre-line; line-height: 1.7; }
        .sg-urgent { padding: .6rem .8rem; border-radius: var(--radius-lg, .5rem); background: var(--danger-50); color: var(--danger-800); font-weight: 600; font-size: .9rem; }
        .sg-clear { padding: .55rem .75rem; border-radius: var(--radius-lg, .5rem); font-size: .86rem; display: flex; justify-content: space-between; gap: .6rem; align-items: center; flex-wrap: wrap; }
        .sg-clear[data-tone="good"] { background: var(--success-50); color: var(--success-800); }
        .sg-clear[data-tone="warn"] { background: var(--warning-50); color: var(--warning-900); }
        .sg-clear[data-tone="bad"] { background: var(--danger-50); color: var(--danger-800); }
        .sg-actions { display: flex; gap: .5rem; flex-wrap: wrap; border-top: 1px solid var(--gray-100); padding-top: .75rem; }
        .sg-steps { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: .55rem; }
        .sg-steps li { font-size: .87rem; padding-inline-start: .85rem; border-inline-start: 2px solid var(--gray-200); }
        .sg-steps li[data-kind="note"] { border-color: var(--primary-300); }
        .sg-steps .sg-note { white-space: pre-line; margin-top: .2rem; }
        .sg-empty { padding: 1.1rem; text-align: center; color: var(--gray-500); border: 1px dashed var(--gray-300); border-radius: var(--radius-xl, .75rem); font-size: .9rem; }
        .sg-private { font-size: .8rem; color: var(--gray-500); display: flex; gap: .35rem; align-items: center; }
        .dark .sg-item, .dark .sg-card { background: var(--gray-900); border-color: var(--gray-800); }
        .dark .sg-body { background: var(--gray-800); }
    </style>

    <div class="sg-wrap">
        <aside>
            <h2 class="sg-h2">{{ __($l.'open') }}</h2>
            @if ($b['open'] === [])
                <div class="sg-empty">{{ __($l.'none_open') }}</div>
            @else
                <div class="sg-list lms-stagger">
                    @foreach ($b['open'] as $r)
                        <button type="button" class="sg-item" wire:key="o-{{ $r['id'] }}" wire:click="show('{{ $r['id'] }}')" @if ($r['id'] === $this->concern) data-on @endif @if ($r['urgent']) data-urgent @endif>
                            <span class="sg-top"><strong>{{ $r['number'] }}</strong><span class="sg-pill" data-tone="{{ $r['status']->value }}">{{ __($c.'status.'.$r['status']->value) }}</span></span>
                            <span>{{ $r['category']->label() }}</span>
                            <span class="sg-muted">{{ $r['student'] ?? '—' }} · {{ $this->when($r['created_at']) }}@if ($r['escalated']) · {{ __($l.'waiting_hour') }}@endif</span>
                        </button>
                    @endforeach
                </div>
            @endif

            @if ($b['closed'] !== [])
                <details style="margin-top: 1rem">
                    <summary class="sg-h2" style="cursor: pointer">{{ __($l.'closed', ['days' => \Modules\Safeguarding\Queries\ConcernBoard::CLOSED_DAYS]) }}</summary>
                    <div class="sg-list">
                        @foreach ($b['closed'] as $r)
                            <button type="button" class="sg-item" wire:key="c-{{ $r['id'] }}" wire:click="show('{{ $r['id'] }}')" @if ($r['id'] === $this->concern) data-on @endif>
                                <span class="sg-top"><strong>{{ $r['number'] }}</strong><span class="sg-pill" data-tone="closed">{{ $r['outcome']?->label() }}</span></span>
                                <span class="sg-muted">{{ $r['category']->label() }} · {{ $this->when($r['closed_at']) }}</span>
                            </button>
                        @endforeach
                    </div>
                </details>
            @endif
        </aside>

        <section>
            @if ($s === null)
                <div class="sg-empty">{{ __($l.'pick') }}</div>
            @else
                @php $x = $s['concern']; @endphp
                <article class="sg-card" wire:key="d-{{ $x->id }}">
                    <div class="sg-title">
                        <div>
                            <h2>{{ $x->reference() }} · {{ $x->category->label() }}</h2>
                            <span class="sg-muted">{{ __($l.'raised_by', ['who' => $s['reporter'], 'side' => __($c.'sides.'.$x->reporter_side), 'when' => $this->when($x->created_at)]) }}</span>
                        </div>
                        <span class="sg-pill" data-tone="{{ $x->status->value }}">{{ __($c.'status.'.$x->status->value) }}</span>
                    </div>

                    @if ($x->urgent)<div class="sg-urgent">{{ __($l.'urgent') }}</div>@endif

                    <dl class="sg-facts">
                        <div><dt>{{ __($c.'fields.student') }}</dt><dd>{{ $s['student'] ?? '—' }}</dd></div>
                        <div><dt>{{ __($c.'fields.teacher') }}</dt><dd>{{ $s['teacher'] ?? '—' }}</dd></div>
                        <div><dt>{{ __($c.'fields.lesson') }}</dt><dd>
                            @if ($s['lesson'])
                                <x-filament::link :href="route('filament.academy.pages.calendar', ['teacher' => $s['lesson']->teacherId, 'date' => $this->day($s['lesson']->startsAt), 'unit' => 'day'])" size="sm">{{ $s['lesson']->subject() }} · {{ $this->when($s['lesson']->startsAt) }}</x-filament::link>
                            @else — @endif
                        </dd></div>
                    </dl>

                    <p class="sg-body">{{ $x->body !== '' ? $x->body : __($l.'no_words') }}</p>

                    @if ($s['clearance'])
                        <div class="sg-clear" data-tone="{{ \Modules\People\Queries\TeacherChecksBoard::tone($s['clearance']) }}">
                            <span>{{ __($l.'teacher_standing', ['name' => $s['teacher'], 'state' => __('people::checks.clearance.'.$s['clearance']->state)]) }}</span>
                            @if ($s['clearance']->state === 'paused') {{ $this->resumeAction }} @else {{ $this->pauseAction }} @endif
                        </div>
                    @endif

                    <div class="sg-actions">
                        @if ($x->status->value === 'new'){{ $this->takeAction }}@endif
                        @if ($x->status->value !== 'closed')
                            {{ $this->noteAction }}
                            {{ $this->classifyAction }}
                            {{ $this->closeAction }}
                        @else
                            {{ $this->reopenAction }}
                        @endif
                    </div>

                    <div>
                        <h3 class="sg-h2">{{ __($l.'steps') }}</h3>
                        <ol class="sg-steps">
                            @foreach ($s['entries'] as $e)
                                <li data-kind="{{ $e['kind'] }}">
                                    <span class="sg-muted">{{ $this->when($e['at']) }}</span>
                                    @if ($e['kind'] === 'note')
                                        · <strong>{{ $e['by'] }}</strong>
                                        <div class="sg-note">{{ $e['body'] }}</div>
                                    @else
                                        · {{ __($c.'events.'.$e['event'], ['by' => $e['by'] ?? '—', 'outcome' => isset($e['data']['outcome']) ? \Modules\Safeguarding\Enums\ConcernOutcome::from($e['data']['outcome'])->label() : '']) }}
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    </div>
                    @if ($x->reporter_reply)
                        <div><h3 class="sg-h2">{{ __($l.'reply_sent') }}</h3><p class="sg-body">{{ $x->reporter_reply }}</p></div>
                    @endif
                    <span class="sg-private"><x-filament::icon icon="heroicon-o-lock-closed" class="h-4 w-4" />{{ __($l.'private') }}</span>
                </article>
            @endif
        </section>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>

@script
<script>
    // A concern comes in, a colleague writes on one: the page follows the academy's safeguarding channel (LmsLive).
    const el = $wire.$el.querySelector('[data-live-channel]');
    if (el && window.LmsLive) {
        window.LmsLive.watch({ el, channel: el.dataset.liveChannel, event: '.changed', refresh: () => $wire.$refresh(), every: 60000 });
    }
</script>
@endscript
