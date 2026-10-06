{{-- Performance screens: KPI tiles against their targets, the teachers' grid, events and ratings (academy colours, Filament variables). --}}
<style>
    .kp-base { background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl, .75rem); }
    .kp-groups { display: flex; flex-direction: column; gap: 1rem; }
    .kp-group h3 { font-size: .85rem; font-weight: 700; color: var(--gray-500); margin: 0 0 .45rem; }
    .kp-tiles { display: grid; grid-template-columns: repeat(auto-fill, minmax(12.5rem, 1fr)); gap: .6rem; }
    .kp-tile { padding: .8rem .9rem; display: flex; flex-direction: column; gap: .2rem; border-inline-start: 4px solid var(--gray-200); }
    .kp-tile[data-state="good"] { border-inline-start-color: var(--success-500); }
    .kp-tile[data-state="bad"] { border-inline-start-color: var(--danger-500); }
    .kp-tile small { color: var(--gray-500); font-size: .76rem; }
    .kp-tile strong { font-size: 1.5rem; font-variant-numeric: tabular-nums; line-height: 1.25; }
    .kp-tile[data-state="good"] strong { color: var(--success-700); }
    .kp-tile[data-state="bad"] strong { color: var(--danger-700); }
    .kp-tile .kp-label { font-weight: 600; font-size: .86rem; }
    .kp-table-wrap { overflow-x: auto; }
    .kp-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: .85rem; }
    .kp-table th { position: sticky; top: 0; background: var(--gray-50); font-weight: 600; color: var(--gray-600); font-size: .76rem; text-align: center; padding: .55rem .5rem; border-bottom: 1px solid var(--gray-200); }
    .kp-table th:first-child, .kp-table td:first-child { text-align: start; position: sticky; inset-inline-start: 0; background: inherit; min-width: 11rem; }
    .kp-table td { padding: .55rem .5rem; border-bottom: 1px solid var(--gray-100); text-align: center; font-variant-numeric: tabular-nums; white-space: nowrap; background: white; }
    .kp-table tr:hover td { background: var(--gray-50); }
    .kp-table td a { font-weight: 600; color: inherit; }
    .kp-cell[data-state="good"] { color: var(--success-700); font-weight: 600; }
    .kp-cell[data-state="bad"] { color: var(--danger-700); font-weight: 700; background: var(--danger-50) !important; }
    .kp-cell[data-state="none"] { color: var(--gray-400); }
    .kp-score { font-size: .75rem; color: var(--gray-500); font-weight: 500; display: block; }
    .kp-two { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1rem; margin-top: 1rem; align-items: start; }
    @media (min-width: 1100px) { .kp-two { grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr); } }
    .kp-box { padding: 1rem; display: flex; flex-direction: column; gap: .6rem; }
    .kp-box h3 { font-weight: 700; font-size: .95rem; }
    .kp-event { display: flex; justify-content: space-between; gap: .75rem; padding: .55rem 0; border-top: 1px solid var(--gray-100); align-items: flex-start; }
    .kp-event > div { display: flex; flex-direction: column; gap: .1rem; min-width: 0; }
    .kp-event strong { font-size: .88rem; font-weight: 600; }
    .kp-event small { color: var(--gray-500); font-size: .78rem; }
    .kp-event p { font-size: .84rem; color: var(--gray-700); margin: 0; }
    .kp-event[data-out] strong { text-decoration: line-through; color: var(--gray-400); }
    .kp-out { color: var(--primary-700) !important; }
    .kp-stars { color: var(--warning-400); letter-spacing: .05em; font-size: .95rem; }
    .kp-stars span { color: var(--gray-300); }
    .kp-tags { display: flex; flex-wrap: wrap; gap: .25rem; }
    .kp-tags span { font-size: .72rem; padding: .05rem .45rem; border-radius: 999px; background: var(--gray-100); color: var(--gray-700); }
    .kp-empty { color: var(--gray-500); font-size: .88rem; }
    .kp-summary { font-size: 1.05rem; font-weight: 700; }
    .dark .kp-base, .dark .kp-table td { background: var(--gray-900); border-color: var(--gray-800); }
    .dark .kp-table th { background: var(--gray-950, #0b0b0c); border-color: var(--gray-800); color: var(--gray-400); }
    .dark .kp-table tr:hover td { background: rgb(255 255 255 / .03); }
    .dark .kp-tile[data-state="good"] strong, .dark .kp-cell[data-state="good"] { color: var(--success-400); }
    .dark .kp-tile[data-state="bad"] strong, .dark .kp-cell[data-state="bad"] { color: var(--danger-400); }
    .dark .kp-cell[data-state="bad"] { background: color-mix(in oklab, var(--danger-500) 12%, transparent) !important; }
    .dark .kp-event, .dark .kp-table td { border-color: var(--gray-800); }
    .dark .kp-event p { color: var(--gray-300); }
    .dark .kp-tags span { background: rgb(255 255 255 / .07); color: var(--gray-300); }
</style>
