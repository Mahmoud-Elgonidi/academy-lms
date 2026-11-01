{{-- Quick counters of mistakes by kind (the exam's result, the Quran lesson): large targets for a tap during a recitation. --}}
@once
<style>
    .qc-counters { display: grid; grid-template-columns: repeat(auto-fill, minmax(12rem, 1fr)); gap: .6rem; }
    .qc-counter { display: flex; align-items: center; justify-content: space-between; gap: .5rem; padding: .55rem .65rem; border-radius: .8rem; border: 1px solid var(--gray-200); background: var(--gray-50); }
    .qc-label { font-size: .85rem; font-weight: 600; color: var(--gray-800); }
    .qc-label small { color: var(--gray-400); font-weight: 500; }
    .qc-ctrl { display: inline-flex; align-items: center; gap: .3rem; }
    .qc-ctrl button { inline-size: 2.4rem; block-size: 2.4rem; border-radius: .65rem; border: 1px solid var(--gray-300); background: white; font-size: 1.25rem; font-weight: 700; color: var(--gray-700); cursor: pointer; line-height: 1; }
    .qc-ctrl button:last-child { background: var(--primary-600); border-color: var(--primary-600); color: white; }
    .qc-ctrl button:disabled { opacity: .4; cursor: not-allowed; }
    .qc-ctrl output { min-inline-size: 2rem; text-align: center; font-size: 1.15rem; font-weight: 800; font-variant-numeric: tabular-nums; color: var(--gray-950); }
    .qr-note { inline-size: 100%; border-radius: .6rem; border: 1px solid var(--gray-300); padding: .55rem .7rem; font: inherit; font-size: .875rem; background: white; color: var(--gray-950); }
    .qr-note:focus { outline: 2px solid var(--primary-500); outline-offset: 0; border-color: var(--primary-500); }
    .dark .qc-counter { background: var(--gray-800); border-color: var(--gray-700); }
    .dark .qc-label, .dark .qc-ctrl output { color: white; }
    .dark .qc-ctrl button { background: var(--gray-900); border-color: var(--gray-600); color: var(--gray-100); }
    .dark .qc-ctrl button:last-child { background: var(--primary-600); border-color: var(--primary-600); color: white; }
    .dark .qr-note { background: var(--gray-900); border-color: var(--gray-700); color: white; }
    @media (prefers-reduced-motion: no-preference) {
        .qc-ctrl output { transition: transform .15s var(--lms-ease, ease-out); }
        .qc-ctrl button:active + output, .qc-ctrl output:has(+ button:active) { transform: scale(1.15); }
    }
</style>
@endonce
