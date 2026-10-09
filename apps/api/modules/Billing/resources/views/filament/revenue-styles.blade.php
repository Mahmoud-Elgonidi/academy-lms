{{-- The revenue screen: the period bar, tiles, the chart, shares and lists (academy colours through Filament's variables). --}}
<style>
    .rv-base { background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl, .75rem); }
    .rv-stack { display: flex; flex-direction: column; gap: 1rem; min-width: 0; }
    .rv-bar { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem .9rem; padding: .6rem .7rem; }
    .rv-chips { display: flex; gap: .3rem; overflow-x: auto; scrollbar-width: none; max-width: 100%; }
    .rv-chips::-webkit-scrollbar { display: none; }
    .rv-chip { flex-shrink: 0; font-size: .8rem; font-weight: 600; padding: .35rem .75rem; border-radius: 999px; border: 1px solid var(--gray-200); color: var(--gray-600); background: white; white-space: nowrap; }
    .rv-chip:hover { color: var(--primary-600); border-color: var(--primary-300); }
    .rv-chip.is-on { background: var(--primary-600); border-color: var(--primary-600); color: white; }
    .rv-dates { display: flex; flex-wrap: wrap; gap: .5rem; }
    .rv-dates label { display: flex; align-items: center; gap: .35rem; font-size: .8rem; color: var(--gray-600); }
    .rv-dates input { border: 1px solid var(--gray-300); border-radius: .5rem; padding: .25rem .45rem; font-size: .85rem; background: white; color: inherit; }
    .rv-when { display: flex; flex-direction: column; margin-inline-start: auto; text-align: end; }
    .rv-when strong { font-size: 1rem; }
    .rv-when small { color: var(--gray-500); font-size: .76rem; }
    .rv-tiles { display: grid; grid-template-columns: repeat(auto-fit, minmax(11.5rem, 1fr)); gap: .6rem; }
    .rv-tile { padding: .85rem .95rem; display: flex; flex-direction: column; gap: .2rem; min-width: 0; }
    .rv-tile small { color: var(--gray-500); font-size: .78rem; }
    .rv-tile strong { font-size: 1.45rem; line-height: 1.25; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
    .rv-tile p { margin: 0; color: var(--gray-500); font-size: .78rem; }
    .rv-tile[data-main] { border-inline-start: 4px solid var(--primary-500); }
    .rv-tile[data-main] strong { color: var(--primary-700); }
    .rv-tile[data-tone="late"] { border-inline-start: 4px solid var(--danger-500); }
    .rv-change { align-self: flex-start; font-size: .72rem; font-weight: 600; padding: .08rem .5rem; border-radius: 999px; background: var(--gray-100); color: var(--gray-700); }
    .rv-change[data-tone="up"] { background: var(--success-50); color: var(--success-700); }
    .rv-change[data-tone="down"] { background: var(--danger-50); color: var(--danger-700); }
    .rv-late { font-size: .78rem; font-weight: 600; color: var(--danger-600); }
    .rv-late:hover { text-decoration: underline; }
    .rv-box { padding: 1rem; display: flex; flex-direction: column; gap: .35rem; min-width: 0; }
    .rv-box h3 { font-weight: 700; font-size: .95rem; }
    .rv-head { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: .3rem .8rem; margin-bottom: .25rem; }
    .rv-head a { font-size: .8rem; font-weight: 600; color: var(--primary-600); }
    .rv-head a:hover { text-decoration: underline; }
    .rv-sum { font-weight: 700; font-variant-numeric: tabular-nums; color: var(--primary-700); }
    .rv-legend { display: flex; flex-wrap: wrap; align-items: center; gap: .3rem .8rem; font-size: .78rem; color: var(--gray-600); }
    .rv-legend span::before { content: ''; display: inline-block; width: .65rem; height: .65rem; border-radius: .2rem; margin-inline-end: .3rem; vertical-align: -1px; }
    .rv-legend span[data-k="collected"]::before { background: var(--primary-600); }
    .rv-legend span[data-k="invoiced"]::before { background: var(--primary-200); }
    .rv-legend small { color: var(--gray-400); }
    .rv-chart { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: .5rem; }
    .rv-axis { display: flex; flex-direction: column; justify-content: space-between; height: 11rem; font-size: .68rem; color: var(--gray-400); text-align: end; font-variant-numeric: tabular-nums; }
    .rv-plot { display: flex; align-items: stretch; gap: 2px; min-width: 0; background: linear-gradient(var(--gray-100) 1px, transparent 1px) 0 0 / 100% 50%; }
    .rv-col { flex: 1 1 0; min-width: 0; display: flex; flex-direction: column; }
    .rv-pair { height: 11rem; display: flex; align-items: flex-end; justify-content: center; gap: 1px; border-bottom: 1px solid var(--gray-200); }
    .rv-col:hover .rv-pair { background: var(--gray-50); }
    .rv-stick { width: 45%; max-width: 1.1rem; border-radius: .2rem .2rem 0 0; }
    .rv-stick[data-k="collected"] { background: var(--primary-600); }
    .rv-stick[data-k="invoiced"] { background: var(--primary-200); }
    .rv-x { visibility: hidden; height: 1.3rem; padding-top: .25rem; font-size: .66rem; color: var(--gray-500); white-space: nowrap; overflow: visible; text-align: center; }
    .rv-x.is-shown { visibility: visible; }
    .rv-grid { display: grid; grid-template-columns: minmax(0, 1fr); gap: .75rem; }
    @media (min-width: 700px) { .rv-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (min-width: 1500px) { .rv-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    .rv-share { display: flex; flex-direction: column; gap: .3rem; padding: .45rem 0; }
    .rv-share-top { display: flex; justify-content: space-between; gap: .75rem; font-size: .85rem; }
    .rv-share-top > span { display: flex; flex-direction: column; min-width: 0; }
    .rv-share-top > span:last-child { align-items: flex-end; text-align: end; flex-shrink: 0; }
    .rv-share-top small { color: var(--gray-500); font-size: .72rem; }
    .rv-share-top strong { font-variant-numeric: tabular-nums; }
    .rv-track { height: .4rem; border-radius: 999px; background: var(--gray-100); overflow: hidden; }
    .rv-track span { display: block; height: 100%; border-radius: inherit; background: var(--primary-500); }
    .rv-two { display: grid; grid-template-columns: minmax(0, 1fr); gap: .75rem; align-items: start; }
    @media (min-width: 1100px) { .rv-two { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); } }
    .rv-row { display: flex; justify-content: space-between; gap: .9rem; padding: .6rem .5rem; margin-inline: -.5rem; border-radius: .6rem; color: inherit; }
    a.rv-row:hover { background: var(--gray-50); }
    .rv-row + .rv-row { border-top: 1px solid var(--gray-100); }
    .rv-row > div { display: flex; flex-direction: column; gap: .1rem; min-width: 0; }
    .rv-row > div:last-child { align-items: flex-end; text-align: end; flex-shrink: 0; }
    .rv-row strong { font-size: .88rem; font-weight: 600; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
    .rv-row small { color: var(--gray-500); font-size: .76rem; overflow-wrap: anywhere; }
    .rv-in { color: var(--success-700); }
    .rv-late-text { color: var(--danger-600) !important; font-weight: 600; }
    .rv-muted { color: var(--gray-500); font-size: .85rem; }
    .rv-note { color: var(--gray-500); font-size: .78rem; margin: 0 0 .25rem; }
    .rv-foot { color: var(--gray-400); font-size: .75rem; margin-top: -.5rem; }
    .rv-empty-big { padding: 2.5rem 1rem; text-align: center; display: flex; flex-direction: column; gap: .35rem; }
    .rv-empty-big strong { font-size: 1.05rem; }
    .rv-empty-big p { color: var(--gray-500); font-size: .88rem; }
    .rv-sr { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
    @media (max-width: 640px) {
        .rv-tiles { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .rv-tile[data-main] { grid-column: 1 / -1; }
        .rv-when { margin-inline-start: 0; text-align: start; width: 100%; }
        .rv-axis { display: none; }
        .rv-chart { grid-template-columns: minmax(0, 1fr); }
        .rv-tile strong { font-size: 1.25rem; }
    }
    @media (prefers-reduced-motion: no-preference) {
        .rv-stick { transform-origin: bottom center; animation: rv-rise .8s var(--lms-ease, ease-out) backwards; animation-delay: calc(.15s + var(--rv-i, 0) * 12ms); }
        @keyframes rv-rise { from { transform: scaleY(0); } }
    }
    .dark .rv-base, .dark .rv-chip, .dark .rv-dates input { background: var(--gray-900); border-color: var(--gray-800); }
    .dark .rv-chip.is-on { background: var(--primary-600); border-color: var(--primary-600); color: white; }
    .dark .rv-tile[data-main] { border-inline-start-color: var(--primary-500); }
    .dark .rv-tile[data-main] strong, .dark .rv-sum { color: var(--primary-400); }
    .dark .rv-row + .rv-row, .dark .rv-pair { border-color: var(--gray-800); }
    .dark a.rv-row:hover, .dark .rv-col:hover .rv-pair { background: rgb(255 255 255 / .03); }
    .dark .rv-track, .dark .rv-change { background: var(--gray-800); }
    .dark .rv-plot { background-image: linear-gradient(var(--gray-800) 1px, transparent 1px); }
    .dark .rv-stick[data-k="invoiced"], .dark .rv-legend span[data-k="invoiced"]::before { background: var(--gray-600); }
    .dark .rv-chip { color: var(--gray-300); }
    .dark .rv-chip:hover { color: var(--primary-400); }
</style>
