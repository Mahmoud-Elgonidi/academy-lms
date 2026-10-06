{{--
    Secondary school: a plan, calm and clear. Progress split by milestone (one
    per level), where they are, the date they should finish at their pace,
    what comes next and what to review, and the numbers behind it. The home
    opens the milestone they are in; the plan page opens them all.
--}}
@php
    $j = 'learning::journey.teen.';
    $courses = $journey['courses'] ?? [];
    $tz = app(\Modules\SharedKernel\Time\ViewerTimezone::class)->effective();
@endphp
<div class="jt lms-stagger">
    <style>
        .jt { display: flex; flex-direction: column; gap: 1rem; }
        .jt-card { background: white; border: 1px solid var(--gray-200); border-radius: .9rem; padding: 1.1rem 1.2rem; }
        .jt-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; }
        .jt-head small { display: block; color: var(--gray-500); font-size: .82rem; }
        .jt-head h3 { margin: .1rem 0; font-size: 1.2rem; font-weight: 700; letter-spacing: -.01em; }
        .jt-pct { text-align: end; }
        .jt-pct b { display: block; font-size: 2rem; font-weight: 700; line-height: 1; color: var(--gray-950); font-variant-numeric: tabular-nums; }
        .jt-bar { display: flex; gap: 3px; height: .45rem; margin-top: .9rem; }
        .jt-bar span { position: relative; border-radius: 999px; background: var(--gray-200); overflow: hidden; }
        .jt-bar i { position: absolute; inset-block: 0; inset-inline-start: 0; background: var(--primary-600); border-radius: inherit; }
        .jt-grid { display: grid; gap: 1.25rem; margin-top: 1.1rem; }
        @media (min-width: 1024px) { .jt-grid { grid-template-columns: minmax(0, 1fr) 19rem; } }
        .jt-label { margin: 0 0 .55rem; font-size: .72rem; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--gray-500); }
        .jt-steps { margin: 0; padding: 0; list-style: none; }
        .jt-steps > li { position: relative; display: grid; grid-template-columns: 1.6rem minmax(0, 1fr); gap: .7rem; padding-bottom: 1rem; }
        .jt-steps > li:not(:last-child)::before { content: ''; position: absolute; inset-inline-start: calc(.8rem - 1px); top: 1.7rem; bottom: .15rem; width: 2px; background: var(--gray-200); }
        .jt-steps > li[data-state="done"]:not(:last-child)::before { background: var(--primary-500); }
        .jt-mark { display: grid; place-items: center; width: 1.6rem; height: 1.6rem; border-radius: 50%; font-size: .8rem; font-weight: 700; border: 2px solid var(--gray-300); color: var(--gray-400); background: white; }
        .jt-steps > li[data-state="done"] .jt-mark { background: var(--primary-600); border-color: var(--primary-600); color: white; }
        .jt-steps > li[data-state="here"] .jt-mark { border-color: var(--primary-600); color: var(--primary-600); box-shadow: 0 0 0 4px color-mix(in oklab, var(--primary-500) 18%, transparent); }
        .jt-steps strong { display: block; font-size: .95rem; font-weight: 600; }
        .jt-steps small { color: var(--gray-500); font-size: .8rem; }
        .jt-steps > li[data-state="here"] small { color: var(--primary-700); font-weight: 600; }
        .jt-lessons { margin: .5rem 0 0; padding: 0; list-style: none; display: flex; flex-direction: column; gap: .2rem; }
        .jt-lessons li { display: flex; align-items: center; gap: .5rem; font-size: .85rem; color: var(--gray-600); }
        .jt-lessons li::before { content: ''; flex: none; width: .45rem; height: .45rem; border-radius: 50%; background: var(--gray-300); }
        .jt-lessons li[data-state="done"] { color: var(--gray-400); text-decoration: line-through; text-decoration-color: var(--gray-300); }
        .jt-lessons li[data-state="done"]::before { background: var(--primary-500); }
        .jt-lessons li[data-state="here"] { color: var(--gray-950); font-weight: 600; }
        .jt-lessons li[data-state="here"]::before { background: var(--primary-600); box-shadow: 0 0 0 3px color-mix(in oklab, var(--primary-500) 25%, transparent); }
        .jt-lessons li[data-state="review"]::before { background: var(--warning-500); }
        .jt-aside { display: flex; flex-direction: column; gap: 1rem; }
        .jt-goal { border-radius: .75rem; padding: .8rem .9rem; background: var(--gray-50); border: 1px solid var(--gray-200); }
        .jt-goal strong { display: block; font-size: 1rem; font-weight: 600; line-height: 1.5; }
        .jt-goal small { display: block; color: var(--gray-500); font-size: .8rem; margin-top: .2rem; }
        .jt-list { margin: 0; padding: 0; list-style: none; display: flex; flex-direction: column; }
        .jt-list li { display: flex; align-items: baseline; gap: .6rem; padding: .4rem 0; border-bottom: 1px solid var(--gray-100); font-size: .9rem; }
        .jt-list li:last-child { border-bottom: 0; }
        .jt-list em { font-style: normal; flex: none; font-size: .75rem; font-weight: 600; color: var(--gray-400); font-variant-numeric: tabular-nums; min-width: 1.2rem; }
        .jt-list .jt-now { margin-inline-start: auto; font-size: .7rem; font-weight: 600; color: var(--primary-700); background: var(--primary-50); border-radius: 999px; padding: .05rem .5rem; }
        .jt-muted { color: var(--gray-500); font-size: .85rem; margin: 0; }
        .jt-stats { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0; margin: 1.1rem 0 0; border-top: 1px solid var(--gray-200); }
        @media (min-width: 640px) { .jt-stats { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
        .jt-stats div { padding: .8rem .2rem 0; }
        .jt-stats dt { font-size: .75rem; color: var(--gray-500); }
        .jt-stats dd { margin: 0; font-size: 1.25rem; font-weight: 600; font-variant-numeric: tabular-nums; }
        .jt-homework { display: flex; justify-content: space-between; align-items: center; gap: 1rem; text-decoration: none; color: inherit; }
        .jt-homework strong { font-weight: 600; }
        .jt-homework small { display: block; color: var(--gray-500); font-size: .8rem; }
        .jt-homework span { flex: none; font-size: .85rem; font-weight: 600; color: var(--primary-700); }

        /* Motion, calm and quick: the milestones fill one after another, the timeline draws down to where
           they are, rows fade in, the numbers count up, and the step they are on breathes softly. */
        @media (prefers-reduced-motion: no-preference) {
            .jt-bar i { animation-duration: .8s; animation-delay: calc(.25s + var(--i) * .3s); }
            .jt-steps > li { animation: lms-rise .45s var(--lms-ease) calc(.25s + var(--i) * .12s) backwards; }
            .jt-steps > li:not(:last-child)::before { transform-origin: top center; animation: lms-grow-y .5s var(--lms-ease) calc(.45s + var(--i) * .12s) backwards; }
            .jt-steps > li[data-state="here"] .jt-mark { animation: jt-breathe 2.6s ease-in-out 1.2s infinite; }
            .jt-lessons li { animation: lms-fade .35s ease backwards; animation-delay: calc(.6s + var(--j) * .06s); }
            .jt-aside > * { animation: lms-rise .45s var(--lms-ease) backwards; }
            .jt-aside > :nth-child(1) { animation-delay: .3s; } .jt-aside > :nth-child(2) { animation-delay: .4s; } .jt-aside > :nth-child(3) { animation-delay: .5s; }
            .jt-list li { transition: background-color .2s; border-radius: .4rem; }
            .jt-list li:hover { background: var(--gray-50); }
            .jt-stats div { animation: lms-fade .4s ease .5s backwards; }
        }
        @keyframes jt-breathe { 0%, 100% { box-shadow: 0 0 0 4px color-mix(in oklab, var(--primary-500) 18%, transparent); } 50% { box-shadow: 0 0 0 7px color-mix(in oklab, var(--primary-500) 8%, transparent); } }
        .dark .jt-card { background: var(--gray-900); border-color: var(--gray-800); }
        .dark .jt-pct b, .dark .jt-lessons li[data-state="here"] { color: white; }
        .dark .jt-goal { background: var(--gray-800); border-color: var(--gray-700); }
        .dark .jt-mark { background: var(--gray-900); border-color: var(--gray-600); }
        .dark .jt-bar span, .dark .jt-steps > li:not(:last-child)::before { background: var(--gray-700); }
        .dark .jt-list li { border-color: var(--gray-800); }
        .dark .jt-stats { border-color: var(--gray-800); }
    </style>

    @forelse ($courses as $course)
        @php $map = $course['map']; @endphp
        <section class="jt-card" wire:key="jt-{{ $course['id'] }}">
            <div class="jt-head">
                <div>
                    @if ($map) <small>{{ $map['program']['name'] }}</small> @endif
                    <h3>{{ $course['subject'] }}</h3>
                    <small>{{ __('learning::journey.with', ['teacher' => $course['teacher']]) }}</small>
                </div>
                @if ($map)
                    <div class="jt-pct">
                        <b><span data-count="{{ $map['percent'] }}">{{ $map['percent'] }}</span>%</b>
                        <small>{{ __($j.'lessons', ['done' => $map['lessons']['mastered'], 'total' => $map['lessons']['total']]) }}</small>
                    </div>
                @endif
            </div>

            @if ($course['level'] === null)
                <p class="jt-muted" style="margin-top: .8rem">{{ __('learning::journey.no_program') }}</p>
            @else
                <div class="jt-bar" aria-hidden="true">
                    @foreach ($course['worlds'] as $w)
                        <span style="flex: {{ max(1, count($w['lessons'])) }}; --i: {{ $loop->index }}" title="{{ $w['title'] }}"><i class="lms-bar-fill" style="width: {{ $w['percent'] }}%"></i></span>
                    @endforeach
                </div>

                <div class="jt-grid">
                    <div>
                        <p class="jt-label">{{ __($j.'milestones') }}</p>
                        <ol class="jt-steps">
                            @foreach ($course['worlds'] as $w)
                                <li data-state="{{ $w['state'] }}" style="--i: {{ $loop->index }}" @if ($w['state'] === 'here') aria-current="step" @endif>
                                    <span class="jt-mark">{{ $w['state'] === 'done' ? '✓' : $w['index'] }}</span>
                                    <div>
                                        <strong>{{ $w['title'] }}</strong>
                                        <small>{{ match ($w['state']) { 'done' => __($j.'done'), 'here' => __($j.'here', ['pct' => "\u{2066}{$w['percent']}%\u{2069}"]), default => __($j.'ahead', ['n' => count($w['lessons'])]) } }}</small>
                                        @if ($full || $w['state'] === 'here')
                                            <ul class="jt-lessons">
                                                @foreach ($w['lessons'] as $lesson)
                                                    <li data-state="{{ $lesson['state'] }}" style="--j: {{ $loop->index }}">{{ $lesson['title'] }}</li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    </div>

                    <div class="jt-aside">
                        <div class="jt-goal">
                            <p class="jt-label" style="margin-bottom: .3rem">{{ __($j.'goal') }}</p>
                            @if ($map['finished'])
                                <strong>{{ __($j.'finished') }}</strong>
                            @elseif ($course['finish_on'])
                                <strong>{{ __($j.'finish_on', ['date' => $course['finish_on']->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('j F Y')]) }}</strong>
                            @else
                                <strong>{{ __($j.'finish_unknown') }}</strong>
                            @endif
                            @if ($map['lessons_per_week'])
                                <small>{{ __($j.'pace', ['per' => rtrim(rtrim(number_format((float) $map['lessons_per_week'], 1), '0'), '.')]) }}@if ($course['level']['weeks_left']) · {{ trans_choice($j.'level_eta', $course['level']['weeks_left'], ['n' => $course['level']['weeks_left']]) }}@endif</small>
                            @endif
                        </div>

                        @if ($course['next'] !== [])
                            <div>
                                <p class="jt-label">{{ __($j.'up_next') }}</p>
                                <ol class="jt-list">
                                    @foreach ($course['next'] as $i => $lesson)
                                        <li><em>{{ $i + 1 }}</em><span>{{ $lesson['title'] }}</span>@if ($i === 0)<span class="jt-now">{{ __($j.'now') }}</span>@endif</li>
                                    @endforeach
                                </ol>
                            </div>
                        @endif

                        <div>
                            <p class="jt-label">{{ __($j.'review') }}</p>
                            @if ($course['reviews'] === [])
                                <p class="jt-muted">{{ __($j.'nothing_to_review') }}</p>
                            @else
                                <ul class="jt-list">
                                    @foreach ($course['reviews'] as $lesson)
                                        <li><em>↻</em><span>{{ $lesson['title'] }}</span></li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                </div>

                <dl class="jt-stats">
                    <div><dt>{{ __($j.'mastered') }}</dt><dd data-count="{{ $course['stars'] }}" data-count-delay="500">{{ $course['stars'] }}</dd></div>
                    <div><dt>{{ __($j.'attended') }}</dt><dd data-count="{{ $course['attended'] }}" data-count-delay="500">{{ $course['attended'] }}</dd></div>
                    <div><dt>{{ __($j.'on_time') }}</dt><dd data-count="{{ $course['on_time'] }}" data-count-delay="500">{{ $course['on_time'] }}</dd></div>
                    <div><dt>{{ __($j.'streak') }}</dt><dd data-count="{{ $course['streak'] }}" data-count-delay="500">{{ $course['streak'] }}</dd></div>
                </dl>
            @endif
        </section>
    @empty
        <div class="jt-card jt-muted">{{ __('learning::journey.none') }}</div>
    @endforelse

    <a class="jt-card jt-homework lms-lift" href="{{ \Modules\Learning\Filament\Family\FamilyHomework::getUrl() }}">
        <div>
            <strong>{{ trans_choice($j.'homework', $homework, ['n' => $homework]) }}</strong>
            @if ($next_due) <small>{{ __($j.'next_due', ['date' => $next_due]) }}</small> @endif
        </div>
        <span>{{ __($j.'open') }}</span>
    </a>
</div>
