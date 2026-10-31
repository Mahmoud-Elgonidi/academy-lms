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
    /* A group lesson: the group tone (Filament's "info", apart from the brand's own) with a people icon. */
    .fl-group { align-self: flex-start; display: inline-flex; align-items: center; gap: .3rem; margin: .15rem 0; padding: .1rem .55rem; border-radius: 999px; font-size: .72rem; font-weight: 700;
        background: var(--info-50); color: var(--info-700); border: 1px solid var(--info-200); white-space: nowrap; }
    .fl-group-ico { width: .85rem; height: .85rem; }
    .fl-courses { display: grid; grid-template-columns: repeat(auto-fill, minmax(16rem, 1fr)); gap: .6rem; margin-bottom: .5rem; }
    .fl-course { display: flex; flex-direction: column; gap: .35rem; padding: .8rem 1rem; background: white; border: 1px solid var(--gray-200); border-top: 3px solid var(--info-500); border-radius: var(--radius-xl, .75rem); }
    .fl-course strong { font-size: .95rem; }
    .fl-course small { color: var(--gray-500); font-size: .82rem; }
    .fl-warn { color: var(--danger-600); font-size: .8rem; }
    .fl-dispute { grid-column: 1 / -1; display: flex; flex-direction: column; gap: .2rem; padding: .5rem .75rem; border-radius: var(--radius-lg, .5rem); background: var(--warning-50); color: var(--warning-800); font-size: .85rem; }
    .fl-dispute[data-s="upheld"] { background: var(--success-50); color: var(--success-800); }
    .fl-dispute[data-s="rejected"] { background: var(--gray-100); color: var(--gray-700); }
    .fl-dispute span { white-space: pre-line; }
    .fl-h2 { font-size: 1rem; font-weight: 700; margin: 1.25rem 0 .6rem; }
    .fl-empty { padding: 1.5rem; text-align: center; color: var(--gray-500); border: 1px dashed var(--gray-300); border-radius: var(--radius-xl, .75rem); }
    @media (max-width: 640px) {
        .fl-card { grid-template-columns: minmax(0, 1fr) auto; }
        .fl-what { grid-column: 1 / -1; grid-row: 2; }
    }
    .dark .fl-card, .dark .fl-course { background: var(--gray-900); border-color: var(--gray-800); }
    .dark .fl-course { border-top-color: var(--info-500); }
    .dark .fl-group { background: color-mix(in oklab, var(--info-500) 16%, var(--gray-900)); color: var(--info-300); border-color: color-mix(in oklab, var(--info-500) 35%, var(--gray-900)); }
    .dark .fl-dispute { background: color-mix(in oklab, var(--warning-500) 12%, transparent); color: var(--warning-200); }
    .dark .fl-dispute[data-s="upheld"] { background: color-mix(in oklab, var(--success-500) 12%, transparent); color: var(--success-200); }
    .dark .fl-dispute[data-s="rejected"] { background: rgb(255 255 255 / .05); color: var(--gray-300); }
</style>
