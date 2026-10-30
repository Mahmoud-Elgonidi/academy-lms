@php
    use Modules\SharedKernel\Time\Clock;

    $results = $this->results;
    $days = $this->gridDays();
    $hours = range(6, 23);
    $freeHours = range(\Modules\Scheduling\Queries\FreeTimesQuery::FIRST_HOUR, \Modules\Scheduling\Queries\FreeTimesQuery::LAST_HOUR);
    $tz = $this->data['timezone'] ?? 'UTC';
    $picked = $this->pickedCandidates();
    $pickedResult = collect($results)->first(fn ($r) => $r['teacher']->id === $this->pickedTeacher);
    $trial = $this->isTrial();
    $ready = $this->ready();
    $tab = $this->timesTab;
    $free = $ready && $tab === 'free' ? $this->freeTimes : null;
    $step = $this->step();
    $short = fn (int $day) => mb_substr($this->dayName($day), 0, app()->getLocale() === 'ar' ? 10 : 3);
    // "Saturday and Tuesday 6:00 PM": days that share a time, then the time.
    $timesLine = function (array $times) {
        return collect($this->inWeekOrder(collect($times)->mapWithKeys(fn ($t) => [$t['weekday'] => $t['time']])->all()))
            ->groupBy(fn ($time) => $time, preserveKeys: true)
            ->map(fn ($group, $time) => $group->keys()->map(fn ($d) => $this->dayName($d))->implode(__('scheduling::find.free.and')).' '.Clock::wall($time))
            ->implode(' + ');
    };
@endphp

