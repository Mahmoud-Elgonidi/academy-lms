{{-- The clock changes screen: one card per weekly time, the student's side and the teacher's (academy colours, Filament variables). --}}
<style>
    .dst-list { display: flex; flex-direction: column; gap: .6rem; }
    .dst-h2 { display: flex; flex-wrap: wrap; align-items: baseline; gap: .25rem .6rem; margin: 1.25rem 0 .6rem; font-size: 1rem; font-weight: 700; }
    .dst-h2 small { font-weight: 500; color: var(--gray-500); font-size: .85rem; }
    .dst-card { display: grid; grid-template-columns: 1.5rem minmax(0, 1.1fr) minmax(0, .9fr) minmax(0, .9fr) minmax(0, 1.3fr); grid-template-areas: "pick who student teacher end";
        align-items: center; gap: .6rem 1rem; padding: .9rem 1rem; background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl, .75rem); }
    .dst-pick { grid-area: pick; } .dst-who { grid-area: who; } .dst-student { grid-area: student; } .dst-teacher { grid-area: teacher; } .dst-end { grid-area: end; }
    .dst-card[data-decision="keep_student"] { border-color: var(--primary-200); }
    .dst-card > div { display: flex; flex-direction: column; gap: .2rem; min-width: 0; }
    .dst-card small { color: var(--gray-500); font-size: .8rem; }
    .dst-pick input { width: 1.05rem; height: 1.05rem; accent-color: var(--primary-600); }
    .dst-times { display: inline-flex; flex-wrap: wrap; align-items: center; gap: .35rem; font-weight: 600; font-variant-numeric: tabular-nums; }
    .dst-old { color: var(--gray-400); text-decoration: line-through; font-weight: 500; }
    .dst-new { color: var(--warning-700); }
    .dst-card[data-decision="keep_student"] .dst-new { color: var(--primary-700); }
    .dst-kept { font-style: normal; font-size: .75rem; font-weight: 600; padding: .05rem .45rem; border-radius: 999px; background: var(--primary-50); color: var(--primary-700); }
    .dst-arrow { width: 1rem; height: 1rem; color: var(--gray-400); }
    [dir="rtl"] .dst-arrow { transform: scaleX(-1); }
    .dst-end { align-items: flex-end; text-align: end; }
    .dst-pill { white-space: nowrap; padding: .12rem .6rem; border-radius: 999px; font-size: .75rem; font-weight: 600; background: var(--gray-100); color: var(--gray-700); }
    .dst-pill[data-d="pending"] { background: var(--warning-50); color: var(--warning-800); }
    .dst-pill[data-d="keep_student"] { background: var(--primary-50); color: var(--primary-700); }
    .dst-ok { color: var(--primary-700) !important; }
    .dst-warn { color: var(--danger-700) !important; }
    .dst-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; align-items: center; gap: .5rem .9rem; margin-top: .2rem; }
    .dst-bar { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem .9rem; padding: .6rem .9rem; border-radius: var(--radius-xl, .75rem);
        background: var(--gray-50); border: 1px solid var(--gray-200); font-size: .9rem; }
    .dst-bar[data-on="1"] { background: var(--primary-50); border-color: var(--primary-200); }
    .dst-bar .dst-count { font-weight: 600; margin-inline-end: auto; }
    .dst-link { color: var(--primary-700); font-weight: 600; font-size: .85rem; }
    .dst-empty { display: flex; flex-direction: column; align-items: center; gap: .5rem; padding: 2.5rem 1rem; text-align: center; color: var(--gray-500);
        border: 1px dashed var(--gray-300); border-radius: var(--radius-xl, .75rem); }
    .dst-empty svg { width: 2rem; height: 2rem; color: var(--primary-500); }
    @media (max-width: 860px) {
        .dst-card { grid-template-columns: 1.5rem minmax(0, 1fr) minmax(0, 1fr); grid-template-areas: "pick who who" ". student teacher" ". end end"; align-items: start; }
        .dst-card > .dst-end { align-items: flex-start; text-align: start; padding-top: .55rem; border-top: 1px dashed var(--gray-200); }
        .dst-actions { justify-content: flex-start; }
        .dst-times { flex-direction: column; align-items: flex-start; gap: .1rem; }
        .dst-times .dst-arrow { display: none; }
    }
    .dark .dst-card { background: var(--gray-900); border-color: var(--gray-800); }
    .dark .dst-bar { background: rgb(255 255 255 / .03); border-color: var(--gray-800); }
    .dark .dst-bar[data-on="1"] { background: color-mix(in oklab, var(--primary-500) 12%, transparent); }
    .dark .dst-new { color: var(--warning-400); }
    .dark .dst-card > .dst-end { border-top-color: var(--gray-800); }
    .dark .dst-pill { background: rgb(255 255 255 / .06); color: var(--gray-300); }
    .dark .dst-pill[data-d="pending"] { background: color-mix(in oklab, var(--warning-500) 16%, transparent); color: var(--warning-300); }
    .dark .dst-pill[data-d="keep_student"], .dark .dst-kept { background: color-mix(in oklab, var(--primary-500) 16%, transparent); color: var(--primary-300); }
    .dark .dst-ok, .dark .dst-link, .dark .dst-card[data-decision="keep_student"] .dst-new { color: var(--primary-400) !important; }
    .dark .dst-warn { color: var(--danger-400) !important; }
    .dark .dst-card[data-decision="keep_student"] { border-color: color-mix(in oklab, var(--primary-500) 35%, transparent); }
</style>
