{{-- Styles for the shifts screen and its slide-overs (Filament's colour variables only). --}}
<style>
    /* The header's chip: hours are on each teacher's clock. */
    .sp-zone-chip { display: inline-flex; align-items: center; gap: .35rem; padding: .2rem .7rem; border-radius: 999px; background: var(--gray-100); color: var(--gray-600); font-size: .78rem; font-weight: 500; cursor: help; }
    .sp-zone-chip svg { width: 1rem; height: 1rem; }

    /* The list: a hint line, then cards in a grid. */
    .sp { display: flex; flex-direction: column; gap: .85rem; }
    .sp-hint { margin: 0; color: var(--gray-500); font-size: .8125rem; }
    .sp-grid { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: minmax(0, 1fr); gap: .85rem; }
    @media (min-width: 1024px) { .sp-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .sp-grid > li { display: flex; }
    @media (max-width: 640px) {
        .sp-tabs { flex-wrap: wrap; justify-content: center; }
        .sp-grid .sp-card { padding: .9rem; }
        .sp-strip .sp-day { font-size: .62rem; padding: .3rem 0; }
        .sp-card .sp-who small { white-space: normal; }
    }

    .sp-card { width: 100%; display: flex; flex-direction: column; gap: .9rem; padding: 1rem 1.1rem; border-radius: var(--radius-xl); background: white; border: 1px solid var(--gray-200);
        box-shadow: 0 1px 2px rgb(0 0 0 / .04); text-align: start; color: inherit; cursor: pointer; transition: border-color .15s, box-shadow .15s; }
    .sp-card:hover, .sp-card:focus-visible { border-color: var(--primary-400); box-shadow: 0 4px 14px rgb(0 0 0 / .06); outline: none; }
    .sp-card:focus-visible { box-shadow: 0 0 0 3px color-mix(in oklab, var(--primary-500) 30%, transparent); }
    .sp-card[data-overdue="true"] { border-inline-start: 4px solid var(--danger-500); }

    .sp-card-head { display: flex; align-items: center; gap: .75rem; min-width: 0; }
    .sp-avatar { flex: none; width: 2.5rem; height: 2.5rem; border-radius: 50%; display: grid; place-items: center; background: var(--primary-50); color: var(--primary-700); font-weight: 700; font-size: 1rem; }
    .sp-who { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: .1rem; }
    .sp-who strong { font-size: .95rem; font-weight: 700; color: var(--gray-950); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .sp-who small { color: var(--gray-500); font-size: .75rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .sp-tags { flex: none; display: flex; flex-wrap: wrap; justify-content: flex-end; gap: .3rem; max-width: 50%; }

    .sp-hours { display: flex; align-items: baseline; flex-wrap: wrap; gap: .25rem .45rem; font-variant-numeric: tabular-nums; line-height: 1.1; }
    .sp-h-before { font-size: 1.2rem; font-weight: 600; color: var(--gray-400); }
    .sp-h-arrow { font-size: 1rem; color: var(--gray-400); }
    .sp-h-after { font-size: 1.75rem; font-weight: 800; color: var(--gray-950); }
    .sp-h-none { font-size: 1rem; font-weight: 600; color: var(--gray-500); }
    .sp-hours small { color: var(--gray-500); font-size: .78rem; }
    .sp-delta { align-self: center; padding: .1rem .5rem; border-radius: 999px; font-size: .75rem; font-weight: 700; background: var(--gray-100); color: var(--gray-600); }
    .sp-delta[data-sign="up"] { background: var(--success-50); color: var(--success-700); }
    .sp-delta[data-sign="down"] { background: var(--danger-50); color: var(--danger-700); }

    /* Seven days: added, removed, changed, the same, working or off. */
    .sp-strip { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: .25rem; }
    .sp-day { display: block; text-align: center; padding: .35rem .1rem; border-radius: var(--radius-md); font-size: .68rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        background: transparent; color: var(--gray-400); border: 1px dashed var(--gray-200); }
    .sp-day[data-kind="same"], .sp-day[data-kind="on"] { background: var(--gray-100); color: var(--gray-700); border: 1px solid var(--gray-200); }
    .sp-day[data-kind="on"] { background: var(--primary-50); color: var(--primary-700); border-color: var(--primary-200); }
    .sp-day[data-kind="added"] { background: var(--success-50); color: var(--success-700); border: 1px solid var(--success-300); }
    .sp-day[data-kind="removed"] { background: var(--danger-50); color: var(--danger-700); border: 1px solid var(--danger-200); text-decoration: line-through; }
    .sp-day[data-kind="changed"] { background: var(--warning-50); color: var(--warning-800); border: 1px solid var(--warning-300); }

    .sp-card-foot { margin-top: auto; display: flex; flex-wrap: wrap; align-items: center; gap: .4rem 1rem; padding-top: .75rem; border-top: 1px solid var(--gray-100); font-size: .8rem; color: var(--gray-600); }
    .sp-card-foot > span { display: inline-flex; align-items: center; gap: .3rem; }
    .sp-ico { width: 1rem; height: 1rem; flex: none; }
    .sp-conf { font-weight: 600; color: var(--success-700); }
    .sp-conf[data-open="true"] { color: var(--danger-700); }
    .sp-from[data-kind="upcoming"] { color: var(--primary-700); font-weight: 600; }
    .sp-cta { margin-inline-start: auto; color: var(--primary-600); font-weight: 700; }
    .sp-card:hover .sp-cta { color: var(--primary-700); }

    .sp-ok, .sp-good { color: var(--success-700); }
    .sp-bad { color: var(--danger-700); font-weight: 600; }
    .sp-late { white-space: nowrap; padding: .15rem .55rem; border-radius: 999px; font-size: .72rem; font-weight: 700; background: var(--danger-50); color: var(--danger-700); }
    .sp-muted { color: var(--gray-500); font-size: .8125rem; margin: 0; }
    .sp-pill { white-space: nowrap; padding: .15rem .6rem; border-radius: 999px; font-size: .72rem; font-weight: 600; background: var(--gray-100); color: var(--gray-700); }
    .sp-pill[data-status="submitted"] { background: var(--warning-50); color: var(--warning-800); }
    .sp-pill[data-status="countered"], .sp-pill[data-status="changes_requested"] { background: var(--info-50); color: var(--info-700); }
    .sp-pill[data-status="approved"] { background: var(--success-50); color: var(--success-700); }
    .sp-pill[data-status="rejected"] { background: var(--danger-50); color: var(--danger-700); }

    .sp-empty { display: flex; flex-direction: column; align-items: center; gap: .6rem; padding: 2.5rem 1rem; text-align: center; color: var(--gray-500); font-size: .875rem; border: 1px dashed var(--gray-300); border-radius: var(--radius-xl); }
    .sp-empty p { margin: 0; }
    .sp-empty-icon { width: 2.75rem; height: 2.75rem; padding: .6rem; border-radius: 50%; background: var(--gray-100); color: var(--gray-400); }

    /* The slide-over: a summary, then tabs. */
    .spo { display: flex; flex-direction: column; gap: 1rem; }
    .spo-hero { display: flex; flex-direction: column; gap: .85rem; padding: 1rem 1.1rem; border-radius: var(--radius-xl); background: var(--gray-50); border: 1px solid var(--gray-200); }
    .spo-hero-top { display: flex; flex-wrap: wrap; align-items: center; gap: .4rem .9rem; font-size: .875rem; }
    .spo-from { font-weight: 600; color: var(--gray-700); }
    .spo-hero-hours { display: grid; grid-template-columns: minmax(0, auto) minmax(0, 1fr); align-items: center; gap: .75rem 1.5rem; }
    @media (max-width: 640px) { .spo-hero-hours { grid-template-columns: minmax(0, 1fr); } }
    .spo-big { display: flex; align-items: baseline; flex-wrap: wrap; gap: .25rem .45rem; font-variant-numeric: tabular-nums; line-height: 1.1; }
    .spo-big small { color: var(--gray-500); font-size: .78rem; }
    .spo-bars { display: flex; flex-direction: column; gap: .4rem; }
    .spo-bar { display: grid; grid-template-columns: 4.5rem minmax(0, 1fr) 2.5rem; align-items: center; gap: .5rem; font-size: .75rem; color: var(--gray-500); }
    .spo-bar-value { font-weight: 700; color: var(--gray-700); font-variant-numeric: tabular-nums; text-align: end; }
    .spo-bar-track { display: block; height: .5rem; border-radius: 999px; background: var(--gray-200); overflow: hidden; }
    .spo-bar-track > span { display: block; height: 100%; border-radius: inherit; background: var(--gray-400); }
    .spo-bar[data-side="after"] .spo-bar-track > span { background: var(--primary-500); }
    .spo-notice { margin: 0; font-size: .8125rem; color: var(--gray-600); }
    .spo-note { font-size: .8125rem; padding: .6rem .8rem; border-radius: var(--radius-lg); background: var(--success-50); color: var(--success-800); }
    .spo-note[data-tone="info"] { background: var(--info-50); color: var(--info-800); }
    .spo-note[data-tone="danger"] { background: var(--danger-50); color: var(--danger-800); }
    .spo-pane { display: flex; flex-direction: column; gap: .6rem; }
    @media (prefers-reduced-motion: no-preference) {
        .spo-pane { animation: spo-pane-in .2s ease-out backwards; }
        @keyframes spo-pane-in { from { opacity: 0; transform: translateY(4px); } }
    }

    .sw { display: flex; flex-direction: column; border: 1px solid var(--gray-200); border-radius: var(--radius-lg); overflow: hidden; }
    .sw-head, .sw-row { display: grid; grid-template-columns: 6rem minmax(0, 1fr) minmax(0, 1fr); gap: .75rem; align-items: start; padding: .5rem .75rem; }
    .sw[data-single="true"] .sw-head, .sw[data-single="true"] .sw-row { grid-template-columns: 6rem minmax(0, 1fr); }
    .sw-head { background: var(--gray-50); font-size: .75rem; font-weight: 600; color: var(--gray-600); }
    .sw-row + .sw-row { border-top: 1px solid var(--gray-100); }
    .sw-row[data-changed="true"] { background: color-mix(in oklab, var(--warning-50) 55%, transparent); }
    .sw-day { font-size: .8125rem; font-weight: 600; padding-top: .15rem; }
    .sw-cell { display: flex; flex-wrap: wrap; gap: .3rem; }
    .sw-block { white-space: nowrap; padding: .15rem .5rem; border-radius: var(--radius-md); font-size: .78rem; font-variant-numeric: tabular-nums; background: var(--gray-100); color: var(--gray-700); border: 1px solid transparent; }
    .sw-block[data-kind="added"] { background: var(--success-50); color: var(--success-800); border-color: var(--success-300); }
    .sw-block[data-kind="removed"] { background: var(--danger-50); color: var(--danger-700); border-color: var(--danger-200); text-decoration: line-through; }
    .sw-block[data-kind="changed"] { background: var(--warning-50); color: var(--warning-800); border-color: var(--warning-300); }
    .sw-off { font-size: .75rem; color: var(--gray-400); padding-top: .15rem; }
    .sw-legend { display: flex; flex-wrap: wrap; gap: .75rem; padding: .5rem .75rem; font-size: .72rem; color: var(--gray-600); border-top: 1px solid var(--gray-100); }
    .sw-legend i { display: inline-block; width: .8rem; height: .55rem; border-radius: 3px; margin-inline-end: .3rem; vertical-align: middle; background: var(--gray-200); }
    .sw-legend i[data-kind="added"] { background: var(--success-300); }
    .sw-legend i[data-kind="removed"] { background: var(--danger-300); }
    .sw-legend i[data-kind="changed"] { background: var(--warning-300); }
    @media (max-width: 640px) {
        .sw-head, .sw-row { grid-template-columns: 3.75rem minmax(0, 1fr) minmax(0, 1fr); gap: .4rem; padding: .5rem; }
        .sw-block { font-size: .7rem; padding: .15rem .35rem; }
    }

    .spo-conflicts { display: flex; flex-direction: column; gap: .5rem; }
    .spo-conflict { border: 1px solid var(--gray-200); border-radius: var(--radius-lg); padding: .75rem; display: flex; flex-direction: column; gap: .45rem; }
    .spo-conflict[data-resolved="false"] { border-color: var(--danger-200); background: var(--danger-50); }
    .spo-conflict-head { display: flex; justify-content: space-between; gap: .5rem; }
    .spo-conflict small { color: var(--gray-600); font-size: .78rem; }
    .spo-state { font-size: .72rem; font-weight: 700; white-space: nowrap; }
    .spo-choices { display: flex; flex-wrap: wrap; gap: .35rem; }
    .spo-choices button { padding: .3rem .7rem; border-radius: var(--radius-md); border: 1px solid var(--gray-300); background: white; font-size: .78rem; }
    .spo-choices button[aria-pressed="true"] { background: var(--primary-600); border-color: var(--primary-600); color: white; font-weight: 600; }
    .spo-choices button[disabled] { opacity: .5; cursor: not-allowed; }
    .spo-move { padding: .3rem .7rem; border-radius: var(--radius-md); border: 1px solid var(--primary-300); color: var(--primary-700); font-size: .78rem; font-weight: 600; }

    .spo-thread { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: .6rem; }
    .spo-event { display: flex; align-items: center; gap: .6rem; color: var(--gray-500); font-size: .72rem; }
    .spo-event::before, .spo-event::after { content: ""; flex: 1; height: 1px; background: var(--gray-200); }
    .spo-event span { text-align: center; max-width: 80%; }
    .spo-msg { max-width: 85%; align-self: flex-start; padding: .55rem .8rem; border-radius: var(--radius-xl); border-start-start-radius: .3rem; background: var(--gray-100); font-size: .875rem; }
    .spo-msg[data-role="admin"] { align-self: flex-end; background: var(--primary-50); border-start-start-radius: var(--radius-xl); border-start-end-radius: .3rem; }
    .spo-msg header { display: flex; flex-wrap: wrap; align-items: baseline; gap: .35rem; font-size: .72rem; color: var(--gray-500); margin-bottom: .15rem; }
    .spo-msg header strong { color: var(--gray-800); font-size: .78rem; }
    .spo-role { padding: 0 .35rem; border-radius: 999px; background: color-mix(in oklab, var(--gray-500) 12%, transparent); }
    .spo-msg p { margin: 0; white-space: pre-line; }
    .spo-composer { display: flex; flex-direction: column; gap: .4rem; border: 1px solid var(--gray-300); border-radius: var(--radius-xl); padding: .35rem .6rem; }
    .spo-composer:focus-within { border-color: var(--primary-500); }
    .spo-composer textarea { width: 100%; border: 0; padding: .3rem 0; font: inherit; font-size: .875rem; background: transparent; resize: none; min-height: 1.8rem; outline: none; box-shadow: none; }
    .spo-composer:focus-within textarea, .spo-composer:has(textarea:not(:placeholder-shown)) textarea { min-height: 5rem; }
    .spo-composer-foot { display: none; justify-content: space-between; align-items: center; gap: .5rem; padding-bottom: .2rem; }
    .spo-composer:focus-within .spo-composer-foot, .spo-composer:has(textarea:not(:placeholder-shown)) .spo-composer-foot, .spo-composer:has(.spo-error) .spo-composer-foot { display: flex; }
    .spo-error { margin: 0; font-size: .78rem; }

    /* Dark. */
    .dark .sp-zone-chip { background: var(--gray-800); color: var(--gray-300); }
    .dark .sp-card { background: var(--gray-900); border-color: var(--gray-800); box-shadow: none; }
    .dark .sp-card:hover, .dark .sp-card:focus-visible { border-color: var(--primary-500); }
    .dark .sp-card-foot { border-color: var(--gray-800); color: var(--gray-400); }
    .dark .sp-avatar { background: color-mix(in oklab, var(--primary-500) 20%, transparent); color: var(--primary-300); }
    .dark .sp-who strong, .dark .sp-h-after { color: white; }
    .dark .sp-h-before, .dark .sp-h-arrow { color: var(--gray-500); }
    .dark .sp-day { border-color: var(--gray-800); color: var(--gray-600); }
    .dark .sp-day[data-kind="same"] { background: var(--gray-800); color: var(--gray-300); border-color: var(--gray-700); }
    .dark .sp-day[data-kind="on"] { background: color-mix(in oklab, var(--primary-500) 18%, transparent); color: var(--primary-300); border-color: var(--primary-800); }
    .dark .sp-day[data-kind="added"], .dark .sp-delta[data-sign="up"] { background: color-mix(in oklab, var(--success-500) 18%, transparent); color: var(--success-300); border-color: var(--success-800); }
    .dark .sp-day[data-kind="removed"], .dark .sp-delta[data-sign="down"], .dark .sp-late { background: color-mix(in oklab, var(--danger-500) 18%, transparent); color: var(--danger-300); border-color: var(--danger-800); }
    .dark .sp-day[data-kind="changed"] { background: color-mix(in oklab, var(--warning-500) 16%, transparent); color: var(--warning-300); border-color: var(--warning-800); }
    .dark .sp-delta { background: var(--gray-800); color: var(--gray-300); }
    .dark .sp-conf { color: var(--success-400); }
    .dark .sp-conf[data-open="true"], .dark .sp-bad { color: var(--danger-400); }
    .dark .sp-ok, .dark .sp-good { color: var(--success-400); }
    .dark .sp-cta, .dark .sp-from[data-kind="upcoming"] { color: var(--primary-400); }
    .dark .sp-pill { background: var(--gray-800); color: var(--gray-300); }
    .dark .sp-pill[data-status="submitted"] { background: color-mix(in oklab, var(--warning-500) 18%, transparent); color: var(--warning-300); }
    .dark .sp-pill[data-status="countered"], .dark .sp-pill[data-status="changes_requested"] { background: color-mix(in oklab, var(--info-500) 18%, transparent); color: var(--info-300); }
    .dark .sp-pill[data-status="approved"] { background: color-mix(in oklab, var(--success-500) 18%, transparent); color: var(--success-300); }
    .dark .sp-pill[data-status="rejected"] { background: color-mix(in oklab, var(--danger-500) 18%, transparent); color: var(--danger-300); }
    .dark .sp-empty { border-color: var(--gray-700); }
    .dark .sp-empty-icon { background: var(--gray-800); color: var(--gray-500); }

    .dark .spo-hero { background: color-mix(in oklab, var(--gray-800) 50%, transparent); border-color: var(--gray-800); }
    .dark .spo-from, .dark .spo-bar-value { color: var(--gray-200); }
    .dark .spo-bar-track { background: var(--gray-700); }
    .dark .spo-bar-track > span { background: var(--gray-500); }
    .dark .spo-notice { color: var(--gray-400); }
    .dark .spo-note { background: color-mix(in oklab, var(--success-500) 14%, transparent); color: var(--success-300); }
    .dark .spo-note[data-tone="info"] { background: color-mix(in oklab, var(--info-500) 14%, transparent); color: var(--info-300); }
    .dark .spo-note[data-tone="danger"] { background: color-mix(in oklab, var(--danger-500) 14%, transparent); color: var(--danger-300); }
    .dark .spo-choices button { background: var(--gray-900); border-color: var(--gray-700); }
    .dark .sw, .dark .sw-row + .sw-row, .dark .sw-legend { border-color: var(--gray-800); }
    .dark .sw-head { background: var(--gray-800); color: var(--gray-300); }
    .dark .sw-block { background: var(--gray-800); color: var(--gray-200); }
    .dark .sw-block[data-kind="added"] { background: color-mix(in oklab, var(--success-500) 18%, transparent); color: var(--success-300); border-color: var(--success-700); }
    .dark .sw-block[data-kind="removed"] { background: color-mix(in oklab, var(--danger-500) 15%, transparent); color: var(--danger-300); border-color: var(--danger-800); }
    .dark .sw-block[data-kind="changed"] { background: color-mix(in oklab, var(--warning-500) 15%, transparent); color: var(--warning-300); border-color: var(--warning-800); }
    .dark .sw-row[data-changed="true"] { background: color-mix(in oklab, var(--warning-500) 6%, transparent); }
    .dark .spo-msg { background: var(--gray-800); }
    .dark .spo-msg[data-role="admin"] { background: color-mix(in oklab, var(--primary-500) 18%, transparent); }
    .dark .spo-msg header strong { color: var(--gray-100); }
    .dark .spo-conflict { border-color: var(--gray-800); }
    .dark .spo-conflict[data-resolved="false"] { background: color-mix(in oklab, var(--danger-500) 10%, transparent); border-color: var(--danger-800); }
    .dark .spo-composer { border-color: var(--gray-700); }
</style>
