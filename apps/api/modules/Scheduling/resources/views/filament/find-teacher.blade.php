@php
    use Modules\SharedKernel\Time\Clock;

    $results = $this->results;
    $days = $this->gridDays();
    $tz = $this->data['timezone'] ?? 'UTC';
    $picked = $this->pickedCandidates();
    $pickedResult = collect($results)->first(fn ($r) => $r['teacher']->id === $this->pickedTeacher);
    $trial = $this->isTrial();
    $group = $this->isGroup();
    $mode = $this->data['mode'] ?? 'series';
    // Group mode puts the open groups second: the steps after it move down one.
    $n = fn (int $step) => $group && $step >= 2 ? $step + 1 : $step;
    $gw = 'scheduling::group_screens.find.';
    $ready = $this->ready();
    $openGroups = $ready && ! $trial ? $this->openGroups : [];
    $tab = $this->timesTab;
    $free = $ready && $tab === 'free' ? $this->freeTimes : null;
    $exactAnswer = $this->exact !== null ? $this->exactAnswer : null;
    $ranges = $this->markedRanges();
    $tzNow = now($tz);
    $exactAt = $this->exactLabel();
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
    @include('scheduling::filament.groups.styles')
    <style>
        /* What is booked: private, group or a trial — the first choice, large. */
        .ft-mode { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .6rem; }
        .ft-mode button { display: flex; align-items: center; gap: .75rem; text-align: start; padding: .85rem 1rem; border-radius: var(--radius-xl); border: 1.5px solid var(--gray-200); background: white; }
        .ft-mode button:hover { border-color: var(--primary-300); }
        .ft-mode button:focus-visible { outline: 2px solid var(--primary-600); outline-offset: 2px; }
        .ft-mode-ico { width: 2.5rem; height: 2.5rem; border-radius: .75rem; display: grid; place-items: center; flex-shrink: 0; background: var(--gray-100); color: var(--gray-600); }
        .ft-mode-ico svg { width: 1.35rem; height: 1.35rem; }
        .ft-mode strong { display: block; font-size: .98rem; }
        .ft-mode small { display: block; font-size: .76rem; color: var(--gray-500); line-height: 1.4; }
        .ft-mode button[aria-checked="true"] { border-color: var(--primary-600); background: var(--primary-50); box-shadow: 0 0 0 1px var(--primary-600); }
        .ft-mode button[aria-checked="true"] .ft-mode-ico { background: var(--primary-600); color: white; }
        .ft-mode button[data-tone="group"] .ft-mode-ico { background: var(--info-50); color: var(--info-700); }
        .ft-mode button[data-tone="group"][aria-checked="true"] { border-color: var(--info-600); background: var(--info-50); box-shadow: 0 0 0 1px var(--info-600); }
        .ft-mode button[data-tone="group"][aria-checked="true"] .ft-mode-ico { background: var(--info-600); color: white; }
        .dark .ft-mode button { background: var(--gray-900); border-color: var(--gray-700); }
        .dark .ft-mode button[aria-checked="true"] { background: color-mix(in oklab, var(--primary-500) 14%, var(--gray-900)); border-color: var(--primary-500); }
        .dark .ft-mode button[data-tone="group"][aria-checked="true"] { background: color-mix(in oklab, var(--info-500) 14%, var(--gray-900)); border-color: var(--info-500); }
        .dark .ft-mode-ico { background: var(--gray-800); color: var(--gray-300); }
        .dark .ft-mode button[data-tone="group"] .ft-mode-ico { background: color-mix(in oklab, var(--info-500) 18%, var(--gray-900)); color: var(--info-300); }
        @media (max-width: 639px) {
            .ft-mode { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .4rem; }
            .ft-mode button { flex-direction: column; align-items: center; text-align: center; gap: .35rem; padding: .65rem .4rem; }
            .ft-mode small { display: none; }
            .ft-mode strong { font-size: .85rem; }
        }
        /* Group mode: the groups with a seat. */
        .ft-groups { display: grid; grid-template-columns: repeat(auto-fill, minmax(19rem, 1fr)); gap: .8rem; }
        .ft-or { display: flex; align-items: center; gap: .75rem; color: var(--gray-500); font-size: .8125rem; font-weight: 600; }
        .ft-or::before, .ft-or::after { content: ''; flex: 1; height: 1px; background: var(--gray-200); }
        .dark .ft-or::before, .dark .ft-or::after { background: var(--gray-800); }
        .ft-hint-group { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; font-size: .8125rem; padding: .5rem .7rem; border-radius: var(--radius-lg);
            background: var(--info-50); color: var(--info-800); }
        .ft-hint-group svg { width: 1rem; height: 1rem; flex-shrink: 0; }
        .ft-hint-group button { font-weight: 700; color: var(--info-700); text-decoration: underline; text-underline-offset: 2px; }
        .dark .ft-hint-group { background: color-mix(in oklab, var(--info-500) 14%, var(--gray-900)); color: var(--info-200); }
        .dark .ft-hint-group button { color: var(--info-300); }
        .ft-teacher[data-group] .ft-avatar { background: var(--info-50); color: var(--info-700); }
        .dark .ft-teacher[data-group] .ft-avatar { background: color-mix(in oklab, var(--info-500) 18%, var(--gray-900)); color: var(--info-300); }

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
        .ft-switch button { white-space: nowrap; padding: .4rem .9rem; border-radius: calc(var(--radius-lg) - 2px); font-size: .8125rem; font-weight: 600; color: var(--gray-600); }
        .ft-switch button[aria-selected="true"] { background: white; color: var(--primary-700); box-shadow: 0 1px 2px rgb(0 0 0 / .08); }

        /* Suggestions. */
        .ft-sugs { display: grid; grid-template-columns: repeat(auto-fill, minmax(14rem, 1fr)); gap: .6rem; margin-top: .75rem; }
        .ft-sug { display: flex; flex-direction: column; align-items: flex-start; gap: .2rem; text-align: start; padding: .7rem .85rem; border-radius: var(--radius-lg);
            border: 1px solid var(--gray-200); background: white; }
        .ft-sug:hover, .ft-sug:focus-visible { border-color: var(--primary-400); }
        .ft-sug strong { font-size: .95rem; font-variant-numeric: tabular-nums; }
        .ft-sug span { font-size: .78rem; color: var(--gray-500); }
        .ft-sug .ft-sug-count { color: var(--primary-700); font-weight: 700; }

        /* The week × 24 hours board. */
        .ft-chosen { display: flex; flex-wrap: wrap; align-items: center; gap: .4rem; margin-top: .75rem; min-height: 1.9rem; }
        .ft-chosen > small { font-size: .75rem; font-weight: 600; color: var(--gray-500); margin-inline-end: .2rem; }
        .ft-range { display: inline-flex; align-items: center; gap: .3rem; padding: .2rem .25rem .2rem .6rem; border-radius: 999px; font-size: .8rem; font-weight: 600;
            background: var(--primary-50); color: var(--primary-800); border: 1px solid var(--primary-200); font-variant-numeric: tabular-nums; }
        .ft-range button { display: grid; place-items: center; width: 1.25rem; height: 1.25rem; border-radius: 50%; color: var(--primary-700); }
        .ft-range button:hover, .ft-range button:focus-visible { background: var(--primary-100); }
        .ft-ico { width: 1rem; height: 1rem; flex-shrink: 0; }
        .ft-board-wrap { margin-top: .75rem; display: flex; flex-direction: column; gap: .5rem; }
        .ft-board-tools { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .5rem; }
        .ft-night-toggle { display: inline-flex; align-items: center; gap: .4rem; padding: .3rem .65rem; border-radius: var(--radius-lg); font-size: .78rem; font-weight: 600;
            color: var(--gray-600); border: 1px dashed var(--gray-300); }
        .ft-night-toggle:hover, .ft-night-toggle:focus-visible { color: var(--primary-700); border-color: var(--primary-400); }
        .ft-night-free { display: inline-flex; align-items: center; gap: .3rem; color: var(--primary-700); font-weight: 600; }
        .ft-night-free i { width: .45rem; height: .45rem; border-radius: 50%; background: var(--primary-500); }
        .ft-dayswitch { display: none; align-items: center; gap: .25rem; }
        .ft-dayswitch button { display: grid; place-items: center; width: 2.25rem; height: 2.25rem; border-radius: var(--radius-lg); border: 1px solid var(--gray-200); color: var(--gray-600); }
        .ft-dayswitch select { height: 2.25rem; border-radius: var(--radius-lg); border: 1px solid var(--gray-200); font-size: .875rem; font-weight: 600; padding-inline: .6rem 2rem; background-color: transparent; }
        .ft-board-scroll { max-height: 31rem; overflow: auto; border: 1px solid var(--gray-200); border-radius: var(--radius-lg); overscroll-behavior: contain; }
        .ft-board { display: grid; grid-template-columns: 4.2rem repeat(7, minmax(2.6rem, 1fr)); gap: 2px; padding: 0 .35rem .35rem; user-select: none; -webkit-user-select: none; min-width: 26rem; }
        .ft-corner, .ft-colhead { position: sticky; top: 0; z-index: 2; background: white; }
        .ft-corner { inset-inline-start: 0; z-index: 3; }
        .ft-colhead { padding: .5rem 0 .4rem; text-align: center; font-size: .75rem; font-weight: 700; color: var(--gray-600); border-bottom: 1px solid var(--gray-200); }
        .ft-colhead[data-has] { color: var(--primary-700); }
        .ft-colhead-short { display: none; }
        .ft-rowhead { position: sticky; inset-inline-start: 0; z-index: 1; background: white; font-size: .68rem; color: var(--gray-500); text-align: end;
            padding-inline-end: .45rem; line-height: 1.5rem; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .ft-rowhead[data-night] { color: var(--gray-400); }
        .ft-rowhead[data-dawn], .ft-c[data-dawn] { margin-top: .3rem; }
        .ft-c { position: relative; height: 1.5rem; border-radius: 5px; border: 0; background: var(--gray-100); cursor: pointer; font-size: .7rem; font-weight: 700;
            font-variant-numeric: tabular-nums; color: var(--gray-400); touch-action: manipulation; }
        .ft-c[data-night] { background-image: repeating-linear-gradient(135deg, rgb(0 0 0 / .045) 0 3px, transparent 3px 7px); }
        .ft-board:not(.is-night) [data-night] { display: none; }
        .ft-c:hover { box-shadow: inset 0 0 0 1.5px var(--primary-300); }
        .ft-c:focus-visible { outline: 2px solid var(--primary-600); outline-offset: 1px; z-index: 1; }
        .ft-c[data-level="1"] { background-color: color-mix(in oklab, var(--primary-500) 14%, white); color: var(--primary-800); }
        .ft-c[data-level="2"] { background-color: color-mix(in oklab, var(--primary-500) 30%, white); color: var(--primary-900); }
        .ft-c[data-level="3"] { background-color: color-mix(in oklab, var(--primary-500) 55%, white); color: white; }
        .ft-c[data-level="4"] { background-color: var(--primary-600); color: white; }
        .ft-c[aria-pressed="true"] { box-shadow: inset 0 0 0 2px var(--primary-700); }
        .ft-board[data-heat="false"] .ft-c[aria-pressed="true"] { background-color: var(--primary-500); }
        .ft-c[aria-pressed="true"]::after { content: ''; position: absolute; top: 3px; inset-inline-end: 3px; width: 5px; height: 5px; border-radius: 50%; background: var(--primary-700); }
        .ft-board[data-heat="false"] .ft-c[aria-pressed="true"]::after { background: white; }
        .ft-c[data-drag="on"] { box-shadow: inset 0 0 0 2px var(--primary-500); background-color: color-mix(in oklab, var(--primary-500) 40%, white); }
        .ft-c[data-drag="off"] { box-shadow: inset 0 0 0 2px var(--gray-400); opacity: .55; }
        .ft-board.is-dragging { cursor: crosshair; }
        .ft-legend { display: flex; flex-wrap: wrap; align-items: center; gap: .3rem; font-size: .72rem; color: var(--gray-500); }
        .ft-legend-gap { width: .6rem; }
        .ft-swatch { width: .9rem; height: .9rem; border-radius: 3px; display: inline-block; background: var(--gray-100); }
        .ft-swatch[data-level="1"] { background: color-mix(in oklab, var(--primary-500) 14%, white); }
        .ft-swatch[data-level="2"] { background: color-mix(in oklab, var(--primary-500) 30%, white); }
        .ft-swatch[data-level="3"] { background: color-mix(in oklab, var(--primary-500) 55%, white); }
        .ft-swatch[data-level="4"] { background: var(--primary-600); }
        .ft-swatch[data-marked] { background: transparent; box-shadow: inset 0 0 0 2px var(--primary-700); }
        .ft-swatch[data-night] { background-image: repeating-linear-gradient(135deg, rgb(0 0 0 / .12) 0 2px, transparent 2px 5px); }
        .ft-tools { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: .5rem; margin-top: .6rem; }
        @media (max-width: 639px) {
            .ft-switch { display: flex; width: 100%; overflow-x: auto; }
            .ft-switch button { flex: 1 0 auto; padding-inline: .7rem; }
            .ft-dayswitch { display: inline-flex; }
            .ft-board { grid-template-columns: 4.2rem minmax(0, 1fr); min-width: 0; }
            .ft-c { height: 2.1rem; font-size: .8rem; }
            .ft-rowhead { line-height: 2.1rem; }
            .ft-board-scroll { max-height: 26rem; }
            @foreach (range(0, 6) as $ci)
                .ft-board[data-day="{{ $ci }}"] [data-col]:not([data-col="{{ $ci }}"]) { display: none; }
            @endforeach
        }
        @media (min-width: 640px) and (max-width: 900px) {
            .ft-colhead-long { display: none; }
            .ft-colhead-short { display: inline; }
        }

        /* An exact time. */
        .ft-exact { display: flex; flex-direction: column; gap: .9rem; }
        .ft-exact-row { display: flex; flex-wrap: wrap; align-items: flex-end; gap: .9rem 1.25rem; }
        .ft-field { display: flex; flex-direction: column; gap: .35rem; }
        .ft-field > span { font-size: .8rem; font-weight: 600; color: var(--gray-700); }
        .ft-daypick { display: flex; flex-wrap: wrap; gap: .3rem; }
        .ft-daypick button { padding: .35rem .7rem; border-radius: 999px; font-size: .8rem; font-weight: 600; border: 1px solid var(--gray-200); color: var(--gray-700); }
        .ft-daypick button[aria-pressed="true"] { background: var(--primary-600); border-color: var(--primary-600); color: white; }
        .ft-time { display: inline-flex; align-items: center; gap: .6rem; }
        .ft-time-fields { display: inline-flex; align-items: center; gap: .25rem; padding: .15rem .3rem; border-radius: var(--radius-lg); border: 1px solid var(--gray-300); background: white; }
        .ft-time-fields:focus-within { border-color: var(--primary-500); box-shadow: 0 0 0 1px var(--primary-500); }
        .ft-time-fields select, .ft-time-fields input { border: 0; background: transparent; font-size: .95rem; font-weight: 700; font-variant-numeric: tabular-nums; padding: .3rem .35rem; box-shadow: none; }
        .ft-time-fields select { padding-inline-end: 1.8rem; }
        .ft-time-fields input { width: 3.4rem; text-align: center; }
        .ft-time-fields select:focus, .ft-time-fields input:focus { outline: none; box-shadow: none; }
        .ft-time-out { font-size: .8rem; color: var(--gray-500); font-variant-numeric: tabular-nums; }
        .ft-date { height: 2.4rem; border-radius: var(--radius-lg); border: 1px solid var(--gray-300); padding-inline: .6rem; font-size: .9rem; background: white; }
        .ft-other { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; padding: .6rem .7rem; border-radius: var(--radius-lg); border: 1px dashed var(--gray-300); }
        .ft-other select.ft-plain { height: 2.3rem; border-radius: var(--radius-lg); border: 1px solid var(--gray-300); font-size: .85rem; padding-inline: .6rem 2rem; background-color: white; }
        .ft-note { display: flex; gap: .45rem; align-items: flex-start; font-size: .8125rem; padding: .5rem .7rem; border-radius: var(--radius-lg); }
        .ft-note[data-ok="true"] { background: var(--success-50); color: var(--success-800); }
        .ft-note[data-ok="false"] { background: var(--danger-50); color: var(--danger-800); }
        .ft-refused { display: flex; flex-direction: column; gap: .4rem; text-align: start; width: 100%; max-width: 40rem; margin-top: .6rem; }
        .ft-refused li { display: flex; flex-wrap: wrap; gap: .25rem .6rem; font-size: .8125rem; padding: .5rem .7rem; border-radius: var(--radius-lg); background: var(--gray-50); }
        .ft-refused li strong { min-width: 7rem; }

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
        .ft-chip[data-typed] { border-style: dashed; border-color: var(--primary-400); }
        .ft-chip em.ft-typed { color: var(--primary-700); }
        .ft-day-add { font-size: .78rem; font-weight: 600; color: var(--primary-700); padding: .35rem .6rem; border-radius: var(--radius-lg); border: 1px dashed var(--gray-300); align-self: center; }
        .ft-day-add:hover, .ft-day-add:focus-visible { border-color: var(--primary-400); background: var(--primary-50); }
        .ft-missed { font-size: .78rem; color: var(--warning-700); margin: 0; }

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
        .dark .ft-switch, .dark .ft-num { background: var(--gray-800); }
        .dark .ft-switch button[aria-selected="true"] { background: var(--gray-900); color: var(--primary-400); }
        .dark .ft-board-scroll, .dark .ft-colhead, .dark .ft-dayswitch button, .dark .ft-dayswitch select, .dark .ft-daypick button { border-color: var(--gray-700); }
        .dark .ft-corner, .dark .ft-colhead, .dark .ft-rowhead { background: var(--gray-900); }
        .dark .ft-colhead { color: var(--gray-300); }
        .dark .ft-colhead[data-has] { color: var(--primary-400); }
        .dark .ft-c, .dark .ft-swatch { background-color: var(--gray-800); color: var(--gray-500); }
        .dark .ft-c[data-night] { background-image: repeating-linear-gradient(135deg, rgb(255 255 255 / .05) 0 3px, transparent 3px 7px); }
        .dark .ft-swatch[data-night] { background-image: repeating-linear-gradient(135deg, rgb(255 255 255 / .2) 0 2px, transparent 2px 5px); }
        .dark .ft-c[data-level="1"], .dark .ft-swatch[data-level="1"] { background-color: color-mix(in oklab, var(--primary-500) 22%, var(--gray-900)); color: var(--primary-200); }
        .dark .ft-c[data-level="2"], .dark .ft-swatch[data-level="2"] { background-color: color-mix(in oklab, var(--primary-500) 40%, var(--gray-900)); color: white; }
        .dark .ft-c[data-level="3"], .dark .ft-swatch[data-level="3"] { background-color: color-mix(in oklab, var(--primary-500) 62%, var(--gray-900)); color: white; }
        .dark .ft-c[data-level="4"], .dark .ft-swatch[data-level="4"] { background-color: var(--primary-600); color: white; }
        .dark .ft-c[aria-pressed="true"] { box-shadow: inset 0 0 0 2px var(--primary-300); }
        .dark .ft-c[aria-pressed="true"]::after { background: var(--primary-300); }
        .dark .ft-board[data-heat="false"] .ft-c[aria-pressed="true"] { background-color: var(--primary-600); }
        .dark .ft-c[data-drag="on"] { background-color: color-mix(in oklab, var(--primary-500) 45%, var(--gray-900)); }
        .dark .ft-swatch[data-marked] { box-shadow: inset 0 0 0 2px var(--primary-300); background: transparent; }
        .dark .ft-range { background: color-mix(in oklab, var(--primary-500) 16%, var(--gray-900)); color: var(--primary-200); border-color: color-mix(in oklab, var(--primary-500) 35%, var(--gray-900)); }
        .dark .ft-range button { color: var(--primary-300); }
        .dark .ft-range button:hover { background: color-mix(in oklab, var(--primary-500) 25%, var(--gray-900)); }
        .dark .ft-night-toggle, .dark .ft-other, .dark .ft-day-add { border-color: var(--gray-700); color: var(--gray-300); }
        .dark .ft-night-free, .dark .ft-chip em.ft-typed, .dark .ft-day-add { color: var(--primary-400); }
        .dark .ft-time-fields, .dark .ft-date, .dark .ft-other select.ft-plain { background: var(--gray-900); border-color: var(--gray-700); color: var(--gray-100); }
        .dark .ft-time-fields option, .dark .ft-dayswitch option, .dark .ft-other option { background: var(--gray-900); }
        .dark .ft-field > span { color: var(--gray-300); }
        .dark .ft-daypick button { color: var(--gray-300); }
        .dark .ft-daypick button[aria-pressed="true"] { background: var(--primary-600); color: white; border-color: var(--primary-600); }
        .dark .ft-note[data-ok="true"] { background: color-mix(in oklab, var(--success-500) 12%, transparent); color: var(--success-300); }
        .dark .ft-note[data-ok="false"] { background: color-mix(in oklab, var(--danger-500) 12%, transparent); color: var(--danger-300); }
        .dark .ft-refused li { background: var(--gray-800); }
        .dark .ft-missed { color: var(--warning-400); }
        .dark .ft-pattern { background: var(--gray-800); }
        .dark .ft-reason[data-tone="danger"] { background: color-mix(in oklab, var(--danger-500) 12%, transparent); border-color: var(--danger-800); color: var(--danger-300); }

        @media (prefers-reduced-motion: no-preference) {
            .ft-board-wrap { animation: ft-up .3s ease-out backwards; }
            .ft-range, .ft-note, .ft-chip[data-typed] { animation: ft-fade .25s ease-out backwards; }
            .ft-other { animation: ft-up .2s ease-out backwards; }
            .ft-bar { animation: ft-up .3s ease-out backwards; }
            @keyframes ft-fade { from { opacity: 0; transform: scale(.85); } }
            @keyframes ft-up { from { opacity: 0; transform: translateY(.75rem); } }
        }
    </style>

    <div class="ft">
        {{-- What is booked: a private course, a seat in a group, or a trial. --}}
        <div class="ft-mode" role="radiogroup" aria-label="{{ __($gw.'mode.label') }}">
            @foreach (['series' => ['heroicon-o-user', null], 'group' => ['heroicon-o-user-group', 'group'], 'trial' => ['heroicon-o-sparkles', null]] as $m => [$icon, $tone])
                <button type="button" role="radio" aria-checked="{{ $mode === $m ? 'true' : 'false' }}" wire:click="setMode('{{ $m }}')" wire:key="mode-{{ $m }}"
                    @if ($tone) data-tone="{{ $tone }}" @endif>
                    <span class="ft-mode-ico" aria-hidden="true"><x-filament::icon :icon="$icon" /></span>
                    <span><strong>{{ __($gw.'mode.'.$m.'.title') }}</strong><small>{{ __($gw.'mode.'.$m.'.line') }}</small></span>
                </button>
            @endforeach
        </div>

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

        {{-- 2. The times: where teachers are free now, the student's own hours, or one exact time. --}}
        @if ($group)
            {{-- 2 (group mode). The groups with a free seat, first. --}}
            <section class="ft-step" id="ft-groups" data-state="{{ $ready ? 'now' : 'later' }}" aria-labelledby="ft-step-groups"
                x-data x-init="@js($this->focusGroup !== null) && $nextTick(() => document.querySelector('[data-focus]')?.scrollIntoView({ block: 'center' }))">
                <div class="ft-step-head">
                    <span class="ft-num">2</span>
                    <div>
                        <h2 id="ft-step-groups">{{ __($gw.'open.title') }}</h2>
                        <p class="ft-muted">{{ $ready ? __($gw.'open.hint', ['city' => $this->city($tz)]) : __('scheduling::find.free.wait.title') }}</p>
                    </div>
                </div>
                @if (! $ready)
                    <div class="ft-card ft-reason">
                        <x-filament::icon icon="heroicon-o-user-group" style="width: 1.75rem; height: 1.75rem; color: var(--gray-400)" />
                        <strong>{{ __($gw.'open.wait') }}</strong>
                    </div>
                @elseif ($openGroups === [])
                    <div class="ft-card ft-reason" role="status">
                        <x-filament::icon icon="heroicon-o-user-group" style="width: 1.75rem; height: 1.75rem; color: var(--gray-400)" />
                        <strong>{{ __($gw.'open.none.title') }}</strong>
                        <p class="ft-muted">{{ array_filter($this->cells) !== [] ? __($gw.'open.none.hours') : __($gw.'open.none.hint') }}</p>
                    </div>
                @else
                    <div class="ft-groups lms-stagger">
                        @foreach ($openGroups as $og)
                            <article class="gr-card lms-lift" wire:key="og-{{ $og['id'] }}" data-status="{{ $og['status'] }}" @if ($og['id'] === $this->focusGroup) data-focus @endif>
                                <div class="gr-card-head">
                                    <div style="display: flex; gap: .65rem; min-width: 0">
                                        <span class="gr-avatar" aria-hidden="true">{{ mb_substr(preg_replace('/^(أ\.|د\.)\s*/u', '', $og['teacher_name']), 0, 1) }}</span>
                                        <div style="min-width: 0">
                                            <h3 class="gr-card-title">{{ $og['teacher_name'] }}</h3>
                                            <p class="gr-card-sub">{{ collect([$og['name'], \Modules\Scheduling\Filament\GroupView::shape($og['minutes'], $og['per_week']), $og['age_group'] ? \Modules\People\Contracts\AgeGroup::tryFrom($og['age_group'])?->label() : null])->filter()->implode(' · ') }}</p>
                                        </div>
                                    </div>
                                    <span class="gr-pill" data-tone="{{ \Modules\Scheduling\Filament\GroupView::tone($og['status']) }}">{{ \Modules\Scheduling\Filament\GroupView::status($og['status']) }}</span>
                                </div>
                                <div class="gr-times"><x-filament::icon icon="heroicon-m-clock" /><span>{{ \Modules\Scheduling\Filament\GroupView::timesLine($og['times']) }}</span></div>
                                <div class="gr-meta">
                                    @if ($og['status'] === 'running')
                                        <span>{{ trans_choice('scheduling::group_screens.card.held', $og['held'], ['n' => $og['held']]) }}</span>
                                        @if ($og['next'])<span><x-filament::icon icon="heroicon-m-calendar" />{{ __('scheduling::group_screens.card.next') }} <strong>{{ $og['next']['label'] }}</strong></span>@endif
                                    @else
                                        <span>{{ __('scheduling::group_screens.card.forming_line') }}</span>
                                    @endif
                                </div>
                                <div class="gr-foot">
                                    @include('scheduling::filament.groups.seats', ['taken' => $og['taken'], 'capacity' => $og['capacity']])
                                    <x-filament::button size="sm" icon="heroicon-m-user-plus" wire:click="join('{{ $og['id'] }}')" color="info">{{ __($gw.'open.join') }}</x-filament::button>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            <div class="ft-or" role="separator">{{ __($gw.'or_new') }}</div>
        @endif

        <section class="ft-step" data-state="{{ ! $ready ? 'later' : ($this->searched ? 'done' : 'now') }}" aria-labelledby="ft-step-2">
            <div class="ft-head-row">
                <div class="ft-step-head">
                    <span class="ft-num">{{ $n(2) }}</span>
                    <div>
                        <h2 id="ft-step-2">{{ $group ? __($gw.'steps.times') : __('scheduling::find.steps.times') }}</h2>
                        <p class="ft-muted">{{ __('scheduling::find.grid.clock', ['city' => $this->city($tz)]) }}</p>
                    </div>
                </div>
                <div class="ft-switch" role="tablist" aria-label="{{ __('scheduling::find.steps.times') }}">
                    @foreach ($trial ? ['exact', 'free', 'mine'] : ['free', 'mine', 'exact'] as $name)
                        <button type="button" role="tab" aria-selected="{{ $tab === $name ? 'true' : 'false' }}" wire:click="showTimes('{{ $name }}')">{{ __('scheduling::find.tabs.'.$name) }}</button>
                    @endforeach
                </div>
            </div>

            @if ($tab === 'exact' && $ready && $this->noTeachers !== null)
                @include('scheduling::filament.find-teacher-reason', ['reason' => $this->noTeachers])
            @elseif ($tab === 'exact')
                @php
                    [$eh, $em] = $this->exact ? array_map('intval', explode(':', $this->exact['time'])) : [17, 0];
                @endphp
                <div class="ft-card ft-exact" wire:key="exact-{{ $trial ? 'trial' : 'series' }}" x-on:ft-exact-search.window="$wire.searchExact(days, date, time())"
                    x-data="{ days: @js($this->exact['days'] ?? []), date: @js($this->exact['date'] ?? $tzNow->copy()->addDay()->toDateString()), h: {{ $eh }}, m: {{ $em }},
                        time() { return String(this.h).padStart(2, '0') + ':' + String(Math.min(59, Math.max(0, this.m | 0))).padStart(2, '0') },
                        toggle(d) { this.days = this.days.includes(d) ? this.days.filter(x => x !== d) : [...this.days, d] } }">
                    <div>
                        <h3 class="ft-h">{{ __('scheduling::find.exact.title') }}</h3>
                        <p class="ft-muted">{{ __('scheduling::find.exact.'.($trial ? 'hint_trial' : 'hint_series')) }}</p>
                    </div>
                    <div class="ft-exact-row">
                        @if ($trial)
                            <label class="ft-field">
                                <span>{{ __('scheduling::find.exact.date') }}</span>
                                <input type="date" class="ft-date" x-model="date" min="{{ $tzNow->toDateString() }}" max="{{ $tzNow->copy()->addMonths(3)->toDateString() }}">
                            </label>
                        @else
                            <div class="ft-field" role="group" aria-labelledby="ft-exact-days">
                                <span id="ft-exact-days">{{ __('scheduling::find.exact.days') }}</span>
                                <div class="ft-daypick">
                                    @foreach ($days as $day)
                                        <button type="button" x-on:click="toggle({{ $day }})" x-bind:aria-pressed="days.includes({{ $day }}) ? 'true' : 'false'"
                                            aria-pressed="{{ in_array($day, $this->exact['days'] ?? [], true) ? 'true' : 'false' }}">{{ $this->dayName($day) }}</button>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        <div class="ft-field">
                            <span>{{ __('scheduling::find.exact.time') }}</span>
                            @include('scheduling::filament.find-teacher-time')
                        </div>
                        <x-filament::button icon="heroicon-m-magnifying-glass" :disabled="! $ready" x-on:click="$wire.searchExact(days, date, time())">
                            {{ __('scheduling::find.exact.search') }}
                        </x-filament::button>
                    </div>
                    @if (! $ready)
                        <p class="ft-muted">{{ __('scheduling::find.free.wait.title') }}</p>
                    @endif
                </div>
            @elseif ($tab === 'free' && ! $ready)
                <div class="ft-card ft-reason">
                    <x-filament::icon icon="heroicon-o-calendar-days" style="width: 1.75rem; height: 1.75rem; color: var(--gray-400)" />
                    <strong>{{ __('scheduling::find.free.wait.title') }}</strong>
                    <p class="ft-muted">{{ __('scheduling::find.free.wait.hint') }}</p>
                </div>
            @elseif ($tab === 'free' && $free['teachers'] === 0)
                @include('scheduling::filament.find-teacher-reason', ['reason' => $this->emptyReason])
            @else
                @if ($tab === 'free')
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
                @endif

                <div class="ft-card">
                    <div class="ft-head-row">
                        <div>
                            <h3 class="ft-h">{{ $tab === 'free' ? __('scheduling::find.free.map') : __('scheduling::find.grid.title') }}</h3>
                            <p class="ft-muted">{{ $tab === 'free' ? __('scheduling::find.free.map_hint', ['minutes' => (int) $this->data['duration']]) : __('scheduling::find.grid.hint', ['city' => $this->city($tz)]) }}</p>
                        </div>
                    </div>

                    {{-- What is marked, as ranges: each comes off with its ✕. --}}
                    <div class="ft-chosen" aria-live="polite">
                        <small>{{ __('scheduling::find.board.chosen') }}</small>
                        @forelse ($ranges as $range)
                            @php $rangeLabel = $this->dayName($range['weekday']).' '.Clock::hour($range['from']).'–'.Clock::hour($range['to']); @endphp
                            <span class="ft-range" wire:key="range-{{ $range['weekday'] }}-{{ $range['from'] }}-{{ $range['to'] }}">
                                {{ $rangeLabel }}
                                <button type="button" wire:click="clearRange({{ $range['weekday'] }}, {{ $range['from'] }}, {{ $range['to'] }})" aria-label="{{ __('scheduling::find.board.remove', ['range' => $rangeLabel]) }}">
                                    <x-filament::icon icon="heroicon-m-x-mark" class="ft-ico" style="width: .8rem; height: .8rem" />
                                </button>
                            </span>
                        @empty
                            <span class="ft-muted">{{ __('scheduling::find.board.none') }}</span>
                        @endforelse
                        @if ($ranges !== [])
                            <button type="button" class="ft-link" wire:click="clearCells" style="margin-inline-start: auto">{{ __('scheduling::find.grid.clear') }}</button>
                        @endif
                    </div>

                    @include('scheduling::filament.find-teacher-board', [
                        'heat' => $tab === 'free' ? $free['cells'] : null,
                        'cells' => $this->cells,
                        'label' => $tab === 'free' ? __('scheduling::find.free.map') : __('scheduling::find.grid.title'),
                    ])

                    <p class="ft-muted" style="margin-top: .6rem">{{ __('scheduling::find.board.drag') }} {{ $tab === 'free' ? __('scheduling::find.free.tap') : '' }}</p>
                </div>
            @endif
        </section>

        {{-- 3. The teachers, ranked; the page comes here after a search. --}}
        <section class="ft-step" id="ft-teachers" data-state="{{ $this->searched ? 'now' : 'later' }}" aria-labelledby="ft-step-3"
            x-data x-on:ft-results.window="$nextTick(() => $el.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' }))">
            <div class="ft-step-head">
                <span class="ft-num">{{ $n(3) }}</span>
                <div>
                    <h2 id="ft-step-3">{{ $group ? __($gw.'steps.teachers') : __('scheduling::find.steps.teachers') }}</h2>
                    @if ($this->searched && $results !== [])
                        <p class="ft-muted">
                            @if ($exactAt) {{ __('scheduling::find.exact.searched', ['at' => $exactAt]) }} · @endif
                            {{ trans_choice('scheduling::find.found', count($results), ['count' => count($results)]) }} · {{ __('scheduling::find.found_hint', ['city' => $this->city($tz)]) }}
                        </p>
                    @endif
                </div>
            </div>

            @if (! $this->searched)
                <div class="ft-card ft-reason">
                    <x-filament::icon :icon="$trial ? 'heroicon-o-sparkles' : 'heroicon-o-magnifying-glass'" style="width: 1.75rem; height: 1.75rem; color: var(--gray-400)" />
                    <strong>{{ __('scheduling::find.'.($trial ? 'start_trial' : 'start').'.title') }}</strong>
                    <p class="ft-muted">{{ __('scheduling::find.'.($trial ? 'start_trial' : 'start').'.hint') }}</p>
                </div>
            @elseif ($results === [] && $exactAnswer !== null && $exactAnswer['refused'] !== [])
                {{-- Nobody at the exact time: each teacher's reasons. --}}
                <div class="ft-card ft-reason" role="status" wire:key="exact-nobody">
                    <x-filament::icon icon="heroicon-o-clock" style="width: 1.75rem; height: 1.75rem; color: var(--gray-400)" />
                    <strong>{{ __('scheduling::find.exact.nobody.title', ['at' => $exactAt]) }}</strong>
                    <p class="ft-muted">{{ __('scheduling::find.exact.nobody.hint') }}</p>
                    <ul class="ft-refused lms-stagger">
                        @foreach (array_slice($exactAnswer['refused'], 0, 8) as $row)
                            <li wire:key="refused-{{ $row['teacher']->id }}">
                                <strong>{{ $row['teacher']->name }}</strong>
                                <span>{{ collect($row['checks'])->map(fn ($check) => $this->explain($check, $this->exact['date'])['text'])->implode(' ') }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <div class="ft-reason-actions">
                        <x-filament::button wire:click="showTimes('free')" icon="heroicon-m-sparkles" size="sm" color="gray" outlined>{{ __('scheduling::find.reasons.see_free') }}</x-filament::button>
                    </div>
                </div>
            @elseif ($results === [])
                @include('scheduling::filament.find-teacher-reason', ['reason' => $this->emptyReason])
            @else
                <div class="ft-list lms-stagger">
                    @foreach ($results as $result)
                        @php $t = $result['teacher']; @endphp
                        <article class="ft-card ft-teacher lms-lift" wire:key="t-{{ $t->id }}" data-picked="{{ $this->pickedTeacher === $t->id ? 'true' : 'false' }}" @if ($group) data-group @endif>
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
                                    @if ($group && ($t->max_group_size ?? null))
                                        <span class="gr-badge" style="margin-top: .3rem"><x-filament::icon icon="heroicon-m-user-group" class="gr-badge-ico" />{{ trans_choice($gw.'teacher_max', (int) $t->max_group_size, ['n' => (int) $t->max_group_size]) }}</span>
                                    @endif
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

                            @if (! $group && ! $trial && ($theirs = $this->groupsOf($t->id)) !== [])
                                {{-- The same teacher has a group with a seat at these hours: a cheaper way in. --}}
                                <div class="ft-hint-group" role="note" wire:key="hint-{{ $t->id }}">
                                    <x-filament::icon icon="heroicon-m-user-group" />
                                    <span>{{ trans_choice($gw.'hint', count($theirs), ['n' => count($theirs), 'times' => \Modules\Scheduling\Filament\GroupView::timesLine($theirs[0]['times'])]) }}</span>
                                    <button type="button" wire:click="setMode('group')">{{ __($gw.'hint_open') }}</button>
                                </div>
                            @endif

                            @if ($result['pattern'] && ! $trial)
                                <div class="ft-pattern">
                                    <span>{{ __('scheduling::find.pattern') }}</span>
                                    <strong>{{ collect($this->inWeekOrder($result['pattern']))->map(fn ($c) => $this->dayName($c->studentWeekday).' '.Clock::wall($c->studentTime))->implode(' + ') }}</strong>
                                    @if (! $this->holdBatch)
                                        <button type="button" class="ft-link" wire:click="usePattern('{{ $t->id }}')">{{ __('scheduling::find.use_pattern') }}</button>
                                    @endif
                                </div>
                            @endif

                            @if (! empty($result['missed']))
                                <p class="ft-missed">{{ __('scheduling::find.exact.missed', ['times' => collect($result['missed'])->map(fn ($c) => $this->dayName($c->studentWeekday).' '.Clock::wall($c->studentTime))->implode('، ')]) }}</p>
                            @endif

                            @php
                                $firstChip = collect($result['chips'])->flatten()->first();
                                [$oh, $om] = $firstChip ? array_map('intval', explode(':', $firstChip->studentTime)) : [17, 0];
                            @endphp
                            <div class="ft-days" x-data="{ other: null, h: {{ $oh }}, m: {{ $om }}, date: @js($this->exact['date'] ?? $tzNow->copy()->addDay()->toDateString()),
                                time() { return String(this.h).padStart(2, '0') + ':' + String(Math.min(59, Math.max(0, this.m | 0))).padStart(2, '0') } }">
                                @foreach ($this->inWeekOrder($result['chips']) as $day => $chips)
                                    <div class="ft-day">
                                        <span>{{ $this->dayName($day) }}</span>
                                        <div class="ft-chips">
                                            @foreach ($chips as $chip)
                                                <button type="button" class="ft-chip" wire:click="pick('{{ $t->id }}', '{{ $chip->key() }}')" wire:key="chip-{{ $t->id }}-{{ $chip->key() }}"
                                                    @if ($chip->typed) data-typed @endif
                                                    aria-pressed="{{ $this->pickedTeacher === $t->id && in_array($chip->key(), $this->picked, true) ? 'true' : 'false' }}"
                                                    title="{{ $chip->full() ? __('scheduling::find.all_weeks', ['n' => $chip->weeks]) : __('scheduling::find.conflicts_on', ['dates' => implode('، ', $chip->conflictDates)]) }}">
                                                    <strong>{{ Clock::wall($chip->studentTime) }}</strong>
                                                    <small>{{ Clock::wall($chip->teacherTime) }} {{ $this->city($t->timezone ?? 'UTC') }}</small>
                                                    @if ($trial)
                                                        <em class="ft-first">{{ __('scheduling::find.first_free', ['date' => $this->firstFree($chip, $t->timezone ?? 'UTC')]) }}</em>
                                                    @elseif (! $chip->full())
                                                        <em>{{ $chip->coverage }}/{{ $chip->weeks }}</em>
                                                    @endif
                                                    @if ($chip->typed)
                                                        <em class="ft-typed">{{ __('scheduling::find.exact.typed') }}</em>
                                                    @endif
                                                </button>
                                            @endforeach
                                            @if (! $trial && ! $this->holdBatch)
                                                <button type="button" class="ft-day-add" x-on:click="other = {{ $day }}">{{ __('scheduling::find.exact.other') }}</button>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach

                                @if (! $this->holdBatch)
                                    <div class="ft-day">
                                        <span></span>
                                        <div class="ft-chips">
                                            <button type="button" class="ft-day-add" x-show="other === null" x-on:click="other = @js($trial ? 0 : array_key_first($this->inWeekOrder($result['chips'])) ?? $days[0])">
                                                {{ __('scheduling::find.exact.other_for', ['name' => $t->name]) }}
                                            </button>
                                        </div>
                                    </div>
                                    <div class="ft-other" x-show="other !== null" x-cloak role="group" aria-label="{{ __('scheduling::find.exact.other_for', ['name' => $t->name]) }}">
                                        @if ($trial)
                                            <input type="date" class="ft-date" x-model="date" min="{{ $tzNow->toDateString() }}" max="{{ $tzNow->copy()->addMonths(3)->toDateString() }}" aria-label="{{ __('scheduling::find.exact.date') }}">
                                        @else
                                            <select class="ft-plain" x-model.number="other" aria-label="{{ __('scheduling::find.exact.day') }}">
                                                @foreach ($days as $day)
                                                    <option value="{{ $day }}">{{ $this->dayName($day) }}</option>
                                                @endforeach
                                            </select>
                                        @endif
                                        @include('scheduling::filament.find-teacher-time')
                                        <x-filament::button size="sm" icon="heroicon-m-check" x-on:click="$wire.checkTime('{{ $t->id }}', other || 0, time(), {{ $trial ? 'date' : 'null' }})">{{ __('scheduling::find.exact.check') }}</x-filament::button>
                                        <button type="button" class="ft-link" x-on:click="other = null">{{ __('scheduling::find.exact.cancel') }}</button>
                                    </div>
                                @endif

                                @if ($note = $this->typedNotes[$t->id] ?? null)
                                    <div class="ft-note" data-ok="{{ $note['ok'] ? 'true' : 'false' }}" role="status" wire:key="note-{{ $t->id }}-{{ md5($note['text']) }}">
                                        <x-filament::icon :icon="$note['ok'] ? 'heroicon-m-check-circle' : 'heroicon-m-no-symbol'" class="ft-ico" style="margin-top: .1rem" />
                                        <span>{{ $note['text'] }}</span>
                                    </div>
                                @endif
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
            @if ($step === 'search' && $tab === 'exact')
                <x-filament::button x-data x-on:click="$dispatch('ft-exact-search')" icon="heroicon-m-magnifying-glass" :disabled="! $ready">{{ __('scheduling::find.exact.search') }}</x-filament::button>
            @elseif ($step === 'search')
                <x-filament::button wire:click="search" icon="heroicon-m-magnifying-glass" :disabled="! $ready">{{ __('scheduling::find.search') }}</x-filament::button>
            @elseif ($step === 'hold' && $group)
                <x-filament::button wire:click="startGroup" icon="heroicon-m-user-group" color="info">{{ __($gw.'create.bar') }}</x-filament::button>
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
