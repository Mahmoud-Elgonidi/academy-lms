@php
    $results = $this->results;
    $days = $this->gridDays();
    $hours = range(6, 23);
    $tz = $this->data['timezone'] ?? 'UTC';
    $picked = $this->pickedCandidates();
    $pickedResult = collect($results)->first(fn ($r) => $r['teacher']->id === $this->pickedTeacher);
    $trial = $this->isTrial();
@endphp

<x-filament-panels::page>
    <style>
        .ft { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1.25rem; align-items: start; }
        @media (min-width: 1200px) { .ft { grid-template-columns: 430px minmax(0, 1fr); } .ft-side { position: sticky; top: 5rem; } }
        .ft-side, .ft-main { display: flex; flex-direction: column; gap: 1rem; min-width: 0; }
        .ft-card { background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl); padding: 1rem; }
        .ft-h { margin: 0; font-size: 1rem; font-weight: 700; }
        .ft-muted { color: var(--gray-500); font-size: .8125rem; margin: 0; }
        .ft-grid { display: grid; grid-template-columns: 2.6rem repeat(7, minmax(0, 1fr)); gap: 2px; margin-top: .6rem; user-select: none; }
        .ft-grid span { font-size: .7rem; color: var(--gray-500); text-align: center; padding: .15rem 0; }
        .ft-grid .ft-hour { text-align: end; padding-inline-end: .35rem; font-variant-numeric: tabular-nums; }
        .ft-cell { height: 1.15rem; border-radius: 4px; background: var(--gray-100); border: 0; cursor: pointer; }
        .ft-cell:hover { background: var(--gray-200); }
        .ft-cell[aria-pressed="true"] { background: var(--primary-500); }
        .ft-grid-tools { display: flex; justify-content: space-between; align-items: center; margin-top: .5rem; }
        .ft-link { color: var(--primary-700); font-size: .8125rem; font-weight: 600; }
        .ft-select { position: sticky; top: 4.5rem; z-index: 5; display: flex; flex-wrap: wrap; align-items: center; gap: .6rem 1rem; padding: .8rem 1rem; border-radius: var(--radius-xl);
            background: var(--primary-50); border: 1px solid var(--primary-200); }
        .ft-select strong { font-size: .95rem; }
        .ft-select .ft-actions { margin-inline-start: auto; display: flex; flex-wrap: wrap; gap: .4rem; }
        .ft-timer { font-variant-numeric: tabular-nums; font-weight: 700; color: var(--primary-700); }
        .ft-teacher { display: flex; flex-direction: column; gap: .8rem; }
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
        .ft-empty { text-align: center; padding: 2.5rem 1rem; color: var(--gray-500); }
        .dark .ft-card, .dark .ft-chip { background: var(--gray-900); border-color: var(--gray-800); }
        .dark .ft-cell { background: var(--gray-800); }
        .dark .ft-select { background: color-mix(in oklab, var(--primary-500) 12%, transparent); border-color: var(--primary-800); }
    </style>

    <div class="ft">
        <aside class="ft-side">
            <section class="ft-card">{{ $this->form }}</section>

            <section class="ft-card">
                <h3 class="ft-h">{{ __('scheduling::find.grid.title') }}</h3>
                <p class="ft-muted">{{ __('scheduling::find.grid.hint', ['city' => $this->city($tz)]) }}</p>
                <div class="ft-grid" role="grid" aria-label="{{ __('scheduling::find.grid.title') }}">
                    <span></span>
                    @foreach ($days as $day)
                        <span>{{ mb_substr($this->dayName($day), 0, app()->getLocale() === 'ar' ? 10 : 3) }}</span>
                    @endforeach
                    @foreach ($hours as $hour)
                        <span class="ft-hour">{{ \Modules\SharedKernel\Time\Clock::hour($hour) }}</span>
                        @foreach ($days as $day)
                            <button type="button" class="ft-cell" wire:click="toggleCell({{ $day }}, {{ $hour }})"
                                aria-pressed="{{ in_array($hour, $this->cells[$day] ?? [], true) ? 'true' : 'false' }}"
                                aria-label="{{ $this->dayName($day) }} {{ \Modules\SharedKernel\Time\Clock::hour($hour) }}"></button>
                        @endforeach
                    @endforeach
                </div>
                <div class="ft-grid-tools">
                    <button type="button" class="ft-link" wire:click="clearCells">{{ __('scheduling::find.grid.clear') }}</button>
                    <x-filament::button wire:click="search" icon="heroicon-m-magnifying-glass">{{ __('scheduling::find.search') }}</x-filament::button>
                </div>
            </section>
        </aside>

        <main class="ft-main">
            @if ($picked !== [] && $pickedResult)
                <div class="ft-select" role="status" wire:key="selection">
                    <div>
                        <span class="ft-muted">{{ __('scheduling::find.selection') }}</span><br>
                        <strong>{{ collect($this->inWeekOrder($picked))->map(fn ($c) => $this->dayName($c->studentWeekday).' '.\Modules\SharedKernel\Time\Clock::wall($c->studentTime))->implode(' + ') }}</strong>
                        · {{ $pickedResult['teacher']->name }}
                    </div>
                    @if ($this->holdBatch)
                        <span x-data="{ left: {{ max(0, $this->holdExpiresAt - now()->getTimestamp()) }} }" x-init="setInterval(() => left = Math.max(0, left - 1), 1000)">
                            {{ __('scheduling::find.held_for') }} <span class="ft-timer" x-text="Math.floor(left / 60) + ':' + String(left % 60).padStart(2, '0')"></span>
                        </span>
                        <div class="ft-actions">
                            @if ($trial)
                                <x-filament::button wire:click="confirm('trial')" size="sm" icon="heroicon-m-sparkles">{{ __('scheduling::find.confirm_trial') }}</x-filament::button>
                            @else
                                <x-filament::button wire:click="confirm('series')" size="sm">{{ __('scheduling::find.confirm_series', ['count' => $this->heldCount()]) }}</x-filament::button>
                            @endif
                            <x-filament::button wire:click="release" color="gray" size="sm" outlined>{{ __('scheduling::find.release') }}</x-filament::button>
                        </div>
                    @else
                        <div class="ft-actions">
                            <x-filament::button wire:click="hold" icon="heroicon-m-lock-closed" size="sm">{{ __('scheduling::find.hold', ['min' => \Modules\Scheduling\Domain\Models\BookingHold::MINUTES]) }}</x-filament::button>
                        </div>
                    @endif
                </div>
            @endif

            @if (! $this->searched)
                <div class="ft-card ft-empty">
                    <x-filament::icon :icon="$trial ? 'heroicon-o-sparkles' : 'heroicon-o-calendar-days'" style="width: 2rem; height: 2rem; margin: 0 auto .5rem" />
                    <strong>{{ __('scheduling::find.'.($trial ? 'start_trial' : 'start').'.title') }}</strong>
                    <p class="ft-muted">{{ __('scheduling::find.'.($trial ? 'start_trial' : 'start').'.hint') }}</p>
                </div>
            @elseif ($results === [])
                <div class="ft-card ft-empty">
                    <strong>{{ __('scheduling::find.none.title') }}</strong>
                    <p class="ft-muted">{{ __('scheduling::find.none.hint') }}</p>
                </div>
            @else
                <div>
                    <h2 class="ft-h lms-heading">{{ trans_choice('scheduling::find.found', count($results), ['count' => count($results)]) }}</h2>
                    <p class="ft-muted">{{ __('scheduling::find.found_hint', ['city' => $this->city($tz)]) }}</p>
                </div>

                @foreach ($results as $result)
                    @php $t = $result['teacher']; @endphp
                    <article class="ft-card ft-teacher" wire:key="t-{{ $t->id }}">
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
                                <strong>{{ collect($this->inWeekOrder($result['pattern']))->map(fn ($c) => $this->dayName($c->studentWeekday).' '.\Modules\SharedKernel\Time\Clock::wall($c->studentTime))->implode(' + ') }}</strong>
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
                                                <strong>{{ \Modules\SharedKernel\Time\Clock::wall($chip->studentTime) }}</strong>
                                                <small>{{ \Modules\SharedKernel\Time\Clock::wall($chip->teacherTime) }} {{ $this->city($t->timezone ?? 'UTC') }}</small>
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
            @endif
        </main>
    </div>
</x-filament-panels::page>
