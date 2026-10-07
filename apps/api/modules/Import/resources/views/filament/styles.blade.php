{{-- The import screen: steps, kind cards, column mapping, the dry run's rows and the history (academy colours, Filament variables). --}}
<style>
    .im-base { background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl, .75rem); }
    .im-stack { display: flex; flex-direction: column; gap: 1rem; }
    .im-steps { display: flex; gap: .4rem; flex-wrap: wrap; list-style: none; margin: 0; padding: 0; }
    .im-steps li { display: flex; align-items: center; gap: .45rem; font-size: .85rem; color: var(--gray-500); padding: .35rem .8rem .35rem .45rem; border-radius: 999px; background: var(--gray-100); }
    .im-steps li span { display: grid; place-items: center; width: 1.45rem; height: 1.45rem; border-radius: 999px; background: white; font-weight: 700; font-size: .78rem; }
    .im-steps li[data-state="now"] { background: var(--primary-50); color: var(--primary-700); font-weight: 600; }
    .im-steps li[data-state="now"] span { background: var(--primary-600); color: white; }
    .im-steps li[data-state="past"] { color: var(--success-700); }
    .im-steps li[data-state="past"] span { background: var(--success-100); color: var(--success-700); }
    .im-kinds { display: grid; grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr)); gap: .7rem; }
    .im-kind { position: relative; text-align: start; padding: 1rem 1rem .85rem; display: flex; flex-direction: column; gap: .35rem; cursor: pointer; transition: border-color .15s, box-shadow .15s; }
    .im-kind:hover { border-color: var(--primary-300); }
    .im-kind[data-on] { border-color: var(--primary-500); box-shadow: 0 0 0 3px color-mix(in oklab, var(--primary-500) 18%, transparent); }
    .im-kind strong { font-size: 1rem; display: flex; align-items: center; gap: .5rem; }
    .im-kind strong em { font-style: normal; display: grid; place-items: center; width: 1.6rem; height: 1.6rem; border-radius: .55rem; background: var(--primary-50); color: var(--primary-700); font-size: .82rem; }
    .im-kind p { margin: 0; color: var(--gray-600); font-size: .86rem; line-height: 1.55; }
    .im-kind .im-link { margin-top: .25rem; font-size: .82rem; font-weight: 600; color: var(--primary-600); background: none; border: 0; padding: 0; cursor: pointer; align-self: flex-start; }
    .im-drop { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .8rem; padding: 1rem; border-style: dashed; }
    .im-drop label { display: flex; flex-direction: column; gap: .15rem; cursor: pointer; min-width: 0; }
    .im-drop label strong { font-size: .95rem; }
    .im-drop label small { color: var(--gray-500); font-size: .8rem; }
    .im-drop input[type=file] { font-size: .85rem; max-width: 100%; }
    .im-note { font-size: .84rem; color: var(--gray-500); margin: 0; }
    .im-box { padding: 1rem; display: flex; flex-direction: column; gap: .7rem; }
    .im-box h3 { margin: 0; font-weight: 700; font-size: 1rem; }
    .im-box > p { margin: 0; color: var(--gray-600); font-size: .87rem; }
    .im-map { display: grid; grid-template-columns: minmax(0, 1fr); gap: .1rem; }
    .im-map > div { display: grid; grid-template-columns: minmax(0, 1fr); gap: .35rem; padding: .6rem 0; border-top: 1px solid var(--gray-100); align-items: center; }
    @media (min-width: 760px) { .im-map > div { grid-template-columns: minmax(0, 1fr) minmax(0, 1.1fr); gap: 1rem; } }
    .im-map label { display: flex; flex-direction: column; gap: .1rem; font-size: .9rem; font-weight: 600; }
    .im-map label small { font-weight: 400; color: var(--gray-500); font-size: .78rem; }
    .im-req { color: var(--danger-600); font-size: .75rem; font-weight: 600; }
    .im-map select, .im-opts input[type=text] { width: 100%; border: 1px solid var(--gray-300); border-radius: .55rem; padding: .45rem .6rem; font-size: .88rem; background: white; }
    .im-opts { display: flex; flex-direction: column; gap: .6rem; padding-top: .6rem; border-top: 1px solid var(--gray-100); }
    .im-opts .im-code { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; font-size: .9rem; }
    .im-opts .im-code input { width: 6.5rem; direction: ltr; }
    .im-opts .im-check { display: flex; gap: .55rem; align-items: flex-start; font-size: .9rem; }
    .im-opts .im-check input { margin-top: .3rem; accent-color: var(--primary-600); }
    .im-opts .im-check small { display: block; color: var(--gray-500); font-size: .78rem; }
    .im-actions { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
    .im-tiles { display: grid; grid-template-columns: repeat(auto-fit, minmax(8.5rem, 1fr)); gap: .6rem; }
    .im-tile { padding: .75rem .9rem; display: flex; flex-direction: column; gap: .1rem; text-align: start; cursor: pointer; border-inline-start: 4px solid var(--gray-200); }
    .im-tile small { color: var(--gray-500); font-size: .78rem; }
    .im-tile strong { font-size: 1.45rem; font-variant-numeric: tabular-nums; line-height: 1.25; }
    .im-tile[data-tone="new"] { border-inline-start-color: var(--success-500); } .im-tile[data-tone="new"] strong { color: var(--success-700); }
    .im-tile[data-tone="update"] { border-inline-start-color: var(--primary-500); } .im-tile[data-tone="update"] strong { color: var(--primary-700); }
    .im-tile[data-tone="skip"] { border-inline-start-color: var(--gray-400); }
    .im-tile[data-tone="error"] { border-inline-start-color: var(--danger-500); } .im-tile[data-tone="error"] strong { color: var(--danger-700); }
    .im-tile[data-on] { box-shadow: 0 0 0 2px color-mix(in oklab, var(--primary-500) 35%, transparent); }
    .im-rows { padding: .3rem 1rem; }
    .im-row { display: flex; gap: .8rem; padding: .6rem 0; border-top: 1px solid var(--gray-100); align-items: flex-start; }
    .im-row:first-child { border-top: 0; }
    .im-row .im-line { flex-shrink: 0; width: 4.2rem; color: var(--gray-500); font-size: .78rem; padding-top: .15rem; font-variant-numeric: tabular-nums; }
    .im-row > div { display: flex; flex-direction: column; gap: .15rem; min-width: 0; flex: 1; }
    .im-row strong { font-size: .9rem; font-weight: 600; }
    .im-row p { margin: 0; font-size: .83rem; color: var(--gray-600); overflow-wrap: anywhere; }
    .im-row[data-tone="error"] p { color: var(--danger-700); }
    .im-chip { flex-shrink: 0; font-size: .74rem; font-weight: 600; padding: .12rem .55rem; border-radius: 999px; background: var(--gray-100); color: var(--gray-700); white-space: nowrap; }
    .im-chip[data-tone="new"] { background: var(--success-50); color: var(--success-700); }
    .im-chip[data-tone="update"] { background: var(--primary-50); color: var(--primary-700); }
    .im-chip[data-tone="error"] { background: var(--danger-50); color: var(--danger-700); }
    .im-history { padding: .3rem 1rem; }
    .im-history > div { display: flex; flex-wrap: wrap; justify-content: space-between; gap: .4rem .8rem; padding: .65rem 0; border-top: 1px solid var(--gray-100); align-items: center; }
    .im-history > div:first-child { border-top: 0; }
    .im-history strong { font-size: .9rem; }
    .im-history small { color: var(--gray-500); font-size: .78rem; display: block; }
    .im-history button { font-size: .82rem; font-weight: 600; color: var(--primary-600); }
    .im-counts { display: flex; gap: .3rem; flex-wrap: wrap; }
    .dark .im-base, .dark .im-step button, .dark .im-map select, .dark .im-opts input[type=text] { background: var(--gray-900); border-color: var(--gray-800); }
    .dark .im-steps li { background: rgb(255 255 255 / .06); color: var(--gray-400); }
    .dark .im-steps li span { background: rgb(255 255 255 / .08); }
    .dark .im-steps li[data-state="now"] { background: color-mix(in oklab, var(--primary-500) 18%, transparent); color: var(--primary-300); }
    .dark .im-kind p, .dark .im-box > p, .dark .im-row p { color: var(--gray-400); }
    .dark .im-kind strong em { background: color-mix(in oklab, var(--primary-500) 18%, transparent); color: var(--primary-300); }
    .dark .im-map > div, .dark .im-opts, .dark .im-row, .dark .im-history > div { border-color: var(--gray-800); }
    .dark .im-chip { background: rgb(255 255 255 / .07); color: var(--gray-300); }
    .dark .im-chip[data-tone="new"], .dark .im-tile[data-tone="new"] strong { color: var(--success-400); }
    .dark .im-chip[data-tone="error"], .dark .im-tile[data-tone="error"] strong, .dark .im-row[data-tone="error"] p { color: var(--danger-400); }
    .dark .im-chip[data-tone="new"] { background: color-mix(in oklab, var(--success-500) 15%, transparent); }
    .dark .im-chip[data-tone="error"] { background: color-mix(in oklab, var(--danger-500) 15%, transparent); }
    .dark .im-chip[data-tone="update"] { background: color-mix(in oklab, var(--primary-500) 15%, transparent); color: var(--primary-300); }
</style>
