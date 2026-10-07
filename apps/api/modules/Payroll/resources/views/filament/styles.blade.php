{{-- Payroll screens: the month bar, totals, the teachers' table and a statement's lines (academy colours, Filament variables). --}}
<style>
    .py-base { background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl, .75rem); }
    .py-stack { display: flex; flex-direction: column; gap: 1rem; }
    .py-bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .6rem; padding: .55rem .7rem; }
    .py-step { display: flex; align-items: center; gap: .35rem; }
    .py-step button { width: 2.1rem; height: 2.1rem; display: grid; place-items: center; border-radius: .6rem; color: var(--gray-600); border: 1px solid var(--gray-200); background: white; }
    .py-step button:hover:not(:disabled) { background: var(--gray-50); color: var(--primary-600); }
    .py-step button:disabled { opacity: .35; cursor: default; }
    .py-step svg { width: 1rem; height: 1rem; }
    .py-month { font-size: 1.1rem; font-weight: 700; min-width: 9.5rem; text-align: center; }
    .py-pill { display: inline-flex; align-items: center; gap: .35rem; font-size: .78rem; font-weight: 600; padding: .2rem .65rem; border-radius: 999px; background: var(--gray-100); color: var(--gray-700); }
    .py-pill[data-state="open"] { background: var(--primary-50); color: var(--primary-700); }
    .py-pill[data-state="closed"] { background: var(--success-50); color: var(--success-700); }
    .py-pill svg { width: .9rem; height: .9rem; }
    .py-tiles { display: grid; grid-template-columns: repeat(auto-fit, minmax(9.5rem, 1fr)); gap: .6rem; }
    .py-tile { padding: .8rem .95rem; display: flex; flex-direction: column; gap: .15rem; }
    .py-tile small { color: var(--gray-500); font-size: .78rem; }
    .py-tile strong { font-size: 1.45rem; line-height: 1.25; font-variant-numeric: tabular-nums; }
    .py-tile[data-main] { border-inline-start: 4px solid var(--primary-500); }
    .py-tile[data-main] strong { color: var(--primary-700); }
    .py-tile .py-totals { display: flex; flex-direction: column; }
    .py-note { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .5rem .9rem; padding: .7rem .9rem; border-radius: var(--radius-xl, .75rem); font-size: .88rem; }
    .py-note[data-tone="warning"] { background: var(--warning-50); color: var(--warning-800); border: 1px solid var(--warning-200); }
    .py-note[data-tone="info"] { background: var(--gray-50); color: var(--gray-700); border: 1px solid var(--gray-200); }
    .py-note[data-tone="success"] { background: var(--success-50); color: var(--success-800); border: 1px solid var(--success-200); }
    .py-note .py-actions { display: flex; flex-wrap: wrap; gap: .4rem; }
    .py-table-wrap { overflow-x: auto; }
    .py-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: .88rem; }
    .py-table th { background: var(--gray-50); font-weight: 600; color: var(--gray-600); font-size: .76rem; text-align: end; padding: .6rem .75rem; border-bottom: 1px solid var(--gray-200); white-space: nowrap; }
    .py-table td { padding: .65rem .75rem; border-bottom: 1px solid var(--gray-100); text-align: end; font-variant-numeric: tabular-nums; white-space: nowrap; background: white; }
    .py-table th:first-child, .py-table td:first-child { text-align: start; position: sticky; inset-inline-start: 0; min-width: 11rem; }
    .py-table th:first-child { z-index: 1; }
    .py-table tr:last-child td { border-bottom: 0; }
    .py-table tbody tr:hover td { background: var(--gray-50); }
    .py-table td a { font-weight: 600; color: inherit; }
    .py-table td a:hover { color: var(--primary-600); }
    .py-table .py-total { font-weight: 700; }
    .py-neg { color: var(--danger-600); }
    .py-pos { color: var(--success-700); }
    .py-muted { color: var(--gray-400); }
    .py-flag { display: inline-block; margin-inline-start: .4rem; font-size: .7rem; font-weight: 600; padding: .05rem .45rem; border-radius: 999px; background: var(--warning-100); color: var(--warning-800); vertical-align: middle; }
    .py-two { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1rem; align-items: start; }
    @media (min-width: 1100px) { .py-two { grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr); } }
    .py-box { padding: 1rem; display: flex; flex-direction: column; }
    .py-box h3 { font-weight: 700; font-size: .95rem; margin-bottom: .35rem; display: flex; justify-content: space-between; gap: .5rem; }
    .py-box h3 span { color: var(--gray-500); font-weight: 500; font-size: .82rem; }
    .py-line { display: flex; justify-content: space-between; gap: .9rem; padding: .6rem 0; border-top: 1px solid var(--gray-100); align-items: flex-start; }
    .py-line:first-of-type { border-top: 0; }
    .py-line > div { display: flex; flex-direction: column; gap: .12rem; min-width: 0; }
    .py-line > div:last-child { align-items: flex-end; text-align: end; flex-shrink: 0; }
    .py-line strong { font-size: .9rem; font-weight: 600; font-variant-numeric: tabular-nums; }
    .py-line small { color: var(--gray-500); font-size: .78rem; }
    .py-line p { margin: 0; font-size: .84rem; color: var(--gray-700); overflow-wrap: anywhere; }
    .py-line[data-state="cancelled"] .py-dot, .py-line[data-state="student_absent"] .py-dot { background: var(--warning-500); }
    .py-line[data-state="corrected"] .py-dot, .py-line[data-state="reversal"] .py-dot, .py-line[data-state="deduction"] .py-dot { background: var(--danger-500); }
    .py-line[data-state="bonus"] .py-dot { background: var(--success-500); }
    .py-line[data-out] p, .py-line[data-out] strong { text-decoration: line-through; color: var(--gray-400); }
    .py-dot { display: inline-block; width: .5rem; height: .5rem; border-radius: 999px; background: var(--primary-500); margin-inline-end: .35rem; vertical-align: middle; }
    .py-tags { display: flex; flex-wrap: wrap; gap: .25rem; }
    .py-tags span { font-size: .7rem; padding: .03rem .45rem; border-radius: 999px; background: var(--gray-100); color: var(--gray-700); }
    .py-tags span[data-tone="warning"] { background: var(--warning-100); color: var(--warning-800); }
    .py-empty { color: var(--gray-500); font-size: .88rem; padding: .4rem 0; }
    .py-empty-big { padding: 2.2rem 1rem; text-align: center; display: flex; flex-direction: column; gap: .35rem; align-items: center; }
    .py-empty-big strong { font-size: 1rem; }
    .py-empty-big p { color: var(--gray-500); font-size: .88rem; max-width: 34rem; margin: 0; }
    .py-rates { display: flex; flex-direction: column; gap: .25rem; }
    .py-rates div { display: flex; justify-content: space-between; gap: .75rem; font-size: .86rem; padding: .35rem 0; border-top: 1px solid var(--gray-100); }
    .py-rates div:first-child { border-top: 0; }
    .py-rates span:last-child { font-weight: 600; font-variant-numeric: tabular-nums; }
    .dark .py-base, .dark .py-table td, .dark .py-step button { background: var(--gray-900); border-color: var(--gray-800); }
    .dark .py-table th { background: var(--gray-950, #0b0b0c); border-color: var(--gray-800); color: var(--gray-400); }
    .dark .py-table tbody tr:hover td { background: rgb(255 255 255 / .03); }
    .dark .py-line, .dark .py-table td, .dark .py-rates div { border-color: var(--gray-800); }
    .dark .py-line p { color: var(--gray-300); }
    .dark .py-pill { background: rgb(255 255 255 / .07); color: var(--gray-300); }
    .dark .py-pill[data-state="open"] { background: color-mix(in oklab, var(--primary-500) 18%, transparent); color: var(--primary-300); }
    .dark .py-pill[data-state="closed"] { background: color-mix(in oklab, var(--success-500) 18%, transparent); color: var(--success-300); }
    .dark .py-tile[data-main] strong { color: var(--primary-400); }
    .dark .py-note[data-tone="warning"] { background: color-mix(in oklab, var(--warning-500) 12%, transparent); color: var(--warning-300); border-color: color-mix(in oklab, var(--warning-500) 30%, transparent); }
    .dark .py-note[data-tone="info"] { background: rgb(255 255 255 / .03); color: var(--gray-300); border-color: var(--gray-800); }
    .dark .py-note[data-tone="success"] { background: color-mix(in oklab, var(--success-500) 12%, transparent); color: var(--success-300); border-color: color-mix(in oklab, var(--success-500) 30%, transparent); }
    .dark .py-tags span { background: rgb(255 255 255 / .07); color: var(--gray-300); }
    .dark .py-neg { color: var(--danger-400); }
    .dark .py-pos { color: var(--success-400); }
</style>
