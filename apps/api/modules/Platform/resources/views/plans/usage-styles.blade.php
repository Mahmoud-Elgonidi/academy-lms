{{-- Settings › Usage and balance (Filament colour variables: the academy's branding). --}}
<style>
    .mu-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 20rem), 1fr)); gap: 1rem; }
    .mu-card { padding: 1rem 1.05rem; display: flex; flex-direction: column; gap: .75rem; }
    .mu-card[data-state="full"] { border-color: var(--danger-200); }
    .mu-card[data-state="near"] { border-color: var(--warning-200); }
    .mu-card header { display: flex; justify-content: space-between; align-items: center; gap: .5rem; }
    .mu-card h3 { font-weight: 700; font-size: 1rem; }
    .mu-headline { display: flex; flex-direction: column; gap: .1rem; }
    .mu-headline span { font-size: .8rem; color: var(--gray-500); }
    .mu-headline strong { font-size: 1.6rem; font-weight: 700; color: var(--gray-950); font-variant-numeric: tabular-nums; }
    .mu-part { display: flex; flex-direction: column; gap: .35rem; padding-top: .65rem; border-top: 1px solid var(--gray-100); }
    .mu-part small, .mu-note { font-size: .75rem; color: var(--gray-500); }
    .mu-row { display: flex; justify-content: space-between; gap: .5rem; font-size: .86rem; }
    .mu-row span:last-child { font-weight: 600; font-variant-numeric: tabular-nums; }
    .mu-card[data-state="near"] .pp-bar span { background: var(--warning-500); }
    .mu-card[data-state="full"] .pp-bar span { background: var(--danger-500); }
    .mu-lot { display: grid; grid-template-columns: 1fr auto; gap: .15rem .5rem; font-size: .82rem; }
    .mu-lot small { text-align: end; }
    .mu-lot-bar { grid-column: 1 / -1; height: .3rem; }
    .mu-debt { margin: 0; font-size: .82rem; color: var(--danger-700); font-weight: 600; }
    .mu-runout { margin: 0; font-size: .86rem; color: var(--gray-700); }
    .mu-card[data-state="full"] .mu-runout { color: var(--danger-700); font-weight: 600; }
    .mu-actions { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: auto; padding-top: .65rem; border-top: 1px solid var(--gray-100); }
    .mu-button { display: inline-flex; align-items: center; padding: .35rem .75rem; border-radius: var(--radius-lg, .5rem); font-size: .8rem; font-weight: 600; border: 1px solid var(--gray-300); color: var(--gray-700); }
    .mu-button:hover { background: var(--gray-50); }
    .mu-button[data-primary] { background: var(--primary-600); border-color: var(--primary-600); color: white; }
    .mu-button[data-primary]:hover { background: var(--primary-500); }
    .mu-legend { display: flex; gap: 1rem; list-style: none; margin: 0; padding: 0; font-size: .78rem; color: var(--gray-600); }
    .mu-legend li { display: flex; align-items: center; gap: .35rem; }
    .mu-legend i { width: .7rem; height: .7rem; border-radius: 3px; display: inline-block; }
    [data-series="now"] { background: var(--primary-500); }
    [data-series="before"] { background: var(--gray-300); }
    .mu-days { display: grid; grid-auto-flow: column; grid-auto-columns: minmax(0, 1fr); gap: 2px; height: 9rem; align-items: end; padding-bottom: 1.1rem; position: relative; border-bottom: 1px solid var(--gray-200); }
    .mu-day { position: relative; height: 100%; display: flex; align-items: flex-end; justify-content: center; gap: 1px; }
    .mu-day:hover { background: var(--gray-50); }
    .mu-col { width: 42%; max-width: .5rem; border-radius: 4px 4px 0 0; min-height: 0; }
    .mu-day small { position: absolute; bottom: -1.1rem; font-size: .65rem; color: var(--gray-500); }
    .mu-split { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 18rem), 1fr)); gap: 1.25rem; padding-top: .75rem; }
    .mu-split h4 { font-size: .85rem; font-weight: 700; margin-bottom: .4rem; }
    .mu-bars { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: .45rem; }
    .mu-bars li { display: grid; grid-template-columns: minmax(6rem, 10rem) 1fr auto; gap: .6rem; align-items: center; font-size: .82rem; }
    .mu-bar-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .mu-bar-track { height: .45rem; background: var(--gray-100); border-radius: 999px; overflow: hidden; }
    .mu-bar-track span { display: block; height: 100%; background: var(--primary-500); border-radius: 0 4px 4px 0; }
    .mu-bar-value { color: var(--gray-600); font-variant-numeric: tabular-nums; white-space: nowrap; }
    .mu-rates { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 16rem), 1fr)); gap: .4rem 1.25rem; font-size: .84rem; }
    .mu-rates li { display: flex; justify-content: space-between; gap: .5rem; padding: .35rem 0; border-bottom: 1px solid var(--gray-100); }
    .mu-tabs { display: inline-flex; gap: .25rem; background: var(--gray-100); padding: .2rem; border-radius: 999px; }
    .mu-tabs button { font-size: .78rem; font-weight: 600; padding: .25rem .7rem; border-radius: 999px; color: var(--gray-600); }
    .mu-tabs button[aria-selected="true"] { background: white; color: var(--primary-700); box-shadow: 0 1px 2px rgb(0 0 0 / .08); }
    .mu-table-wrap { overflow-x: auto; }
    .mu-table { width: 100%; border-collapse: collapse; font-size: .84rem; }
    .mu-table th { text-align: start; font-weight: 600; color: var(--gray-500); font-size: .75rem; padding: .45rem .5rem; border-bottom: 1px solid var(--gray-200); }
    .mu-table td { padding: .5rem; border-bottom: 1px solid var(--gray-100); vertical-align: top; }
    .mu-table td small { color: var(--gray-500); }
    .mu-reason { display: block; }
    .mu-num { text-align: end; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .mu-num[data-sign="in"] { color: var(--success-700); font-weight: 600; }
    .dark .mu-part, .dark .mu-actions, .dark .mu-table td, .dark .mu-rates li { border-color: var(--gray-800); }
    .dark .mu-headline strong { color: white; }
    .dark .mu-tabs, .dark .mu-bar-track { background: var(--gray-800); }
    .dark .mu-tabs button[aria-selected="true"] { background: var(--gray-900); color: var(--primary-300); }
    .dark [data-series="before"] { background: var(--gray-600); }
    .dark .mu-button { border-color: var(--gray-700); color: var(--gray-200); }
    .dark .mu-day:hover { background: var(--gray-800); }
</style>