<x-filament-panels::page>
    <style>
        .ft { display: flex; flex-direction: column; gap: 1.5rem; max-width: 72rem; margin-inline: auto; width: 100%; padding-bottom: 1rem; }
        .ft-step { display: flex; flex-direction: column; gap: .75rem; min-width: 0; scroll-margin-top: 5rem; }
        .ft-step-head { display: flex; align-items: center; gap: .75rem; }
        .ft-num { width: 1.9rem; height: 1.9rem; border-radius: 50%; display: grid; place-items: center; font-weight: 700; font-size: .9rem; flex-shrink: 0;
            background: var(--gray-100); color: var(--gray-600); }
        .ft-step[data-state="now"] .ft-num { background: var(--primary-600); color: white; }
        .ft-step[data-state="done"] .ft-num { background: var(--primary-50); color: var(--primary-700); }
        .ft-step-head h2 { margin: 0; font-size: 1.05rem; font-weight: 700; }
        .ft-card { background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl); padding: 1rem; }
        .ft-h { margin: 0; font-size: 1rem; font-weight: 700; }
        .ft-muted { color: var(--gray-500); font-size: .8125rem; margin: 0; }
        .ft-link { color: var(--primary-700); font-size: .8125rem; font-weight: 600; }
        .ft-head-row { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .6rem; }

        /* The two ways to give times: a segmented switch. */
        .ft-switch { display: inline-flex; padding: 3px; gap: 2px; border-radius: var(--radius-lg); background: var(--gray-100); }
        .ft-switch button { padding: .4rem .9rem; border-radius: calc(var(--radius-lg) - 2px); font-size: .8125rem; font-weight: 600; color: var(--gray-600); }
        .ft-switch button[aria-selected="true"] { background: white; color: var(--primary-700); box-shadow: 0 1px 2px rgb(0 0 0 / .08); }

        /* Suggestions. */
        .ft-sugs { display: grid; grid-template-columns: repeat(auto-fill, minmax(14rem, 1fr)); gap: .6rem; margin-top: .75rem; }
        .ft-sug { display: flex; flex-direction: column; align-items: flex-start; gap: .2rem; text-align: start; padding: .7rem .85rem; border-radius: var(--radius-lg);
            border: 1px solid var(--gray-200); background: white; }
        .ft-sug:hover, .ft-sug:focus-visible { border-color: var(--primary-400); }
        .ft-sug strong { font-size: .95rem; font-variant-numeric: tabular-nums; }
        .ft-sug span { font-size: .78rem; color: var(--gray-500); }
        .ft-sug .ft-sug-count { color: var(--primary-700); font-weight: 700; }

        /* The heat map and the student's grid share a layout. */
        .ft-grid { display: grid; grid-template-columns: 3.2rem repeat(7, minmax(0, 1fr)); gap: 3px; margin-top: .75rem; user-select: none; }
        .ft-grid > span { font-size: .7rem; color: var(--gray-500); text-align: center; padding: .15rem 0; }
        .ft-grid .ft-hour { text-align: end; padding-inline-end: .35rem; font-variant-numeric: tabular-nums; }
        .ft-cell { height: 1.15rem; border-radius: 4px; background: var(--gray-100); border: 0; cursor: pointer; }
        .ft-cell:hover { background: var(--gray-200); }
        .ft-cell[aria-pressed="true"] { background: var(--primary-500); }
        .ft-heat { height: 1.6rem; border-radius: 5px; border: 0; cursor: pointer; font-size: .72rem; font-weight: 700; font-variant-numeric: tabular-nums;
            background: var(--gray-50); color: var(--gray-400); }
        .ft-heat[data-level="1"] { background: color-mix(in oklab, var(--primary-500) 16%, white); color: var(--primary-800); }
        .ft-heat[data-level="2"] { background: color-mix(in oklab, var(--primary-500) 34%, white); color: var(--primary-900); }
        .ft-heat[data-level="3"] { background: color-mix(in oklab, var(--primary-500) 60%, white); color: white; }
        .ft-heat[data-level="4"] { background: var(--primary-600); color: white; }
        .ft-heat:hover { outline: 2px solid var(--primary-300); }
        .ft-heat[aria-pressed="true"] { outline: 2px solid var(--primary-700); outline-offset: 1px; }
        .ft-heat:disabled { cursor: default; outline: 0; }
        .ft-legend { display: flex; align-items: center; gap: .3rem; font-size: .72rem; color: var(--gray-500); }
        .ft-legend i { width: .9rem; height: .9rem; border-radius: 3px; display: inline-block; }
        .ft-tools { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: .5rem; margin-top: .6rem; }

        /* A reason in place. */
        .ft-reason { display: flex; flex-direction: column; align-items: center; text-align: center; gap: .35rem; padding: 2rem 1rem; }
        .ft-reason strong { font-size: .95rem; }
        .ft-reason .ft-reason-actions { display: flex; flex-wrap: wrap; justify-content: center; gap: .4rem; margin-top: .4rem; }
        .ft-reason[data-tone="danger"] { background: var(--danger-50); border-color: var(--danger-200); color: var(--danger-800); }

        /* Teachers. */
        .ft-list { display: flex; flex-direction: column; gap: .8rem; }
        .ft-teacher { display: flex; flex-direction: column; gap: .8rem; }
        .ft-teacher[data-picked="true"] { border-color: var(--primary-400); box-shadow: 0 0 0 1px var(--primary-400); }
        .ft-teacher-head { display: flex; align-items: center; gap: .75rem; }
        .ft-avatar { width: 2.6rem; height: 2.6rem; border-radius: 50%; display: grid; place-items: center; font-weight: 700; background: var(--primary-50); color: var(--primary-700); flex-shrink: 0; }
        .ft-name { font-weight: 700; font-size: 1rem; }
        .ft-stats { margin-inline-start: auto; display: flex; gap: 1rem; text-align: center; }
        .ft-stats div { display: flex; flex-direction: column; }
        .ft-stats small { font-size: .7rem; color: var(--gray-500); }
        .ft-stats strong { font-size: 1.15rem; font-variant-numeric: tabular-nums; }
        .ft-pattern { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; font-size: .8125rem; padding: .5rem .7rem; border-radius: var(--radius-lg); background: var(--gray-50); }
        .ft-days { display: flex; flex-direction: column; gap: .45rem; }
        .ft-day { display: grid; grid-template-columns: 4.5rem minmax(0, 1fr); align-items: start; gap: .5rem; }
        .ft-day > span { font-size: .8125rem; font-weight: 600; padding-top: .45rem; }
        .ft-chips { display: flex; flex-wrap: wrap; gap: .4rem; }
        .ft-chip { display: flex; flex-direction: column; align-items: center; min-width: 5.2rem; padding: .3rem .6rem; border-radius: var(--radius-lg); border: 1px solid var(--gray-200); background: white; line-height: 1.25; }
        .ft-chip strong { font-size: .95rem; font-variant-numeric: tabular-nums; }
        .ft-chip small { font-size: .68rem; color: var(--gray-500); }
        .ft-chip em { font-style: normal; font-size: .65rem; font-weight: 700; color: var(--warning-700); }
        .ft-chip em.ft-first { color: var(--primary-700); }
        .ft-chip:hover { border-color: var(--primary-400); }
        .ft-chip[aria-pressed="true"] { background: var(--primary-600); border-color: var(--primary-600); color: white; }
        .ft-chip[aria-pressed="true"] small, .ft-chip[aria-pressed="true"] em { color: white; }

        /* The bar under everything: what is chosen, and the one next step. */
        .ft-bar { position: sticky; bottom: .75rem; z-index: 10; display: flex; flex-wrap: wrap; align-items: center; gap: .6rem 1rem; padding: .75rem 1rem;
            max-width: 72rem; width: 100%; margin-inline: auto; border-radius: var(--radius-xl); background: white; border: 1px solid var(--gray-200);
            box-shadow: 0 10px 30px -12px rgb(0 0 0 / .25); }
        .ft-bar-summary { display: flex; flex-wrap: wrap; align-items: center; gap: .2rem .5rem; font-size: .8125rem; min-width: 0; }
        .ft-bar-summary span + span::before { content: '·'; margin-inline-end: .5rem; color: var(--gray-400); }
        .ft-bar-summary span:last-child { font-weight: 700; }
        .ft-bar-actions { margin-inline-start: auto; display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
        .ft-timer { font-variant-numeric: tabular-nums; font-weight: 700; color: var(--primary-700); }
        .ft-bar-refusal { flex-basis: 100%; font-size: .8125rem; font-weight: 600; color: var(--danger-700); display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }

        .dark .ft-card, .dark .ft-chip, .dark .ft-sug, .dark .ft-bar { background: var(--gray-900); border-color: var(--gray-800); }
        .dark .ft-cell, .dark .ft-switch, .dark .ft-num { background: var(--gray-800); }
        .dark .ft-switch button[aria-selected="true"] { background: var(--gray-900); color: var(--primary-400); }
        .dark .ft-heat { background: var(--gray-800); color: var(--gray-500); }
        .dark .ft-heat[data-level="1"] { background: color-mix(in oklab, var(--primary-500) 22%, var(--gray-900)); color: var(--primary-200); }
        .dark .ft-heat[data-level="2"] { background: color-mix(in oklab, var(--primary-500) 42%, var(--gray-900)); color: white; }
        .dark .ft-pattern { background: var(--gray-800); }
        .dark .ft-reason[data-tone="danger"] { background: color-mix(in oklab, var(--danger-500) 12%, transparent); border-color: var(--danger-800); color: var(--danger-300); }

        @media (prefers-reduced-motion: no-preference) {
            .ft-heat { animation: ft-fade .35s ease-out backwards; }
            .ft-bar { animation: ft-up .3s ease-out backwards; }
            @keyframes ft-fade { from { opacity: 0; transform: scale(.85); } }
            @keyframes ft-up { from { opacity: 0; transform: translateY(.75rem); } }
        }
    </style>

    <div class="ft">
        {{-- 1. The student and the subject. --}}
        <section class="ft-step" data-state="{{ $ready ? 'done' : 'now' }}" aria-labelledby="ft-step-1">
            <div class="ft-step-head">
                <span class="ft-num">1</span>
                <div>
                    <h2 id="ft-step-1">{{ __('scheduling::find.steps.who') }}</h2>
                    <p class="ft-muted">{{ __('scheduling::find.steps.who_hint') }}</p>
                </div>
            </div>
            <div class="ft-card">{{ $this->form }}</div>
        </section>

        {{-- 2. The times: where teachers are free now, or the student's own hours. --}}
        <section class="ft-step" data-state="{{ ! $ready ? 'later' : ($this->searched ? 'done' : 'now') }}" aria-labelledby="ft-step-2">
            <div class="ft-head-row">
                <div class="ft-step-head">
                    <span class="ft-num">2</span>
                    <div>
                        <h2 id="ft-step-2">{{ __('scheduling::find.steps.times') }}</h2>
                        <p class="ft-muted">{{ __('scheduling::find.grid.clock', ['city' => $this->city($tz)]) }}</p>
                    </div>
                </div>
                <div class="ft-switch" role="tablist" aria-label="{{ __('scheduling::find.steps.times') }}">
                    <button type="button" role="tab" aria-selected="{{ $tab === 'free' ? 'true' : 'false' }}" wire:click="showTimes('free')">{{ __('scheduling::find.tabs.free') }}</button>
                    <button type="button" role="tab" aria-selected="{{ $tab === 'mine' ? 'true' : 'false' }}" wire:click="showTimes('mine')">{{ __('scheduling::find.tabs.mine') }}</button>
                </div>
            </div>

            @if ($tab === 'free')
                @if (! $ready)
                    <div class="ft-card ft-reason">
                        <x-filament::icon icon="heroicon-o-calendar-days" style="width: 1.75rem; height: 1.75rem; color: var(--gray-400)" />
                        <strong>{{ __('scheduling::find.free.wait.title') }}</strong>
                        <p class="ft-muted">{{ __('scheduling::find.free.wait.hint') }}</p>
                    </div>
                @elseif ($free['teachers'] === 0)
                    @include('scheduling::filament.find-teacher-reason', ['reason' => $this->emptyReason])
                @else
                    <div class="ft-card">
                        <h3 class="ft-h">{{ __('scheduling::find.free.suggestions') }}</h3>
                        <p class="ft-muted">{{ trans_choice('scheduling::find.free.suggestions_hint', $trial ? 1 : (int) $this->data['per_week'], ['n' => $trial ? 1 : (int) $this->data['per_week']]) }}</p>
                        @if ($free['suggestions'] === [])
                            <p class="ft-muted" style="margin-top: .6rem">{{ __('scheduling::find.free.no_suggestions') }}</p>
                        @else
                            <div class="ft-sugs lms-stagger">
                                @foreach ($free['suggestions'] as $i => $suggestion)
                                    <button type="button" class="ft-sug lms-lift" wire:key="sug-{{ $i }}-{{ md5(json_encode($suggestion['times'])) }}"
                                        wire:click="useSuggestion({{ $i }})" @disabled($this->holdBatch !== null)>
                                        <strong>{{ $timesLine($suggestion['times']) }}</strong>
                                        <span class="ft-sug-count">{{ trans_choice('scheduling::find.free.teachers', $suggestion['count'], ['count' => $suggestion['count']]) }}</span>
                                        <span>{{ __('scheduling::find.free.best', ['name' => $suggestion['teacher']]) }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    @php $max = max(1, ...array_map(fn ($day) => max([0, ...array_column($day, 'count')]), $free['cells'] ?: [[]])); @endphp
                    <div class="ft-card">
                        <div class="ft-head-row">
                            <div>
                                <h3 class="ft-h">{{ __('scheduling::find.free.map') }}</h3>
                                <p class="ft-muted">{{ __('scheduling::find.free.map_hint', ['minutes' => (int) $this->data['duration']]) }}</p>
                            </div>
                            <div class="ft-legend" aria-hidden="true">
                                {{ __('scheduling::find.free.fewer') }}
                                <i style="background: color-mix(in oklab, var(--primary-500) 16%, white)"></i>
                                <i style="background: color-mix(in oklab, var(--primary-500) 34%, white)"></i>
                                <i style="background: color-mix(in oklab, var(--primary-500) 60%, white)"></i>
                                <i style="background: var(--primary-600)"></i>
                                {{ __('scheduling::find.free.more') }}
                            </div>
                        </div>
                        <div class="ft-grid" role="grid" aria-label="{{ __('scheduling::find.free.map') }}">
                            <span></span>
                            @foreach ($days as $day)
                                <span>{{ $short($day) }}</span>
                            @endforeach
                            @foreach ($freeHours as $hour)
                                <span class="ft-hour">{{ Clock::hour($hour) }}</span>
                                @foreach ($days as $day)
                                    @php
                                        $cell = $free['cells'][$day][$hour] ?? null;
                                        $count = $cell['count'] ?? 0;
                                        $level = $count === 0 ? 0 : max(1, (int) ceil(4 * $count / $max));
                                        $names = $cell ? implode('، ', $cell['names']).($count > count($cell['names']) ? ' '.__('scheduling::find.free.others', ['n' => $count - count($cell['names'])]) : '') : '';
                                        $label = $this->dayName($day).' '.Clock::hour($hour).' · '.($count === 0 ? __('scheduling::find.free.nobody') : trans_choice('scheduling::find.free.teachers', $count, ['count' => $count]).': '.$names);
                                    @endphp
                                    <button type="button" class="ft-heat" data-level="{{ $level }}" wire:click="toggleCell({{ $day }}, {{ $hour }})"
                                        aria-pressed="{{ in_array($hour, $this->cells[$day] ?? [], true) ? 'true' : 'false' }}"
                                        title="{{ $label }}" aria-label="{{ $label }}" @disabled($count === 0 && ! in_array($hour, $this->cells[$day] ?? [], true))>{{ $count ?: '' }}</button>
                                @endforeach
                            @endforeach
                        </div>
                        <div class="ft-tools">
                            <p class="ft-muted">{{ __('scheduling::find.free.tap') }}</p>
                            @if (array_filter($this->cells) !== [])
                                <button type="button" class="ft-link" wire:click="clearCells">{{ __('scheduling::find.grid.clear') }}</button>
                            @endif
                        </div>
                    </div>
                @endif
            @else
                <div class="ft-card">
                    <h3 class="ft-h">{{ __('scheduling::find.grid.title') }}</h3>
                    <p class="ft-muted">{{ __('scheduling::find.grid.hint', ['city' => $this->city($tz)]) }}</p>
                    <div class="ft-grid" role="grid" aria-label="{{ __('scheduling::find.grid.title') }}">
                        <span></span>
                        @foreach ($days as $day)
                            <span>{{ $short($day) }}</span>
                        @endforeach
                        @foreach ($hours as $hour)
                            <span class="ft-hour">{{ Clock::hour($hour) }}</span>
                            @foreach ($days as $day)
                                <button type="button" class="ft-cell" wire:click="toggleCell({{ $day }}, {{ $hour }})"
                                    aria-pressed="{{ in_array($hour, $this->cells[$day] ?? [], true) ? 'true' : 'false' }}"
                                    aria-label="{{ $this->dayName($day) }} {{ Clock::hour($hour) }}"></button>
                            @endforeach
                        @endforeach
                    </div>
                    <div class="ft-tools">
                        <button type="button" class="ft-link" wire:click="clearCells">{{ __('scheduling::find.grid.clear') }}</button>
                    </div>
                </div>
            @endif
        </section>

        {{-- 3. The teachers, ranked; the page comes here after a search. --}}
        <section class="ft-step" id="ft-teachers" data-state="{{ $this->searched ? 'now' : 'later' }}" aria-labelledby="ft-step-3"
            x-data x-on:ft-results.window="$nextTick(() => $el.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' }))">
            <div class="ft-step-head">
                <span class="ft-num">3</span>
                <div>
                    <h2 id="ft-step-3">{{ __('scheduling::find.steps.teachers') }}</h2>
                    @if ($this->searched && $results !== [])
                        <p class="ft-muted">{{ trans_choice('scheduling::find.found', count($results), ['count' => count($results)]) }} · {{ __('scheduling::find.found_hint', ['city' => $this->city($tz)]) }}</p>
                    @endif
                </div>
            </div>

            @if (! $this->searched)
                <div class="ft-card ft-reason">
                    <x-filament::icon :icon="$trial ? 'heroicon-o-sparkles' : 'heroicon-o-magnifying-glass'" style="width: 1.75rem; height: 1.75rem; color: var(--gray-400)" />
                    <strong>{{ __('scheduling::find.'.($trial ? 'start_trial' : 'start').'.title') }}</strong>
                    <p class="ft-muted">{{ __('scheduling::find.'.($trial ? 'start_trial' : 'start').'.hint') }}</p>
                </div>
            @elseif ($results === [])
                @include('scheduling::filament.find-teacher-reason', ['reason' => $this->emptyReason])
            @else
                <div class="ft-list lms-stagger">
                    @foreach ($results as $result)
                        @php $t = $result['teacher']; @endphp
                        <article class="ft-card ft-teacher lms-lift" wire:key="t-{{ $t->id }}" data-picked="{{ $this->pickedTeacher === $t->id ? 'true' : 'false' }}">
                            <div class="ft-teacher-head">
                                <span class="ft-avatar">{{ mb_substr(preg_replace('/^(أ\.|د\.)\s*/u', '', $t->name), 0, 1) }}</span>
                                <div>
                                    <div class="ft-name">{{ $t->name }}</div>
                                    <p class="ft-muted">
                                        {{ __('scheduling::find.genders_short.'.$t->gender) }}
                                        · {{ collect($t->languages)->map(fn ($l) => __('scheduling::find.languages.'.$l))->implode(' + ') }}
                                        @if ($t->dialect) · {{ $t->dialect }} @endif
                                        · {{ $this->city($t->timezone ?? 'UTC') }}
                                    </p>
                                </div>
                                <div class="ft-stats">
                                    <div><small>{{ __('scheduling::find.fit') }}</small><strong>{{ $result['score'] }}%</strong></div>
                                    @if ($trial)
                                        <div><small>{{ __('scheduling::find.conversion') }}</small><strong>{{ $result['conversion'] === null ? '—' : (int) round($result['conversion'] * 100).'%' }}</strong></div>
                                    @else
                                        <div><small>{{ __('scheduling::find.load') }}</small><strong>{{ (int) round($result['utilisation'] * 100) }}%</strong></div>
                                    @endif
                                </div>
                            </div>

                            @if ($result['pattern'] && ! $trial)
                                <div class="ft-pattern">
                                    <span>{{ __('scheduling::find.pattern') }}</span>
                                    <strong>{{ collect($this->inWeekOrder($result['pattern']))->map(fn ($c) => $this->dayName($c->studentWeekday).' '.Clock::wall($c->studentTime))->implode(' + ') }}</strong>
                                    @if (! $this->holdBatch)
                                        <button type="button" class="ft-link" wire:click="usePattern('{{ $t->id }}')">{{ __('scheduling::find.use_pattern') }}</button>
                                    @endif
                                </div>
                            @endif

                            <div class="ft-days">
                                @foreach ($this->inWeekOrder($result['chips']) as $day => $chips)
                                    <div class="ft-day">
                                        <span>{{ $this->dayName($day) }}</span>
                                        <div class="ft-chips">
                                            @foreach ($chips as $chip)
                                                <button type="button" class="ft-chip" wire:click="pick('{{ $t->id }}', '{{ $chip->key() }}')"
                                                    aria-pressed="{{ $this->pickedTeacher === $t->id && in_array($chip->key(), $this->picked, true) ? 'true' : 'false' }}"
                                                    title="{{ $chip->full() ? __('scheduling::find.all_weeks', ['n' => $chip->weeks]) : __('scheduling::find.conflicts_on', ['dates' => implode('، ', $chip->conflictDates)]) }}">
                                                    <strong>{{ Clock::wall($chip->studentTime) }}</strong>
                                                    <small>{{ Clock::wall($chip->teacherTime) }} {{ $this->city($t->timezone ?? 'UTC') }}</small>
                                                    @if ($trial)
                                                        <em class="ft-first">{{ __('scheduling::find.first_free', ['date' => $this->firstFree($chip, $t->timezone ?? 'UTC')]) }}</em>
                                                    @elseif (! $chip->full())
                                                        <em>{{ $chip->coverage }}/{{ $chip->weeks }}</em>
                                                    @endif
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </div>

    {{-- What is chosen so far, and the one button for the next step. --}}
    <div class="ft-bar" role="region" aria-label="{{ __('scheduling::find.bar.label') }}" wire:key="bar">
        <div class="ft-bar-summary">
            @forelse ($this->summary() as $part)
                <span>{{ $part }}</span>
            @empty
                <span class="ft-muted">{{ __('scheduling::find.bar.empty') }}</span>
            @endforelse
            @if ($pickedResult && $picked !== [])
                <span>{{ $pickedResult['teacher']->name }}</span>
            @endif
        </div>

        <div class="ft-bar-actions">
            @if ($step === 'search')
                <x-filament::button wire:click="search" icon="heroicon-m-magnifying-glass" :disabled="! $ready">{{ __('scheduling::find.search') }}</x-filament::button>
            @elseif ($step === 'hold')
                <x-filament::button wire:click="hold" icon="heroicon-m-lock-closed">{{ __('scheduling::find.hold', ['min' => \Modules\Scheduling\Domain\Models\BookingHold::MINUTES]) }}</x-filament::button>
            @else
                <span x-data="{ left: {{ max(0, $this->holdExpiresAt - now()->getTimestamp()) }} }" x-init="setInterval(() => left = Math.max(0, left - 1), 1000)" class="ft-bar-actions">
                    <span x-show="left > 0">{{ __('scheduling::find.held_for') }} <span class="ft-timer" x-text="Math.floor(left / 60) + ':' + String(left % 60).padStart(2, '0')"></span></span>
                    <span x-show="left > 0" class="ft-bar-actions">
                        @if ($trial)
                            <x-filament::button wire:click="confirm('trial')" icon="heroicon-m-sparkles">{{ __('scheduling::find.confirm_trial') }}</x-filament::button>
                        @else
                            <x-filament::button wire:click="confirm('series')">{{ __('scheduling::find.confirm_series', ['count' => $this->heldCount()]) }}</x-filament::button>
                        @endif
                    </span>
                    <span x-show="left === 0" @if (! $this->holdExpired()) x-cloak @endif class="ft-bar-actions">
                        <span class="ft-bar-refusal" style="flex-basis: auto">{{ __('scheduling::find.expired.title') }}</span>
                        <x-filament::button wire:click="holdAgain" icon="heroicon-m-arrow-path">{{ __('scheduling::find.expired.again') }}</x-filament::button>
                    </span>
                    <x-filament::button wire:click="release" color="gray" outlined>{{ __('scheduling::find.release') }}</x-filament::button>
                </span>
            @endif
        </div>

        @if ($this->refusal)
            <div class="ft-bar-refusal" role="alert">
                <x-filament::icon icon="heroicon-m-exclamation-triangle" style="width: 1rem; height: 1rem" />
                {{ $this->refusal }}
                @if ($step !== 'confirm')
                    <button type="button" class="ft-link" wire:click="search">{{ __('scheduling::find.search_again') }}</button>
                @endif
            </div>
        @endif
    </div>
</x-filament-panels::page>
