{{-- Time off and holidays: cards, status pills and the cover rows (academy colours, Filament variables). --}}
<style>
    .to-list { display: flex; flex-direction: column; gap: .6rem; }
    .to-h2 { display: flex; align-items: center; gap: .5rem; margin: 1.25rem 0 .6rem; font-size: 1rem; font-weight: 700; }
    .to-h2 small { font-weight: 500; color: var(--gray-500); font-size: .8rem; }
    .to-card { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr) auto; align-items: center; gap: .5rem 1rem; padding: .9rem 1rem;
        background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl, .75rem); color: inherit; text-decoration: none; }
    a.to-card:hover { border-color: var(--primary-400); }
    .to-card > div { display: flex; flex-direction: column; gap: .15rem; min-width: 0; }
    .to-card strong { font-size: .95rem; }
    .to-card small, .to-muted { color: var(--gray-500); font-size: .8rem; }
    .to-side { align-items: flex-end; }
    .to-pill { align-self: flex-start; white-space: nowrap; padding: .12rem .6rem; border-radius: 999px; font-size: .75rem; font-weight: 600; background: var(--gray-100); color: var(--gray-700); }
    .to-pill[data-s="pending"] { background: var(--warning-50); color: var(--warning-800); }
    .to-pill[data-s="approved"], .to-pill[data-s="covered"] { background: var(--primary-50); color: var(--primary-700); }
    .to-pill[data-s="rejected"], .to-pill[data-s="needs_cover"] { background: var(--danger-50); color: var(--danger-700); }
    .to-left { color: var(--danger-700); font-weight: 600; font-size: .85rem; }
    .to-ok { color: var(--primary-700); font-size: .85rem; }
    .to-note { margin-top: .25rem; padding: .45rem .65rem; border-radius: var(--radius-lg, .5rem); background: var(--gray-50); color: var(--gray-700); font-size: .85rem; white-space: pre-line; }
    .to-empty { padding: 1.1rem; text-align: center; color: var(--gray-500); border: 1px dashed var(--gray-300); border-radius: var(--radius-xl, .75rem); font-size: .9rem; }
    .to-banner { display: flex; gap: .6rem; align-items: center; padding: .75rem 1rem; border-radius: var(--radius-xl, .75rem); background: var(--info-50); color: var(--info-800); font-size: .9rem; }
    .to-banner svg { width: 1.25rem; height: 1.25rem; flex: none; }
    @media (max-width: 760px) { .to-card { grid-template-columns: minmax(0, 1fr) auto; } .to-card > .to-mid { grid-column: 1 / -1; grid-row: 2; } }
    .dark .to-card { background: var(--gray-900); border-color: var(--gray-800); }
    .dark .to-note { background: rgb(255 255 255 / .04); color: var(--gray-300); }
    .dark .to-banner { background: color-mix(in oklab, var(--info-500) 14%, transparent); color: var(--info-200); }
</style>
