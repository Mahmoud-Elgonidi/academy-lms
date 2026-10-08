{{-- Plans: the console's plan cards and an academy's plan with its usage (Filament colour variables). --}}
<style>
    .pp-plan { display: flex; flex-direction: column; gap: .8rem; padding: 1rem 1.05rem; }
    .pp-plan[data-archived] { opacity: .65; }
    .pp-plan header { display: flex; justify-content: space-between; gap: .6rem; align-items: flex-start; }
    .pp-plan h3 { font-weight: 700; font-size: 1.05rem; display: flex; flex-wrap: wrap; gap: .1rem .45rem; align-items: baseline; }
    .pp-plan h3 small { font-weight: 500; font-size: .8rem; color: var(--gray-500); }
    .pp-plan code { font-size: .75rem; color: var(--gray-500); direction: ltr; unicode-bidi: isolate; }
    .pp-tags { display: flex; gap: .3rem; flex-wrap: wrap; justify-content: flex-end; }
    .pp-tag { font-size: .7rem; font-weight: 700; padding: .12rem .55rem; border-radius: 999px; background: var(--gray-100); color: var(--gray-700); white-space: nowrap; }
    .pp-tag[data-tone="brand"] { background: var(--primary-50); color: var(--primary-700); }
    .pp-tag[data-tone="warn"] { background: var(--warning-50); color: var(--warning-700); }
    .pp-tag[data-tone="danger"] { background: var(--danger-50); color: var(--danger-700); }
    .pp-price { display: flex; align-items: baseline; gap: .35rem; }
    .pp-price strong { font-size: 1.3rem; font-variant-numeric: tabular-nums; }
    .pp-price small { color: var(--gray-500); font-size: .8rem; }
    .pp-limits { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: .35rem .8rem; font-size: .86rem; margin: 0; padding-top: .7rem; border-top: 1px solid var(--gray-100); }
    .pp-limits dt { color: var(--gray-500); }
    .pp-limits dd { margin: 0; font-weight: 600; text-align: end; font-variant-numeric: tabular-nums; }
    .pp-features { list-style: none; margin: 0; padding: 0; display: grid; gap: .3rem; font-size: .84rem; }
    .pp-features li { display: flex; gap: .4rem; align-items: center; }
    .pp-features li[data-on="no"] { color: var(--gray-400); }
    .pp-icon { width: 1.05rem; height: 1.05rem; flex: none; }
    .pp-features li[data-on="yes"] .pp-icon { color: var(--success-600); }
    .pp-plan footer { display: flex; justify-content: space-between; gap: .6rem; align-items: center; font-size: .8rem; color: var(--gray-500); margin-top: auto; padding-top: .6rem; border-top: 1px solid var(--gray-100); }
    .pp-meters { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 14rem), 1fr)); gap: .75rem; }
    .pp-meter { padding: .85rem .95rem; display: flex; flex-direction: column; gap: .45rem; }
    .pp-meter header { display: flex; flex-direction: column; gap: .1rem; font-size: .82rem; color: var(--gray-600); }
    .pp-meter strong { font-size: 1.05rem; color: var(--gray-950); font-variant-numeric: tabular-nums; }
    .pp-bar { height: .5rem; border-radius: 999px; background: var(--gray-100); overflow: hidden; }
    .pp-bar span { display: block; height: 100%; border-radius: inherit; background: var(--primary-500); }
    .pp-meter[data-state="near"] .pp-bar span { background: var(--warning-500); }
    .pp-meter[data-state="full"] .pp-bar span { background: var(--danger-500); }
    .pp-meter small { font-size: .75rem; color: var(--gray-500); }
    .pp-meter[data-state="near"] small.pp-state { color: var(--warning-700); }
    .pp-meter[data-state="full"] small.pp-state { color: var(--danger-700); }
    .pp-history { display: flex; flex-direction: column; font-size: .84rem; }
    .pp-history li { display: flex; justify-content: space-between; gap: .75rem; padding: .5rem 0; border-top: 1px solid var(--gray-100); }
    .pp-history li:first-child { border-top: 0; }
    .pp-history small { color: var(--gray-500); }
    .dark .pp-limits, .dark .pp-plan footer, .dark .pp-history li { border-color: var(--gray-800); }
    .dark .pp-bar { background: var(--gray-800); }
    .dark .pp-meter strong { color: white; }
</style>
<style>
    .pp-sub { margin: 0; font-size: .8rem; color: var(--gray-500); }
    .pp-link { font-size: .8rem; font-weight: 600; color: var(--primary-600); }
    .pp-link:hover { text-decoration: underline; }
    .pp-features-row { grid-template-columns: repeat(auto-fit, minmax(min(100%, 13rem), 1fr)); padding-top: .4rem; }
    .pp-features small { color: var(--gray-500); font-size: .72rem; }
    .pp-details summary { cursor: pointer; font-size: .82rem; font-weight: 600; color: var(--gray-600); padding-top: .4rem; }
    .pp-details ul { list-style: none; margin: .3rem 0 0; padding: 0; }
</style>
<style>
    .pp-ask { margin: 0; font-size: .85rem; color: var(--gray-500); }
</style>
<style>
    .pp-tags-start { justify-content: flex-start; }
</style>
