<style>
    .lf-stack { display: flex; flex-direction: column; gap: 1rem; }
    .lf-card { background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl, .75rem); padding: 1rem 1.1rem; display: flex; flex-direction: column; gap: .75rem; }
    .lf-head { display: flex; justify-content: space-between; align-items: flex-start; gap: .75rem; flex-wrap: wrap; }
    .lf-head h3 { margin: 0; font-size: 1.05rem; font-weight: 700; }
    .lf-head small, .lf-muted { color: var(--gray-500); font-size: .85rem; }
    .lf-pct { font-size: 1.75rem; font-weight: 800; color: var(--primary-700); line-height: 1; font-variant-numeric: tabular-nums; }
    .lf-state { display: inline-block; margin-top: .3rem; padding: .1rem .55rem; border-radius: 999px; font-size: .75rem; font-weight: 700; background: var(--info-50); color: var(--info-700); }
    .dark .lf-state { background: color-mix(in oklab, var(--info-500) 18%, transparent); color: var(--info-300); }
    .lf-bar { height: .55rem; border-radius: 999px; background: var(--gray-100); overflow: hidden; }
    .lf-bar i { display: block; height: 100%; background: var(--primary-500); border-radius: inherit; }
    .lf-facts { display: grid; grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr)); gap: .6rem; }
    .lf-fact { background: var(--gray-50); border-radius: var(--radius-lg, .5rem); padding: .55rem .7rem; }
    .lf-fact small { display: block; color: var(--gray-500); font-size: .75rem; }
    .lf-fact strong { font-size: .92rem; }
    .lf-here { border-inline-start: 3px solid var(--primary-500); padding-inline-start: .6rem; }
    .lf-empty { padding: 1.5rem; text-align: center; color: var(--gray-500); border: 1px dashed var(--gray-300); border-radius: var(--radius-xl, .75rem); }
    .lf-stars { color: var(--warning-500); letter-spacing: .1em; }
    .lf-items { display: flex; flex-direction: column; gap: .35rem; margin: 0; padding: 0; list-style: none; }
    .lf-items li { display: flex; justify-content: space-between; gap: .75rem; font-size: .88rem; border-bottom: 1px dashed var(--gray-200); padding-bottom: .3rem; }
    .lf-answers { display: grid; grid-template-columns: repeat(auto-fill, minmax(9.5rem, 1fr)); gap: .45rem; margin: 0; padding: 0; list-style: none; }
    .lf-answers li { display: flex; flex-direction: column; gap: .1rem; padding: .45rem .65rem; border-radius: var(--radius-lg, .75rem); background: var(--primary-50); font-size: .88rem; }
    .lf-answers small { color: var(--gray-600); font-size: .75rem; }
    .dark .lf-answers li { background: color-mix(in oklab, var(--primary-500) 14%, transparent); }
    .dark .lf-answers small { color: var(--gray-400); }
    .lf-journey { border-color: var(--primary-200); background: linear-gradient(135deg, var(--primary-50), white 70%); }
    .lf-go { font-weight: 700; font-size: .9rem; color: var(--primary-700); white-space: nowrap; }
    .dark .lf-go { color: var(--primary-300); }
    .lf-pill { font-size: .75rem; padding: .1rem .55rem; border-radius: 999px; background: var(--gray-100); color: var(--gray-700); white-space: nowrap; }
    .lf-pill[data-tone="good"] { background: var(--success-50); color: var(--success-700); }
    .lf-pill[data-tone="warn"] { background: var(--warning-50); color: var(--warning-700); }
    .lf-pill[data-tone="bad"] { background: var(--danger-50); color: var(--danger-700); }
    .lf-note { margin: 0; white-space: pre-line; font-size: .92rem; line-height: 1.7; }
    .dark .lf-card { background: var(--gray-900); border-color: var(--gray-800); }
    .dark .lf-fact { background: var(--gray-800); }
    .dark .lf-journey { border-color: var(--primary-800); background: linear-gradient(135deg, color-mix(in oklab, var(--primary-500) 14%, transparent), var(--gray-900) 70%); }
</style>
