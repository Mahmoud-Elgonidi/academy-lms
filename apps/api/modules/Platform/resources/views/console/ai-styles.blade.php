{{-- The AI engines and routing screens (Filament colour variables). --}}
<style>
    .ai-empty p { font-size: .88rem; color: var(--gray-600); }
    .ai-engine[data-off] { opacity: .65; }
    .ai-chip { flex-shrink: 0; font-size: .72rem; font-weight: 700; padding: .15rem .6rem; border-radius: 999px; background: var(--gray-100); color: var(--gray-700); }
    .ai-chip[data-status="ok"] { background: var(--success-50); color: var(--success-700); }
    .ai-chip[data-status="warn"] { background: var(--warning-50); color: var(--warning-700); }
    .ai-chip[data-status="fail"] { background: var(--danger-50); color: var(--danger-700); }
    .ai-health { font-size: .82rem; color: var(--gray-600); display: flex; flex-direction: column; gap: .15rem; }
    .ai-health em { font-style: normal; color: var(--danger-700); font-size: .78rem; }
    .ai-notes { font-size: .8rem; color: var(--gray-500); border-top: 1px solid var(--gray-100); padding-top: .5rem; }
    .ai-actions { display: inline-flex; gap: .4rem; align-items: center; }
    .ai-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
    .ai-table th { text-align: start; font-size: .75rem; color: var(--gray-500); font-weight: 600; padding: .55rem .6rem; border-bottom: 1px solid var(--gray-200); }
    .ai-table td { padding: .7rem .6rem; border-bottom: 1px solid var(--gray-100); vertical-align: top; }
    .ai-table td strong { display: block; font-weight: 600; }
    .ai-table td small { color: var(--gray-500); font-size: .74rem; }
    .ai-wrap { overflow-x: auto; }
    .ai-kind { display: inline-block; font-size: .7rem; font-weight: 600; padding: .05rem .45rem; border-radius: 999px; background: var(--primary-50); color: var(--primary-700); margin-inline-start: .3rem; }
    .ai-chain { display: flex; flex-wrap: wrap; gap: .25rem; align-items: center; }
    .ai-step { font-size: .78rem; padding: .1rem .5rem; border-radius: .4rem; background: var(--gray-100); color: var(--gray-800); white-space: nowrap; }
    .ai-step[data-first] { background: var(--primary-50); color: var(--primary-800); font-weight: 600; }
    .ai-arrow { color: var(--gray-400); font-size: .75rem; }
    .ai-scope { display: flex; flex-direction: column; gap: .35rem; }
    .ai-scope > div { display: flex; flex-wrap: wrap; gap: .3rem; align-items: center; }
    .ai-scope b { font-size: .74rem; color: var(--gray-600); font-weight: 600; }
    .ai-split { font-size: .74rem; color: var(--warning-700); }
    .ai-none { color: var(--gray-400); font-size: .8rem; }
    .ai-compare { display: flex; flex-direction: column; gap: .75rem; margin-top: 1rem; }
    .ai-compare-row { display: grid; grid-template-columns: minmax(0, 1fr); gap: .6rem; }
    @media (min-width: 900px) { .ai-compare-row { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); } }
    .ai-side { padding: .8rem; display: flex; flex-direction: column; gap: .4rem; min-width: 0; }
    .ai-side header { display: flex; justify-content: space-between; gap: .5rem; font-size: .8rem; color: var(--gray-600); }
    .ai-side pre { white-space: pre-wrap; overflow-wrap: anywhere; font-size: .78rem; background: var(--gray-50); padding: .6rem; border-radius: .5rem; max-height: 18rem; overflow: auto; margin: 0; }
    .ai-side .ai-fail { color: var(--danger-700); font-size: .82rem; }
    .ai-figs { display: flex; flex-wrap: wrap; gap: .5rem; font-size: .76rem; color: var(--gray-500); }
    .ai-figs span { font-variant-numeric: tabular-nums; }
    .ai-banner { padding: .75rem .95rem; border-radius: var(--radius-xl, .75rem); font-size: .88rem; background: var(--primary-50); color: var(--primary-800); border: 1px solid var(--primary-200); display: flex; justify-content: space-between; gap: .6rem; align-items: center; }
    .ai-cases li { display: flex; justify-content: space-between; gap: .6rem; padding: .45rem 0; border-top: 1px solid var(--gray-100); font-size: .85rem; align-items: center; }
    .ai-cases ul { list-style: none; margin: 0; padding: 0; }
    .dark .ai-chip, .dark .ai-step { background: var(--gray-800); color: var(--gray-200); }
    .dark .ai-table th, .dark .ai-table td, .dark .ai-notes, .dark .ai-cases li { border-color: var(--gray-800); }
    .dark .ai-side pre { background: var(--gray-800); }
</style>
