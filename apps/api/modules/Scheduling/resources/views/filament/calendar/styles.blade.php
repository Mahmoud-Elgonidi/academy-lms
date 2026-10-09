{{-- The academy calendar: the bar, status chips, the week grid, the day timeline, the month, the lists (academy colours through Filament's variables). --}}
<style>
    .cal { --cal-line: var(--gray-100); display: flex; flex-direction: column; gap: .85rem; min-width: 0; }
    .cal-card { background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl, .75rem); min-width: 0; }
    .cal-icon { width: 1.1rem; height: 1.1rem; flex-shrink: 0; }
    [dir="ltr"] .cal-flip { transform: scaleX(-1); }
    .cal-sr { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; }
    .cal-muted { color: var(--gray-500); font-size: .85rem; }
    .cal-note { color: var(--gray-500); font-size: .78rem; margin: 0; }
    .cal-ltr { direction: ltr; unicode-bidi: isolate; }
    .cal-block { display: block; color: var(--gray-500); }

    /* Status colours: every chip, pill, dot and lesson takes them from data-status. */
    :where(.cal-ev, .cal-tev, .cal-pill, .cal-chip, .cal-dot, .cal-mbar span, .cal-ag-row) { --st: var(--primary-500); --st-bg: var(--primary-50); --st-fg: var(--primary-800); }
    :where(.cal-ev, .cal-tev, .cal-pill, .cal-chip, .cal-dot, .cal-mbar span, .cal-ag-row)[data-status="in_progress"] { --st: var(--primary-700); --st-bg: var(--primary-600); --st-fg: white; }
    :where(.cal-ev, .cal-tev, .cal-pill, .cal-chip, .cal-dot, .cal-mbar span, .cal-ag-row)[data-status="completed"] { --st: var(--success-500); --st-bg: var(--success-50); --st-fg: var(--success-800); }
    :where(.cal-ev, .cal-tev, .cal-pill, .cal-chip, .cal-dot, .cal-mbar span, .cal-ag-row)[data-status="student_absent"] { --st: var(--warning-500); --st-bg: var(--warning-50); --st-fg: var(--warning-800); }
    :where(.cal-ev, .cal-tev, .cal-pill, .cal-chip, .cal-dot, .cal-mbar span, .cal-ag-row)[data-status="teacher_absent"] { --st: var(--danger-500); --st-bg: var(--danger-50); --st-fg: var(--danger-800); }
    :where(.cal-ev, .cal-tev, .cal-pill, .cal-chip, .cal-dot, .cal-mbar span, .cal-ag-row)[data-status="needs_review"] { --st: var(--info-500); --st-bg: var(--info-50); --st-fg: var(--info-800); }
    :where(.cal-ev, .cal-tev, .cal-pill, .cal-chip, .cal-dot, .cal-mbar span, .cal-ag-row)[data-status="cancelled"] { --st: var(--gray-400); --st-bg: var(--gray-100); --st-fg: var(--gray-500); }
    .cal-dot { display: inline-block; width: .55rem; height: .55rem; border-radius: 999px; background: var(--st); margin-inline-end: .3rem; vertical-align: 1px; flex-shrink: 0; }
    .cal-pill { flex-shrink: 0; font-size: .72rem; font-weight: 600; padding: .12rem .55rem; border-radius: 999px; background: var(--st-bg); color: var(--st-fg); white-space: nowrap; }
    .cal-tag, .cal-ev-tags i, .cal-ag-main i { font-style: normal; font-size: .68rem; font-weight: 600; padding: .05rem .4rem; border-radius: 999px; background: var(--gray-100); color: var(--gray-700); white-space: nowrap; }
    :is(.cal-tag, .cal-ev-tags i, .cal-ag-main i)[data-tone="danger"] { background: var(--danger-50); color: var(--danger-700); }
    .cal-badge { font-size: .68rem; font-weight: 700; padding: .05rem .45rem; border-radius: 999px; background: var(--primary-600); color: white; }

    /* The bar: moving around, the range, unit and display, the clock. */
    .cal-bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .6rem .9rem; padding: .55rem .7rem; }
    .cal-nav { display: flex; align-items: center; gap: .35rem; min-width: 0; }
    .cal-icon-btn { display: grid; place-items: center; width: 2rem; height: 2rem; border-radius: .55rem; border: 1px solid var(--gray-200); color: var(--gray-600); }
    .cal-icon-btn:hover, .cal-today:hover { color: var(--primary-600); border-color: var(--primary-300); }
    .cal-today { height: 2rem; padding: 0 .75rem; border-radius: .55rem; border: 1px solid var(--gray-200); font-size: .82rem; font-weight: 600; color: var(--gray-700); }
    .cal-range { font-size: 1.05rem; font-weight: 700; margin-inline-start: .35rem; overflow-wrap: anywhere; }
    .cal-tools { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
    .cal-seg { display: inline-flex; padding: .18rem; border-radius: .65rem; background: var(--gray-100); gap: .15rem; }
    .cal-seg button { font-size: .8rem; font-weight: 600; padding: .3rem .7rem; border-radius: .5rem; color: var(--gray-600); white-space: nowrap; }
    .cal-seg button[aria-pressed="true"] { background: white; color: var(--primary-700); box-shadow: 0 1px 2px rgb(0 0 0 / .08); }
    .cal-zone { display: inline-flex; align-items: center; gap: .3rem; font-size: .8rem; color: var(--gray-600); }
    .cal-zone select { border: 1px solid var(--gray-200); border-radius: .5rem; padding: .25rem 1.8rem .25rem .5rem; font-size: .8rem; background-color: white; color: inherit; max-width: 12rem; }
    [dir="rtl"] .cal-zone select { padding: .25rem .5rem .25rem 1.8rem; }

    .cal-filters { display: flex; align-items: center; gap: .6rem; padding: .55rem .7rem; }
    .cal-filters-form { flex: 1 1 auto; min-width: 0; }
    .cal-clear { display: inline-flex; align-items: center; gap: .25rem; flex-shrink: 0; font-size: .8rem; font-weight: 600; color: var(--danger-600); }
    .cal-clear:hover { text-decoration: underline; }

    .cal-sum { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem 1rem; }
    .cal-totals { display: flex; align-items: baseline; gap: .55rem; font-size: .85rem; color: var(--gray-600); }
    .cal-totals strong { font-size: 1.05rem; color: var(--gray-950); }
    .cal-chips { display: flex; gap: .3rem; overflow-x: auto; scrollbar-width: none; max-width: 100%; padding-block: .1rem; }
    .cal-chips::-webkit-scrollbar { display: none; }
    .cal-chip { flex-shrink: 0; display: inline-flex; align-items: center; gap: .1rem; font-size: .78rem; font-weight: 600; padding: .3rem .65rem; border-radius: 999px; border: 1px solid var(--gray-200); background: white; color: var(--gray-700); white-space: nowrap; }
    .cal-chip b { font-variant-numeric: tabular-nums; margin-inline-start: .25rem; color: var(--gray-500); }
    .cal-chip[data-zero] { opacity: .55; }
    .cal-chip:hover { border-color: var(--st, var(--primary-300)); }
    .cal-chip[aria-pressed="true"] { background: var(--st-bg); border-color: var(--st); color: var(--st-fg); }
    .cal-chip[aria-pressed="true"] b { color: inherit; }
    .cal-chip[data-all][aria-pressed="true"] { background: var(--primary-600); border-color: var(--primary-600); color: white; }

    /* The week: hours down the side, a column a day; lessons by minutes, side by side where they overlap. */
    .cal-grid-card { padding: 0; overflow: hidden; }
    .cal-week { --hour-h: 4rem; --axis-w: 3.4rem; }
    .cal-week-head, .cal-week-body { display: grid; grid-template-columns: var(--axis-w) repeat(7, minmax(0, 1fr)); }
    .cal-week-head { border-bottom: 1px solid var(--gray-200); }
    .cal-wday { display: flex; flex-direction: column; align-items: center; gap: .05rem; padding: .5rem .25rem; border-inline-start: 1px solid var(--gray-100); min-width: 0; }
    .cal-wday span { font-size: .75rem; color: var(--gray-500); }
    .cal-wday strong { font-size: .95rem; }
    .cal-wday small { font-size: .7rem; color: var(--gray-400); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
    .cal-wday:hover { background: var(--gray-50); }
    .cal-wday[data-today] strong { color: var(--primary-600); }
    .cal-wday[data-today] { box-shadow: inset 0 -3px 0 var(--primary-500); }
    .cal-week-body { height: calc(var(--hours) * var(--hour-h)); }
    .cal-axis { position: relative; }
    .cal-axis span { position: absolute; top: calc(var(--i) * var(--hour-h) + 2px); inset-inline: 0; text-align: center; font-size: .68rem; color: var(--gray-400); font-variant-numeric: tabular-nums; }
    .cal-col { position: relative; overflow: hidden; border-inline-start: 1px solid var(--cal-line); background-image: linear-gradient(to bottom, var(--cal-line) 1px, transparent 1px); background-size: 100% var(--hour-h); }
    .cal-col[data-today] { background-color: color-mix(in oklab, var(--primary-50) 45%, transparent); }
    .cal-shade { position: absolute; inset-inline: 0; top: calc(var(--s) * var(--hour-h) / 60); height: calc(var(--d) * var(--hour-h) / 60); }
    .cal-shade[data-kind="shift"] { background: color-mix(in oklab, var(--primary-100) 55%, transparent); }
    .cal-shade[data-kind="off"], .cal-shade[data-kind="holiday"] { background: repeating-linear-gradient(135deg, var(--cal-line) 0 6px, transparent 6px 12px); }
    .cal-shade[data-kind="holiday"] { background-color: color-mix(in oklab, var(--warning-50) 70%, transparent); }
    .cal-shade span { position: sticky; top: 0; display: block; padding: .2rem .35rem; font-size: .68rem; font-weight: 600; color: var(--gray-500); }
    .cal-ev, .cal-more { position: absolute; top: calc(var(--s) * var(--hour-h) / 60 + 1px); height: calc(var(--d) * var(--hour-h) / 60 - 2px);
        inset-inline-start: calc(var(--lane) * 100% / var(--lanes) + 2px); width: calc(100% / var(--lanes) - 4px); }
    .cal-ev { display: flex; flex-direction: column; gap: .05rem; overflow: hidden; padding: .15rem .35rem; border-radius: .45rem; text-align: start;
        background: var(--st-bg); color: var(--st-fg); border-inline-start: 3px solid var(--st); font-size: .72rem; line-height: 1.25; }
    .cal-ev:hover, .cal-tev:hover { filter: brightness(.97); box-shadow: 0 2px 8px rgb(0 0 0 / .1); z-index: 2; }
    .cal-ev[data-status="cancelled"] .cal-ev-line, .cal-tev[data-status="cancelled"] .cal-ev-line { text-decoration: line-through; }
    .cal-ev[data-live], .cal-tev[data-live] { box-shadow: 0 0 0 2px var(--primary-300); }
    /* The time never gives way; the name and subject end in "…" when the lesson is narrow. */
    .cal-ev-line, .cal-ev-sub { display: flex; gap: .3rem; min-width: 0; white-space: nowrap; }
    :is(.cal-ev-line, .cal-ev-sub) > span { min-width: 0; overflow: hidden; text-overflow: ellipsis; }
    :is(.cal-ev-line, .cal-ev-sub) > b { flex-shrink: 0; font-variant-numeric: tabular-nums; }
    .cal-ev-sub { opacity: .8; }
    .cal-ev-tags { display: flex; gap: .2rem; flex-wrap: wrap; }
    .cal-ev-tags i { font-size: .62rem; padding: 0 .3rem; }
    .cal-ev[data-short] { justify-content: center; padding-block: 0; }
    .cal-ev[data-short] :is(.cal-ev-sub, .cal-ev-tags) { display: none; }
    .cal-more { display: grid; place-items: center; border-radius: .45rem; border: 1px dashed var(--gray-300); background: white; font-size: .75rem; font-weight: 700; color: var(--gray-600); }
    .cal-more:hover { color: var(--primary-600); border-color: var(--primary-400); }
    .cal-now { position: absolute; inset-inline: 0; top: calc((var(--m) - var(--from) * 60) * var(--hour-h) / 60); height: 2px; background: var(--danger-500); z-index: 3; pointer-events: none; }
    .cal-now::before { content: ''; position: absolute; inset-inline-start: -4px; top: -3px; width: 8px; height: 8px; border-radius: 999px; background: var(--danger-500); }
    .cal-legend { display: flex; flex-wrap: wrap; gap: .3rem 1rem; padding: .5rem .8rem; border-top: 1px solid var(--gray-100); font-size: .75rem; color: var(--gray-500); }
    .cal-legend span::before { content: ''; display: inline-block; width: .8rem; height: .8rem; border-radius: .2rem; margin-inline-end: .3rem; vertical-align: -2px; border: 1px solid var(--gray-200); }
    .cal-legend span[data-kind="shift"]::before { background: color-mix(in oklab, var(--primary-100) 55%, transparent); }
    .cal-legend span[data-kind="off"]::before { background: repeating-linear-gradient(135deg, var(--gray-200) 0 3px, transparent 3px 6px); }
    .cal-legend span[data-kind="holiday"]::before { background: repeating-linear-gradient(135deg, var(--gray-200) 0 3px, var(--warning-50) 3px 6px); }

    /* The day: a row per teacher, time across. */
    .cal-day-scroll { overflow-x: auto; }
    .cal-day { --row-h: 2.9rem; --name-w: 11rem; min-width: max(100%, calc(var(--name-w) + var(--hours) * 4.5rem)); }
    .cal-day-head, .cal-row { display: grid; grid-template-columns: var(--name-w) minmax(0, 1fr); }
    .cal-day-head { border-bottom: 1px solid var(--gray-200); }
    .cal-ticks { position: relative; height: 1.9rem; }
    .cal-ticks span { position: absolute; top: .5rem; inset-inline-start: calc(var(--i) * 100% / var(--hours) + 3px); font-size: .68rem; color: var(--gray-400); font-variant-numeric: tabular-nums; }
    .cal-row + .cal-row { border-top: 1px solid var(--gray-100); }
    .cal-row-name { display: flex; flex-direction: column; justify-content: center; gap: .05rem; padding: .35rem .7rem; text-align: start; min-width: 0; border-inline-end: 1px solid var(--gray-100); }
    .cal-row-name strong { font-size: .82rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .cal-row-name small { font-size: .7rem; color: var(--gray-500); }
    .cal-row-name:hover strong { color: var(--primary-600); }
    .cal-track { position: relative; overflow: hidden; height: calc(var(--lanes) * var(--row-h));
        background-image: linear-gradient(to right, var(--cal-line) 1px, transparent 1px); background-size: calc(100% / var(--hours)) 100%; }
    [dir="rtl"] .cal-track { background-image: linear-gradient(to left, var(--cal-line) 1px, transparent 1px); background-position: right top; }
    .cal-track .cal-shade { top: 0; bottom: 0; height: auto; inset-inline: auto; inset-inline-start: calc(var(--s) * 100% / (var(--hours) * 60)); width: calc(var(--d) * 100% / (var(--hours) * 60)); }
    .cal-tev { position: absolute; top: calc(var(--lane) * var(--row-h) + 4px); height: calc(var(--row-h) - 8px);
        inset-inline-start: calc(var(--s) * 100% / (var(--hours) * 60) + 1px); width: calc(var(--d) * 100% / (var(--hours) * 60) - 2px);
        display: flex; flex-direction: column; justify-content: center; overflow: hidden; padding: 0 .35rem; border-radius: .4rem; text-align: start;
        background: var(--st-bg); color: var(--st-fg); border-inline-start: 3px solid var(--st); font-size: .7rem; line-height: 1.2; }
    .cal-tev .cal-ev-tags { display: none; }
    .cal-tev .cal-ev-line { font-weight: 600; }
    .cal-tev-more { display: grid; place-items: center; background: white; border: 1px dashed var(--gray-300); color: var(--gray-600); font-weight: 700; }
    .cal-now-v { position: absolute; top: 0; bottom: 0; width: 2px; inset-inline-start: calc((var(--m) - var(--from) * 60) * 100% / (var(--hours) * 60)); background: var(--danger-500); z-index: 3; pointer-events: none; }
    .cal-banner { display: flex; align-items: center; gap: .4rem; padding: .55rem .8rem; border-radius: .75rem; background: var(--warning-50); color: var(--warning-800); font-size: .85rem; font-weight: 600; }

    /* The month: counts per day, a bar of statuses. */
    .cal-month-card { padding: .35rem; }
    .cal-month { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: .3rem; }
    .cal-mhead { text-align: center; font-size: .75rem; font-weight: 600; color: var(--gray-500); padding: .3rem 0; }
    .cal-short { display: none; }
    .cal-mday { display: flex; flex-direction: column; align-items: stretch; gap: .25rem; min-height: 6.4rem; padding: .4rem .5rem; border-radius: .6rem; border: 1px solid var(--gray-100); text-align: start; min-width: 0; color: var(--gray-400); }
    .cal-mday[data-in] { color: inherit; background: white; }
    .cal-mday:not([data-in]) { background: var(--gray-50); }
    .cal-mday:hover { border-color: var(--primary-300); }
    .cal-mday[data-today] { border-color: var(--primary-500); box-shadow: inset 0 0 0 1px var(--primary-500); }
    .cal-mnum { font-size: .78rem; font-weight: 600; }
    .cal-mday[data-today] .cal-mnum { color: var(--primary-600); }
    .cal-mtotal { font-size: 1.15rem; line-height: 1; font-variant-numeric: tabular-nums; }
    .cal-mbar { display: flex; gap: 1px; height: .35rem; border-radius: 999px; overflow: hidden; background: var(--gray-100); }
    .cal-mbar span { flex: var(--n) 1 0; background: var(--st); }
    .cal-mlist { display: flex; flex-direction: column; gap: .05rem; font-size: .68rem; color: var(--gray-500); }
    .cal-mlist span { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    /* Lists: the list display, and the calendar on a phone. */
    .cal-agenda { display: flex; flex-direction: column; gap: .7rem; }
    .cal-phone { display: none; }
    .cal-ag-day header { display: flex; align-items: baseline; gap: .5rem; padding: .6rem .85rem; border-bottom: 1px solid var(--gray-100); }
    .cal-ag-day header strong { font-size: .9rem; }
    .cal-ag-day header small { margin-inline-start: auto; color: var(--gray-500); font-size: .75rem; }
    .cal-ag-rows { display: flex; flex-direction: column; }
    .cal-ag-row { display: flex; align-items: center; gap: .75rem; padding: .55rem .85rem; text-align: start; border-inline-start: 3px solid var(--st); }
    .cal-ag-row + .cal-ag-row { border-top: 1px solid var(--gray-100); }
    .cal-ag-row:hover { background: var(--gray-50); }
    .cal-ag-time { flex-shrink: 0; width: 6.6rem; font-size: .8rem; font-weight: 600; font-variant-numeric: tabular-nums; direction: ltr; unicode-bidi: isolate; text-align: start; }
    .cal-ag-main { display: flex; flex-direction: column; gap: .1rem; flex: 1 1 auto; min-width: 0; }
    .cal-ag-main strong { font-size: .85rem; display: flex; flex-wrap: wrap; align-items: center; gap: .3rem; }
    .cal-ag-main small { font-size: .76rem; color: var(--gray-500); overflow-wrap: anywhere; }
    .cal-ag-row[data-status="cancelled"] .cal-ag-main strong { text-decoration: line-through; color: var(--gray-500); }

    /* By status: a table per teacher and per subject. */
    .cal-summary { display: grid; grid-template-columns: minmax(0, 1fr); gap: .8rem; }
    .cal-box { padding: .9rem 1rem; display: flex; flex-direction: column; gap: .5rem; }
    .cal-box h3 { font-size: .95rem; font-weight: 700; }
    .cal-table-wrap { overflow-x: auto; }
    .cal-table { width: 100%; border-collapse: collapse; font-size: .82rem; font-variant-numeric: tabular-nums; }
    .cal-table th, .cal-table td { padding: .45rem .55rem; text-align: center; white-space: nowrap; }
    .cal-table thead th { font-size: .72rem; font-weight: 600; color: var(--gray-500); border-bottom: 1px solid var(--gray-200); }
    .cal-table tbody tr + tr { border-top: 1px solid var(--gray-100); }
    .cal-table tbody tr:hover { background: var(--gray-50); }
    .cal-table th[scope="row"] { text-align: start; font-weight: 600; }
    .cal-table th[scope="row"] button:hover { color: var(--primary-600); text-decoration: underline; }
    .cal-table td[data-zero] { color: var(--gray-300); }
    .cal-table tfoot { border-top: 2px solid var(--gray-200); font-weight: 700; }

    /* A lesson's details (side panel). */
    .cal-dt { display: flex; flex-direction: column; gap: 1rem; }
    .cal-dt-tags { display: flex; flex-wrap: wrap; gap: .35rem; }
    .cal-dt dl { display: flex; flex-direction: column; gap: .7rem; }
    .cal-dt dl > div { display: grid; grid-template-columns: 6.5rem minmax(0, 1fr); gap: .6rem; font-size: .88rem; }
    .cal-dt dt { color: var(--gray-500); }
    .cal-dt dd small { font-size: .78rem; }
    .cal-dt-links { display: flex; flex-wrap: wrap; gap: .45rem; }

    .cal-empty { padding: 2.5rem 1rem; display: flex; flex-direction: column; align-items: center; gap: .35rem; text-align: center; }
    .cal-empty-icon { width: 2rem; height: 2rem; color: var(--gray-300); }
    .cal-empty span { color: var(--gray-500); font-size: .85rem; }

    @media (max-width: 767px) {
        .cal-wide { display: none; }
        .cal-phone { display: flex; }
        .cal-bar { padding: .5rem; }
        .cal-tools { width: 100%; }
        .cal-zone { margin-inline-start: auto; }
        .cal-zone select { max-width: 9rem; }
        .cal-long { display: none; }
        .cal-short { display: inline; }
        .cal-month { gap: .15rem; }
        .cal-mday { min-height: 3.6rem; padding: .25rem; align-items: center; text-align: center; }
        .cal-mlist { display: none; }
        .cal-mtotal { font-size: .95rem; }
        .cal-mbar { width: 100%; }
        .cal-filters { flex-direction: column; align-items: stretch; }
        .cal-ag-row { flex-wrap: wrap; gap: .25rem .6rem; }
        .cal-ag-time { width: auto; }
        .cal-ag-main { order: 3; flex-basis: 100%; }
        .cal-ag-row .cal-pill { margin-inline-start: auto; }
    }

    @media (prefers-reduced-motion: no-preference) {
        .cal-ev, .cal-tev { animation: cal-in .35s var(--lms-ease, ease-out) backwards; }
        .cal-mbar span { transform-origin: left center; animation: cal-grow .6s var(--lms-ease, ease-out) backwards; }
        [dir="rtl"] .cal-mbar span { transform-origin: right center; }
        @keyframes cal-in { from { opacity: 0; transform: translateY(3px); } }
        @keyframes cal-grow { from { transform: scaleX(0); } }
    }

    .dark .cal-card, .dark .cal-chip, .dark .cal-mday[data-in], .dark .cal-more, .dark .cal-tev-more, .dark .cal-zone select { background: var(--gray-900); border-color: var(--gray-800); }
    .dark .cal-icon-btn, .dark .cal-today { border-color: var(--gray-700); color: var(--gray-300); }
    .dark .cal-seg { background: var(--gray-800); }
    .dark .cal-seg button { color: var(--gray-300); }
    .dark .cal-seg button[aria-pressed="true"] { background: var(--gray-950); color: var(--primary-400); }
    .dark .cal-totals strong { color: white; }
    .dark .cal-mday:not([data-in]) { background: transparent; border-color: var(--gray-800); }
    .dark :is(.cal-week-head, .cal-day-head, .cal-ag-day header, .cal-legend, .cal-table thead th) { border-color: var(--gray-800); }
    .dark :is(.cal-wday, .cal-col, .cal-row-name, .cal-row + .cal-row, .cal-ag-row + .cal-ag-row, .cal-table tbody tr + tr, .cal-mday) { border-color: var(--gray-800); }
    .dark .cal { --cal-line: var(--gray-800); }
    .dark .cal-col[data-today] { background-color: rgb(255 255 255 / .02); }
    .dark :is(.cal-wday, .cal-ag-row, .cal-table tbody tr):hover { background: rgb(255 255 255 / .03); }
    .dark :where(.cal-ev, .cal-tev, .cal-pill, .cal-chip[aria-pressed="true"]) { --st-bg: color-mix(in oklab, var(--st) 24%, transparent); --st-fg: var(--gray-50); }
    .dark .cal-shade[data-kind="shift"] { background: color-mix(in oklab, var(--primary-500) 10%, transparent); }
    .dark .cal-shade[data-kind="off"], .dark .cal-shade[data-kind="holiday"] { background: repeating-linear-gradient(135deg, rgb(255 255 255 / .05) 0 6px, transparent 6px 12px); }
    .dark .cal-mbar, .dark .cal-tag, .dark .cal-ev-tags i, .dark .cal-ag-main i { background: var(--gray-800); color: var(--gray-200); }
    .dark .cal-legend span::before { border-color: var(--gray-700); }
    .dark .cal-legend span[data-kind="shift"]::before { background: color-mix(in oklab, var(--primary-500) 10%, transparent); }
    .dark .cal-legend span[data-kind="off"]::before, .dark .cal-legend span[data-kind="holiday"]::before { background: repeating-linear-gradient(135deg, rgb(255 255 255 / .12) 0 3px, transparent 3px 6px); }
    .dark .cal-banner { background: color-mix(in oklab, var(--warning-500) 15%, transparent); color: var(--warning-300); }
</style>
