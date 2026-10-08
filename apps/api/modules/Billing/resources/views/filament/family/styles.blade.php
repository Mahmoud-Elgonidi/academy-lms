<style>
    .bf-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr)); gap: .75rem; }
    .bf-card { background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl, .75rem); padding: .9rem 1rem; display: flex; flex-direction: column; gap: .25rem; text-decoration: none; color: inherit; }
    .bf-card small { color: var(--gray-500); font-size: .8rem; }
    .bf-off { display: block; color: var(--success-700); font-size: .78rem; }
    .dark .bf-off { color: var(--success-400); }
    .bf-extra { margin-top: .35rem; padding-top: .45rem; border-top: 1px dashed var(--gray-200); display: flex; flex-wrap: wrap; align-items: center; gap: .2rem .6rem; }
    .bf-big { font-size: 1.6rem; font-weight: 800; line-height: 1.1; color: var(--primary-700); font-variant-numeric: tabular-nums; }
    .bf-card[data-tone="owed"] { border-color: var(--warning-300); background: var(--warning-50); }
    .bf-card[data-tone="owed"] .bf-big { color: var(--warning-800); }
    .bf-card[data-low] .bf-big { color: var(--danger-600); }
    .bf-list { display: flex; flex-direction: column; gap: .5rem; }
    .bf-inv { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: .2rem 1rem; align-items: center; background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl, .75rem); padding: .75rem 1rem; }
    .bf-inv strong { font-variant-numeric: tabular-nums; }
    .bf-inv small { color: var(--gray-500); font-size: .8rem; }
    .bf-doc { font-size: .8rem; font-weight: 600; color: var(--primary-600); justify-self: end; grid-column: 2; }
    .bf-status { font-size: .78rem; padding: .12rem .6rem; border-radius: 999px; background: var(--gray-100); color: var(--gray-700); justify-self: end; white-space: nowrap; }
    .bf-status[data-s="paid"] { background: var(--success-50); color: var(--success-700); }
    .bf-status[data-s="overdue"] { background: var(--danger-50); color: var(--danger-700); }
    .bf-status[data-s="issued"], .bf-status[data-s="partly_paid"] { background: var(--warning-50); color: var(--warning-800); }
    .bf-h2 { font-size: 1rem; font-weight: 700; margin: 1.25rem 0 .6rem; }
    .bf-note { color: var(--gray-600); font-size: .9rem; margin: .5rem 0 0; }
    .bf-empty { padding: 1.25rem; text-align: center; color: var(--gray-500); border: 1px dashed var(--gray-300); border-radius: var(--radius-xl, .75rem); }
    .bf-pay { grid-column: 1 / -1; display: flex; flex-wrap: wrap; gap: .5rem; justify-content: flex-end; margin-top: .35rem; }
    .bf-proof { grid-column: 1 / -1; font-size: .82rem; padding: .45rem .7rem; border-radius: var(--radius-lg, .5rem); background: var(--info-50, var(--primary-50)); color: var(--info-800, var(--primary-800)); overflow-wrap: anywhere; }
    .bf-proof[data-s="approved"] { background: var(--success-50); color: var(--success-800); }
    .bf-proof[data-s="rejected"] { background: var(--danger-50); color: var(--danger-800); }
    .bf-result { display: flex; gap: .75rem; align-items: flex-start; border-radius: var(--radius-xl, .75rem); padding: .85rem 1rem; margin-bottom: .5rem; background: var(--warning-50); color: var(--warning-800); }
    .bf-result b { font-size: 1.4rem; line-height: 1; }
    .bf-result strong { display: block; }
    .bf-result small { display: block; font-size: .85rem; opacity: .9; }
    .bf-result[data-tone="good"] { background: var(--success-50); color: var(--success-800); }
    .bf-result[data-tone="bad"] { background: var(--danger-50); color: var(--danger-800); }
    @media (prefers-reduced-motion: no-preference) {
        .bf-result { animation: lms-rise .45s var(--lms-ease) backwards; }
        .bf-result[data-tone="good"] b { display: inline-block; animation: lms-pop .6s var(--lms-spring) .2s backwards; }
    }
    .dark .bf-card, .dark .bf-inv { background: var(--gray-900); border-color: var(--gray-800); }
    .dark .bf-extra { border-color: var(--gray-700); }
    .dark .bf-proof { background: var(--gray-800); color: var(--gray-100); }
</style>
