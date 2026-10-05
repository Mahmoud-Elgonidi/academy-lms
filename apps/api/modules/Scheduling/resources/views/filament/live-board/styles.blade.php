{{-- Board styles. Colours come from the panel palette (--primary-600 etc.), which follows the academy's branding. --}}
<style>
    .lb { display: flex; flex-direction: column; gap: 1.25rem; }
    .lb-head { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 1rem; }
    .lb-title { margin: 0; font-size: 1.75rem; font-weight: 600; line-height: 1.25; }
    .lb-sub, .lb-muted { margin: 0; color: var(--gray-500); font-size: .875rem; }
    .lb-head-tools { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; }
    .lb-icon { width: 1.1rem; height: 1.1rem; }
    .lb-num { font-variant-numeric: tabular-nums; }

    .lb-seg { display: inline-flex; align-items: center; gap: 2px; padding: 3px; border-radius: var(--radius-lg); background: var(--gray-100); }
    .lb-seg-label { padding: 0 .5rem; font-size: .75rem; color: var(--gray-500); }
    .lb-seg button { padding: .3rem .75rem; border-radius: var(--radius-md); font-size: .8125rem; color: var(--gray-700); }
    .lb-seg button[aria-pressed="true"] { background: white; color: var(--primary-700); font-weight: 600; box-shadow: 0 1px 2px rgb(0 0 0 / .1); }
    .lb-tz { display: inline-flex; align-items: center; gap: .4rem; padding: .3rem .4rem .3rem .7rem; border: 1px solid var(--gray-200); border-radius: var(--radius-lg); background: white; font-size: .8125rem; }
    .lb-tz select { border: 0; background: transparent; font: inherit; font-weight: 600; padding: .1rem 1.6rem .1rem .3rem; border-radius: var(--radius-md); }

    .lb-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: .75rem; }
    .lb-kpi { display: flex; flex-direction: column; gap: .15rem; padding: 1rem 1.1rem; border-radius: var(--radius-xl); background: white; border: 1px solid var(--gray-200); }
    .lb-kpi-label { font-size: .8125rem; color: var(--gray-500); }
    .lb-kpi-value { font-size: 2rem; font-weight: 600; line-height: 1.15; }
    .lb-kpi[data-tone="primary"] .lb-kpi-value { color: var(--primary-600); }
    .lb-kpi[data-tone="danger"] .lb-kpi-value { color: var(--danger-600); }
    .lb-kpi[data-tone="warning"] .lb-kpi-value { color: var(--warning-700); }
    .lb-kpi-hint { font-size: .75rem; color: var(--gray-500); }

    .lb-h2 { margin: 0; font-size: 1.125rem; font-weight: 700; }
    .lb-h3 { margin: 0 0 .4rem; font-size: .8125rem; font-weight: 700; color: var(--gray-600); }
    .lb-card { background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl); padding: 1rem; display: flex; flex-direction: column; gap: .75rem; }
    .lb-card-head { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem 1rem; }
    .lb-chips { display: flex; flex-wrap: wrap; gap: .35rem; margin-inline-start: auto; }
    .lb-chips button { padding: .3rem .8rem; border-radius: 999px; border: 1px solid var(--gray-200); font-size: .8125rem; color: var(--gray-700); }
    .lb-chips button span { color: var(--gray-400); margin-inline-start: .2rem; }
    .lb-chips button[aria-pressed="true"] { border-color: var(--primary-600); color: var(--primary-700); background: var(--primary-50); font-weight: 600; }

    .lb-alerts { display: flex; flex-direction: column; gap: .6rem; }
    .lb-alert-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: .75rem; }
    .lb-alert { display: flex; flex-direction: column; gap: .35rem; padding: .9rem 1rem; border-radius: var(--radius-xl); border: 1px solid var(--warning-200); background: var(--warning-50); }
    .lb-alert[data-severity="3"] { border-color: var(--danger-200); background: var(--danger-50); }
    .lb-alert p { margin: 0; font-size: .8125rem; color: var(--gray-700); }
    .lb-alert-top { display: flex; justify-content: space-between; gap: .5rem; }
    .lb-alert-left { flex-shrink: 0; font-size: .75rem; font-weight: 700; color: var(--danger-700); }
    .lb-rule { color: var(--danger-700); font-weight: 600; font-size: .8125rem; }
    [data-severity="1"] .lb-rule { color: var(--warning-800); }
    .lb-handled { color: var(--primary-700); font-weight: 600; font-size: .8125rem; }
    .lb-actions { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem; }

    .lb-table { display: flex; flex-direction: column; overflow-x: auto; }
    .lb-tr { display: grid; grid-template-columns: 105px minmax(130px, 1fr) minmax(130px, 1fr) minmax(90px, .7fr) 110px 110px 215px; gap: .6rem; align-items: center; padding: .65rem .5rem; border-bottom: 1px solid var(--gray-100); min-width: 940px; }
    .lb-tr > span { display: flex; flex-direction: column; gap: .1rem; min-width: 0; font-size: .875rem; }
    .lb-tr small { font-size: .75rem; color: var(--gray-500); display: flex; align-items: center; gap: .3rem; }
    .lb-th { color: var(--gray-500); font-size: .75rem; border-bottom-color: var(--gray-200); }
    .lb-tr[data-issue="teacher_not_joined"], .lb-tr[data-issue="nobody_joined"], .lb-tr[data-issue="student_not_joined"] { background: var(--danger-50); }
    .lb-tr[data-issue="unfunded"], .lb-tr[data-issue="weak_connection"] { background: var(--warning-50); }
    .lb-late { color: var(--danger-700) !important; font-weight: 600; }
    .lb-dot { width: .45rem; height: .45rem; border-radius: 50%; flex-shrink: 0; background: var(--gray-300); display: inline-block; }
    .lb-dot[data-state="ok"] { background: var(--primary-600); }
    .lb-dot[data-state="warn"] { background: var(--warning-500); }
    .lb-dot[data-state="bad"] { background: var(--danger-600); }
    .lb-tag { align-self: flex-start; padding: 0 .45rem; border-radius: 999px; font-size: .6875rem; font-weight: 600; background: var(--info-50); color: var(--info-700); }
    .lb-tag[data-type="makeup"] { background: #EFE8F7; color: #5B3A8C; }
    .lb-progress-cell { gap: .3rem !important; }
    .lb-bar { display: block; height: .3rem; border-radius: 999px; background: var(--gray-100); overflow: hidden; }
    .lb-bar > span { display: block; height: 100%; background: var(--primary-600); border-radius: inherit; }
    .lb-pill { align-self: flex-start; padding: .1rem .55rem; border-radius: 999px; font-size: .75rem; font-weight: 600; background: var(--gray-100); color: var(--gray-700); white-space: nowrap; }
    .lb-pill[data-status="live"] { background: var(--primary-50); color: var(--primary-700); }
    .lb-pill[data-status="late"], .lb-pill[data-status="waiting"], .lb-pill[data-status="absent"] { background: var(--danger-50); color: var(--danger-700); }
    .lb-pill[data-status="unpaid"], .lb-pill[data-status="weak"], .lb-pill[data-status="review"] { background: var(--warning-50); color: var(--warning-800); }
    .lb-row-actions { flex-direction: row !important; align-items: center; justify-content: flex-end; gap: .25rem !important; }
    .lb-row-actions .lb-actions { flex-wrap: nowrap; }
    .lb-pill { border: 1px solid transparent; }
    .lb-pill[data-status="late"], .lb-pill[data-status="waiting"] { border-color: var(--danger-200); background: white; }
    .lb-pill[data-status="unpaid"] { border-color: var(--warning-200); background: white; }
    .lb-expand { display: inline-grid; place-items: center; width: 2rem; height: 2rem; border-radius: var(--radius-md); border: 1px solid var(--gray-200); color: var(--gray-600); }
    .lb-details { padding: 1rem; background: var(--gray-50); border-bottom: 1px solid var(--gray-100); min-width: 940px; }
    .lb-detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.25rem; }
    .lb-clocks { display: grid; grid-template-columns: repeat(3, 1fr); gap: .5rem; }
    .lb-clock { display: flex; flex-direction: column; padding: .55rem .7rem; border-radius: var(--radius-lg); background: var(--gray-50); border: 1px solid var(--gray-100); }
    .lb-clock small { font-size: .7rem; color: var(--gray-500); }
    .lb-clock strong { font-size: 1.15rem; }
    .lb-details .lb-clock { background: white; }
    .lb-log { margin: 0; padding: 0; list-style: none; display: flex; flex-direction: column; gap: .3rem; font-size: .8125rem; }
    .lb-log li { display: flex; gap: .6rem; }
    .lb-log li span { color: var(--gray-500); flex-shrink: 0; }
    .lb-dl { display: grid; grid-template-columns: auto 1fr; gap: .3rem .9rem; margin: 0; font-size: .8125rem; }
    .lb-dl dt { color: var(--gray-500); }
    .lb-dl dd { margin: 0; }
    .lb-pad { padding: 1rem .5rem; }
    .lb-link { color: var(--primary-700); font-weight: 600; font-size: .8125rem; }
    .lb-empty { display: flex; flex-direction: column; align-items: center; gap: .35rem; padding: 3rem 1rem; border-radius: var(--radius-xl); border: 1px dashed var(--gray-300); color: var(--gray-500); text-align: center; }
    .lb-empty-icon { width: 2rem; height: 2rem; }
    .lb-empty strong { color: var(--gray-800); }

    /* Focus layout */
    .lf { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1.25rem; align-items: start; }
    @media (min-width: 1200px) { .lf { grid-template-columns: minmax(0, 1fr) 400px; } .lf-panel { position: sticky; top: 5rem; } }
    .lf-list { display: flex; flex-direction: column; gap: 1.1rem; }
    .lf-group { display: flex; flex-direction: column; gap: .5rem; }
    .lf-group-title { display: flex; align-items: center; gap: .5rem; margin: 0; font-size: 1rem; font-weight: 700; }
    .lf-group-title small { font-weight: 400; font-size: .8125rem; color: var(--gray-500); }
    .lf-bullet { width: .6rem; height: .6rem; border-radius: 50%; background: var(--gray-400); }
    .lf-bullet[data-tone="danger"] { background: var(--danger-600); }
    .lf-bullet[data-tone="primary"] { background: var(--primary-600); }
    .lf-bullet[data-tone="hollow"] { background: transparent; border: 2px solid var(--gray-400); }
    .lf-card { display: flex; flex-direction: column; gap: .6rem; padding: .9rem 1rem; border-radius: var(--radius-xl); background: white; border: 1px solid var(--gray-200); }
    .lf-card[aria-current="true"] { border-color: var(--primary-600); box-shadow: 0 0 0 2px var(--primary-100); }
    .lf-ring { flex-shrink: 0; width: 3.6rem; height: 3.6rem; border-radius: 50%; display: grid; place-content: center; text-align: center; border: 3px solid var(--warning-500); color: var(--warning-700); background: var(--warning-50); }
    .lf-ring[data-severity="3"] { border-color: var(--danger-500); color: var(--danger-700); background: var(--danger-50); }
    .lf-ring strong { font-size: 1.15rem; line-height: 1; }
    .lf-ring small { font-size: .65rem; }
    .lf-text { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: .15rem; font-size: .8125rem; color: var(--gray-700); }
    .lf-text strong { font-size: .95rem; color: var(--gray-950); }
    .lf-card-main { display: flex; align-items: flex-start; gap: 1rem; }
    .lf-card-actions { display: flex; align-items: center; gap: .35rem; padding-inline-start: 4.6rem; }
    .lf-chevron { transform: none; }
    [dir="ltr"] .lf-chevron { transform: scaleX(-1); }
    .lf-row { display: grid; grid-template-columns: 110px minmax(0, 1fr) auto; align-items: center; gap: .3rem .8rem; padding: .65rem .9rem; border-radius: var(--radius-lg); background: white; border: 1px solid var(--gray-200); text-align: start; font-size: .875rem; }
    .lf-row[aria-pressed="true"] { border-color: var(--primary-600); }
    .lf-row .lb-bar { grid-column: 2 / 3; }
    .lf-who small { color: var(--gray-500); }
    .lf-progress { font-size: .75rem; color: var(--gray-500); }
    .lf-checks { display: flex; flex-wrap: wrap; gap: .3rem; grid-column: 2 / 4; }
    .lb-check { padding: .05rem .5rem; border-radius: 999px; font-size: .7rem; font-weight: 600; background: var(--primary-50); color: var(--primary-700); }
    .lb-check[data-ok="false"] { background: var(--warning-50); color: var(--warning-800); }
    .lf-done-head { display: flex; align-items: center; gap: .75rem; }
    .lf-panel { display: flex; flex-direction: column; gap: .8rem; padding: 1.1rem; border-radius: var(--radius-xl); background: white; border: 1px solid var(--gray-200); }
    .lf-panel-title { margin: 0; font-size: 1.15rem; font-weight: 700; }
    .lf-panel-actions { display: flex; flex-direction: column; gap: .5rem; }
    .lf-panel-action { display: flex; flex-direction: column; align-items: flex-start; gap: .2rem; padding: .55rem .7rem; border-radius: var(--radius-lg); border: 1px solid var(--gray-200); }
    .lf-panel-action[data-danger="true"] { border-color: var(--danger-200); }
    .lf-panel-action small { font-size: .75rem; color: var(--gray-500); }

    .dark .lb-kpi, .dark .lb-card, .dark .lf-card, .dark .lf-row, .dark .lf-panel, .dark .lb-tz, .dark .lb-seg button[aria-pressed="true"] { background: var(--gray-900); border-color: var(--gray-800); }
    .dark .lb-seg { background: var(--gray-800); }
    .dark .lb-details, .dark .lb-clock { background: var(--gray-950); border-color: var(--gray-800); }
    .dark .lb-tr { border-bottom-color: var(--gray-800); }
    .dark .lb-alert { background: color-mix(in oklab, var(--warning-500) 12%, transparent); border-color: var(--warning-800); }
    .dark .lb-alert[data-severity="3"], .dark .lb-tr[data-issue="teacher_not_joined"], .dark .lb-tr[data-issue="nobody_joined"], .dark .lb-tr[data-issue="student_not_joined"] { background: color-mix(in oklab, var(--danger-500) 12%, transparent); border-color: var(--danger-800); }
    .dark .lf-text strong, .dark .lb-empty strong { color: white; }
    .dark .lf-text, .dark .lb-alert p { color: var(--gray-300); }
</style>
