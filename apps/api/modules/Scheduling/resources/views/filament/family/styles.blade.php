<style>
    .fl-list { display: flex; flex-direction: column; gap: .6rem; }
    .fl-card { display: grid; grid-template-columns: 10rem minmax(0, 1fr) auto; gap: .75rem 1rem; align-items: center; padding: .9rem 1rem; background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl, .75rem); }
    .fl-card[data-status="in_progress"] { border-color: var(--primary-400); box-shadow: 0 0 0 3px color-mix(in oklab, var(--primary-500) 15%, transparent); }
    .fl-card[data-status="cancelled"] { opacity: .65; }
    .fl-when { display: flex; flex-direction: column; }
    .fl-when strong { font-size: .95rem; }
    .fl-time { font-variant-numeric: tabular-nums; color: var(--gray-600); font-size: .9rem; }
    .fl-what { display: flex; flex-direction: column; min-width: 0; }
    .fl-what small, .fl-muted { color: var(--gray-500); font-size: .85rem; }
    .fl-side { display: flex; flex-direction: column; align-items: flex-end; gap: .35rem; }
    .fl-status { font-size: .8rem; padding: .15rem .6rem; border-radius: 999px; background: var(--gray-100); color: var(--gray-700); white-space: nowrap; }
    .fl-status[data-status="completed"] { background: var(--success-50); color: var(--success-700); }
    .fl-status[data-status="teacher_absent"], .fl-status[data-status="student_absent"] { background: var(--warning-50); color: var(--warning-700); }
    .fl-status[data-status="cancelled"] { background: var(--gray-100); color: var(--gray-500); }
    .fl-tag { font-size: .7rem; padding: .05rem .5rem; border-radius: 999px; background: var(--primary-50); color: var(--primary-700); font-weight: 600; margin-inline-start: .25rem; }
    .fl-tag[data-kind="substitute"] { background: var(--warning-50); color: var(--warning-800); }
    .fl-warn { color: var(--danger-600); font-size: .8rem; }
    .fl-h2 { font-size: 1rem; font-weight: 700; margin: 1.25rem 0 .6rem; }
    .fl-empty { padding: 1.5rem; text-align: center; color: var(--gray-500); border: 1px dashed var(--gray-300); border-radius: var(--radius-xl, .75rem); }
    @media (max-width: 640px) {
        .fl-card { grid-template-columns: minmax(0, 1fr) auto; }
        .fl-what { grid-column: 1 / -1; grid-row: 2; }
    }
    .dark .fl-card { background: var(--gray-900); border-color: var(--gray-800); }
</style>
