{{-- Styles for the shifts screen and its slide-overs (Filament's colour variables only). --}}
<style>
    .sp { display: flex; flex-direction: column; gap: 1.5rem; }
    .sp-group { display: flex; flex-direction: column; gap: .6rem; }
    .sp-group-head h2 { margin: 0; font-size: 1rem; font-weight: 700; display: flex; align-items: center; gap: .5rem; }
    .sp-group-head p { margin: .1rem 0 0; color: var(--gray-500); font-size: .8125rem; }
    .sp-count { min-width: 1.5rem; padding: 0 .45rem; border-radius: 999px; background: var(--gray-100); color: var(--gray-700); font-size: .75rem; font-weight: 600; text-align: center; }
    .sp-group[data-group="review"] .sp-count { background: var(--warning-100); color: var(--warning-800); }
    .sp-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: .5rem; }
    .sp-item { width: 100%; display: grid; grid-template-columns: minmax(150px, 1.2fr) minmax(130px, .9fr) minmax(200px, 1.6fr) minmax(120px, .8fr) auto; align-items: center; gap: .9rem;
        padding: .85rem 1rem; border-radius: var(--radius-xl); background: white; border: 1px solid var(--gray-200); text-align: start; color: inherit; cursor: pointer; }
    .sp-item:hover, .sp-item:focus-visible { border-color: var(--primary-400); outline: none; }
    .sp-item[data-overdue="true"] { border-inline-start: 3px solid var(--danger-500); }
    .sp-item > span { display: flex; flex-direction: column; gap: .1rem; font-size: .875rem; min-width: 0; }
    .sp-item small { color: var(--gray-500); font-size: .75rem; }
    .sp-hours { font-weight: 600; font-variant-numeric: tabular-nums; }
    .sp-change small { display: flex; flex-wrap: wrap; gap: .25rem .6rem; }
    .sp-days[data-kind="added"] { color: var(--primary-700); }
    .sp-days[data-kind="removed"] { color: var(--danger-700); }
    .sp-days[data-kind="changed"] { color: var(--warning-700); }
    .sp-conf-n { font-weight: 600; color: var(--primary-700); }
    .sp-conf-n[data-open="true"] { color: var(--danger-700); }
    .sp-ok, .sp-good { color: var(--primary-700); }
    .sp-bad, .sp-late { color: var(--danger-700); font-weight: 600; }
    .sp-go { color: var(--primary-700); font-weight: 600; font-size: .8125rem; }
    .sp-muted { color: var(--gray-500); font-size: .8125rem; margin: 0; }
    .sp-pill { justify-self: end; white-space: nowrap; align-self: center; padding: .15rem .6rem; border-radius: 999px; font-size: .75rem; font-weight: 600; background: var(--gray-100); color: var(--gray-700); }
    .sp-pill[data-status="submitted"] { background: var(--warning-50); color: var(--warning-800); }
    .sp-pill[data-status="countered"], .sp-pill[data-status="changes_requested"] { background: var(--info-50); color: var(--info-700); }
    .sp-pill[data-status="approved"] { background: var(--primary-50); color: var(--primary-700); }
    .sp-pill[data-status="rejected"] { background: var(--danger-50); color: var(--danger-700); }
    .sp-empty { padding: 1rem; color: var(--gray-500); font-size: .875rem; border: 1px dashed var(--gray-300); border-radius: var(--radius-xl); }
    @media (max-width: 900px) { .sp-item { grid-template-columns: 1fr 1fr; } .sp-pill { justify-self: start; } }

    .spo { display: flex; flex-direction: column; gap: 1.25rem; }
    .spo-facts { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem 1.25rem; font-size: .875rem; }
    .spo-facts small { color: var(--gray-500); }
    .spo-note { font-size: .8125rem; padding: .6rem .8rem; border-radius: var(--radius-lg); background: var(--primary-50); color: var(--primary-800); }
    .spo-note[data-tone="info"] { background: var(--info-50); color: var(--info-800); }
    .spo-step { display: flex; flex-direction: column; gap: .6rem; }
    .spo-step h3 { margin: 0; font-size: .95rem; font-weight: 700; display: flex; align-items: center; flex-wrap: wrap; gap: .5rem; }
    .spo-step h3 small { font-weight: 400; font-size: .78rem; color: var(--gray-500); }
    .spo-n { display: inline-grid; place-items: center; width: 1.4rem; height: 1.4rem; border-radius: 50%; background: var(--primary-50); color: var(--primary-700); font-size: .75rem; }

    .sw { display: flex; flex-direction: column; border: 1px solid var(--gray-200); border-radius: var(--radius-lg); overflow: hidden; }
    .sw-head, .sw-row { display: grid; grid-template-columns: 6rem minmax(0, 1fr) minmax(0, 1fr); gap: .75rem; align-items: start; padding: .5rem .75rem; }
    .sw[data-single="true"] .sw-head, .sw[data-single="true"] .sw-row { grid-template-columns: 6rem minmax(0, 1fr); }
    .sw-head { background: var(--gray-50); font-size: .75rem; font-weight: 600; color: var(--gray-600); }
    .sw-row + .sw-row { border-top: 1px solid var(--gray-100); }
    .sw-row[data-changed="true"] { background: color-mix(in oklab, var(--warning-50) 55%, transparent); }
    .sw-day { font-size: .8125rem; font-weight: 600; padding-top: .15rem; }
    .sw-cell { display: flex; flex-wrap: wrap; gap: .3rem; }
    .sw-block { padding: .15rem .5rem; border-radius: var(--radius-md); font-size: .78rem; font-variant-numeric: tabular-nums; background: var(--gray-100); color: var(--gray-700); border: 1px solid transparent; }
    .sw-block[data-kind="added"] { background: var(--primary-50); color: var(--primary-800); border-color: var(--primary-300); }
    .sw-block[data-kind="removed"] { background: var(--danger-50); color: var(--danger-700); border-color: var(--danger-200); text-decoration: line-through; }
    .sw-block[data-kind="changed"] { background: var(--warning-50); color: var(--warning-800); border-color: var(--warning-300); }
    .sw-off { font-size: .75rem; color: var(--gray-400); padding-top: .15rem; }
    .sw-legend { display: flex; flex-wrap: wrap; gap: .75rem; padding: .5rem .75rem; font-size: .72rem; color: var(--gray-600); border-top: 1px solid var(--gray-100); }
    .sw-legend i { display: inline-block; width: .8rem; height: .55rem; border-radius: 3px; margin-inline-end: .3rem; vertical-align: middle; background: var(--gray-200); }
    .sw-legend i[data-kind="added"] { background: var(--primary-300); }
    .sw-legend i[data-kind="removed"] { background: var(--danger-300); }
    .sw-legend i[data-kind="changed"] { background: var(--warning-300); }

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

    .dark .sp-item, .dark .spo-choices button { background: var(--gray-900); border-color: var(--gray-800); }
    .dark .sw, .dark .sw-row + .sw-row, .dark .sw-legend { border-color: var(--gray-800); }
    .dark .sw-head { background: var(--gray-800); color: var(--gray-300); }
    .dark .sw-block { background: var(--gray-800); color: var(--gray-200); }
    .dark .sw-block[data-kind="added"] { background: color-mix(in oklab, var(--primary-500) 18%, transparent); color: var(--primary-300); border-color: var(--primary-700); }
    .dark .sw-block[data-kind="removed"] { background: color-mix(in oklab, var(--danger-500) 15%, transparent); color: var(--danger-300); border-color: var(--danger-800); }
    .dark .sw-block[data-kind="changed"] { background: color-mix(in oklab, var(--warning-500) 15%, transparent); color: var(--warning-300); border-color: var(--warning-800); }
    .dark .sw-row[data-changed="true"] { background: color-mix(in oklab, var(--warning-500) 6%, transparent); }
    .dark .spo-msg { background: var(--gray-800); }
    .dark .spo-msg[data-role="admin"] { background: color-mix(in oklab, var(--primary-500) 18%, transparent); }
    .dark .spo-msg header strong { color: var(--gray-100); }
    .dark .spo-conflict[data-resolved="false"] { background: color-mix(in oklab, var(--danger-500) 10%, transparent); border-color: var(--danger-800); }
    .dark .spo-composer { border-color: var(--gray-700); }
    .dark .sp-count { background: var(--gray-800); color: var(--gray-300); }
</style>
