{{-- Disputes: one card per dispute, what each side said, who was in the room (academy colours, Filament variables). --}}
<style>
    .dp-card { display: flex; flex-direction: column; gap: .6rem; padding: 1rem; background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl, .75rem); }
    .dp-card[data-focused] { border-color: var(--primary-500); box-shadow: 0 0 0 3px color-mix(in oklab, var(--primary-500) 18%, transparent); }
    .dp-head { display: flex; justify-content: space-between; align-items: flex-start; gap: .75rem; }
    .dp-head > div { display: flex; flex-direction: column; gap: .1rem; min-width: 0; }
    .dp-head small, .dp-said small { color: var(--gray-500); font-size: .8rem; }
    .dp-said { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem .75rem; font-size: .9rem; }
    .dp-side { padding: .1rem .55rem; border-radius: 999px; font-size: .75rem; font-weight: 600; background: var(--info-50); color: var(--info-700); }
    .dp-side[data-side="teacher"] { background: var(--gray-100); color: var(--gray-700); }
    .dp-card .to-note { margin: 0; }
    .dp-presence { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .5rem; margin: 0; }
    .dp-presence > div { padding: .5rem .7rem; border-radius: var(--radius-lg, .5rem); border: 1px solid var(--gray-200); }
    .dp-presence > div[data-full="0"] { border-color: var(--warning-300); background: var(--warning-50); }
    .dp-presence dt { font-size: .75rem; color: var(--gray-500); }
    .dp-presence dd { margin: 0; font-size: .85rem; font-weight: 600; }
    .dp-answer { display: flex; flex-direction: column; gap: .3rem; font-size: .85rem; color: var(--gray-600); }
    .dp-short { color: var(--warning-700); }
    .dp-foot { display: flex; justify-content: space-between; align-items: center; gap: .75rem; }
    .dp-link { color: var(--primary-700); font-size: .85rem; font-weight: 600; }
    @media (max-width: 640px) { .dp-presence { grid-template-columns: minmax(0, 1fr); } }
    .dark .dp-card { background: var(--gray-900); border-color: var(--gray-800); }
    .dark .dp-presence > div { border-color: var(--gray-700); }
    .dark .dp-presence > div[data-full="0"] { background: color-mix(in oklab, var(--warning-500) 12%, transparent); border-color: color-mix(in oklab, var(--warning-500) 40%, transparent); }
    .dark .dp-side { background: color-mix(in oklab, var(--info-500) 16%, transparent); color: var(--info-200); }
    .dark .dp-side[data-side="teacher"] { background: rgb(255 255 255 / .06); color: var(--gray-300); }
</style>
