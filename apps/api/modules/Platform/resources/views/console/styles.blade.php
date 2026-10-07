{{-- Console screens: tiles, academy cards, boxes and filters (Filament colour variables). --}}
<style>
    .pc-stack { display: flex; flex-direction: column; gap: 1rem; }
    .pc-base { background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl, .75rem); }
    .pc-tiles { display: grid; grid-template-columns: repeat(auto-fit, minmax(9.5rem, 1fr)); gap: .6rem; }
    .pc-tile { padding: .8rem .95rem; display: flex; flex-direction: column; gap: .15rem; }
    .pc-tile small { color: var(--gray-500); font-size: .78rem; }
    .pc-tile strong { font-size: 1.45rem; line-height: 1.25; font-variant-numeric: tabular-nums; }
    .pc-tile em { font-style: normal; font-size: .75rem; color: var(--danger-600); }
    .pc-tile[data-main] { border-inline-start: 4px solid var(--primary-500); }
    .pc-tile[data-main] strong { color: var(--primary-700); }
    .pc-tile .pc-lines { display: flex; flex-direction: column; }
    .pc-tile .pc-lines strong { font-size: 1.05rem; }
    .pc-bar { display: flex; flex-wrap: wrap; gap: .6rem; align-items: center; justify-content: space-between; }
    .pc-search { flex: 1 1 16rem; max-width: 26rem; }
    .pc-chips { display: flex; flex-wrap: wrap; gap: .35rem; }
    .pc-chips button { font-size: .8rem; font-weight: 600; padding: .35rem .8rem; border-radius: 999px; border: 1px solid var(--gray-200); background: white; color: var(--gray-700); }
    .pc-chips button[aria-pressed="true"] { background: var(--primary-600); border-color: var(--primary-600); color: white; }
    .pc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 21rem), 1fr)); gap: .75rem; }
    .pc-card { display: flex; flex-direction: column; gap: .7rem; padding: 1rem; color: inherit; }
    .pc-card:hover { border-color: var(--primary-300); }
    .pc-card header { display: flex; justify-content: space-between; gap: .6rem; align-items: flex-start; }
    .pc-card header div { min-width: 0; display: flex; flex-direction: column; align-items: flex-start; gap: .1rem; }
    .pc-card h3 { font-weight: 700; font-size: 1rem; overflow-wrap: anywhere; }
    .pc-card header small { color: var(--gray-500); font-size: .78rem; direction: ltr; unicode-bidi: isolate; }
    .pc-status { flex-shrink: 0; font-size: .72rem; font-weight: 700; padding: .15rem .6rem; border-radius: 999px; background: var(--success-50); color: var(--success-700); }
    .pc-status[data-state="suspended"] { background: var(--danger-50); color: var(--danger-700); }
    .pc-people { font-size: .84rem; color: var(--gray-700); }
    .pc-figures { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .5rem; padding-top: .7rem; border-top: 1px solid var(--gray-100); }
    .pc-figures div { display: flex; flex-direction: column; align-items: flex-start; gap: .05rem; min-width: 0; }
    .pc-figures small { color: var(--gray-500); font-size: .72rem; }
    .pc-figures strong { font-size: .95rem; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
    .pc-foot { display: flex; justify-content: space-between; gap: .5rem; font-size: .76rem; color: var(--gray-500); }
    .pc-empty { padding: 2.2rem 1rem; text-align: center; color: var(--gray-500); font-size: .9rem; }
    .pc-two { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1rem; align-items: start; }
    @media (min-width: 1100px) { .pc-two { grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr); } }
    .pc-box { padding: 1rem; display: flex; flex-direction: column; gap: .35rem; }
    .pc-box h3 { font-weight: 700; font-size: .95rem; display: flex; justify-content: space-between; gap: .5rem; align-items: center; }
    .pc-box h3 a { font-size: .8rem; font-weight: 600; color: var(--primary-600); }
    .pc-side { display: flex; flex-direction: column; gap: 1rem; }
    .pc-dl { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: .45rem .9rem; font-size: .86rem; margin: 0; }
    .pc-dl dt { color: var(--gray-500); }
    .pc-dl dd { margin: 0; display: flex; flex-direction: column; align-items: flex-end; text-align: end; overflow-wrap: anywhere; min-width: 0; }
    .pc-dl dd span { direction: ltr; unicode-bidi: isolate; }
    .pc-dl dd small { color: var(--warning-700); }
    .pc-person { display: flex; justify-content: space-between; gap: .75rem; padding: .55rem 0; border-top: 1px solid var(--gray-100); align-items: flex-start; }
    .pc-person:first-of-type { border-top: 0; }
    .pc-person div { display: flex; flex-direction: column; align-items: flex-start; gap: .1rem; min-width: 0; }
    .pc-person strong { font-size: .9rem; font-weight: 600; }
    .pc-person small { color: var(--gray-500); font-size: .78rem; direction: ltr; unicode-bidi: isolate; overflow-wrap: anywhere; max-width: 100%; }
    .pc-role { flex-shrink: 0; font-size: .7rem; font-weight: 600; padding: .1rem .5rem; border-radius: 999px; background: var(--gray-100); color: var(--gray-700); }
    .pc-role[data-role="owner"] { background: var(--primary-50); color: var(--primary-700); }
    .pc-note { padding: .75rem .95rem; border-radius: var(--radius-xl, .75rem); font-size: .88rem; background: var(--danger-50); color: var(--danger-800); border: 1px solid var(--danger-200); }
    .pc-filters { display: grid; grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr)); gap: .6rem; padding: .8rem; }
    .pc-filters label { display: flex; flex-direction: column; gap: .3rem; font-size: .78rem; color: var(--gray-500); font-weight: 600; }
    .pc-more { display: flex; justify-content: center; padding-top: .4rem; }
    .dark .pc-base, .dark .pc-chips button { background: var(--gray-900); border-color: var(--gray-800); }
    .dark .pc-chips button { color: var(--gray-300); }
    .dark .pc-chips button[aria-pressed="true"] { background: var(--primary-600); border-color: var(--primary-600); color: white; }
    .dark .pc-figures, .dark .pc-person { border-color: var(--gray-800); }
    .dark .pc-people { color: var(--gray-300); }
    .dark .pc-tile[data-main] strong { color: var(--primary-400); }
    .dark .pc-status { background: color-mix(in oklab, var(--success-500) 16%, transparent); color: var(--success-300); }
    .dark .pc-status[data-state="suspended"] { background: color-mix(in oklab, var(--danger-500) 16%, transparent); color: var(--danger-300); }
    .dark .pc-role { background: rgb(255 255 255 / .07); color: var(--gray-300); }
    .dark .pc-role[data-role="owner"] { background: color-mix(in oklab, var(--primary-500) 18%, transparent); color: var(--primary-300); }
    .dark .pc-note { background: color-mix(in oklab, var(--danger-500) 12%, transparent); color: var(--danger-300); border-color: color-mix(in oklab, var(--danger-500) 30%, transparent); }
    .dark .pc-dl dd small { color: var(--warning-300); }
</style>
