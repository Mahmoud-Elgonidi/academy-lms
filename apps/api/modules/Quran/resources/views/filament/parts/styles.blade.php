{{-- The Quran screens' own look (Filament's colour variables, so it follows the academy's branding; the shared motion layer for movement). --}}
@once
<style>
    .qs { display: flex; flex-direction: column; gap: 1.25rem; }
    .qs-muted { color: var(--gray-500); }
    .qs-small { font-size: .8125rem; }
    .qs-row { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem .75rem; }
    .qs-between { display: flex; align-items: flex-start; justify-content: space-between; gap: .75rem; }
    .qs-h2 { margin: 0; font-size: 1rem; font-weight: 700; color: var(--gray-950); }
    .qs-h3 { margin: 0; font-size: .95rem; font-weight: 700; color: var(--gray-950); }
    .qs-panel { padding: 1rem 1.1rem; border-radius: var(--radius-xl, .75rem); background: white; border: 1px solid var(--gray-200); display: flex; flex-direction: column; gap: .8rem; min-width: 0; }
    .qs-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr)); gap: .75rem; }
    .qs-stat { display: flex; align-items: center; gap: .85rem; padding: .95rem 1rem; border-radius: var(--radius-xl, .75rem); background: white; border: 1px solid var(--gray-200); text-decoration: none; color: inherit; }
    .qs-stat-ico { inline-size: 2.6rem; block-size: 2.6rem; border-radius: .8rem; display: grid; place-items: center; flex: none; background: var(--primary-50); color: var(--primary-700); }
    .qs-stat-ico svg { inline-size: 1.35rem; block-size: 1.35rem; }
    .qs-stat[data-tone="success"] .qs-stat-ico { background: var(--success-50); color: var(--success-700); }
    .qs-stat[data-tone="warning"] .qs-stat-ico { background: var(--warning-50); color: var(--warning-700); }
    .qs-stat[data-tone="danger"] .qs-stat-ico { background: var(--danger-50); color: var(--danger-700); }
    .qs-stat[data-tone="info"] .qs-stat-ico { background: var(--info-50); color: var(--info-700); }
    .qs-stat strong { display: block; font-size: 1.6rem; line-height: 1.1; font-weight: 800; font-variant-numeric: tabular-nums; color: var(--gray-950); }
    .qs-stat small { display: block; font-size: .78rem; color: var(--gray-500); margin-top: .1rem; }
    .qs-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(18.5rem, 1fr)); gap: .9rem; }
    .qs-cols { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 1rem; }
    .qs-card { display: flex; flex-direction: column; gap: .7rem; padding: 1rem 1.05rem; border-radius: var(--radius-xl, .75rem); background: white; border: 1px solid var(--gray-200); color: inherit; text-decoration: none; min-width: 0; }
    a.qs-card:hover { border-color: var(--primary-300); }
    .qs-card[data-muted] { opacity: .75; }
    .qs-card-title { margin: 0; font-size: 1rem; font-weight: 700; color: var(--gray-950); overflow: hidden; text-overflow: ellipsis; }
    .qs-card-sub { margin: .1rem 0 0; font-size: .8rem; color: var(--gray-500); }
    .qs-meta { display: flex; flex-wrap: wrap; gap: .3rem .9rem; font-size: .8rem; color: var(--gray-600); }
    .qs-meta span { display: inline-flex; align-items: center; gap: .3rem; }
    .qs-meta svg { inline-size: .95rem; block-size: .95rem; color: var(--gray-400); }
    .qs-pill { display: inline-flex; align-items: center; gap: .3rem; padding: .12rem .55rem; border-radius: 999px; font-size: .75rem; font-weight: 600; white-space: nowrap;
        background: var(--gray-100); color: var(--gray-700); }
    .qs-pill[data-tone="success"] { background: var(--success-50); color: var(--success-700); }
    .qs-pill[data-tone="warning"] { background: var(--warning-50); color: var(--warning-700); }
    .qs-pill[data-tone="danger"] { background: var(--danger-50); color: var(--danger-700); }
    .qs-pill[data-tone="info"] { background: var(--info-50); color: var(--info-700); }
    .qs-pill[data-tone="primary"] { background: var(--primary-50); color: var(--primary-700); }
    .qs-dot { inline-size: .45rem; block-size: .45rem; border-radius: 999px; background: currentColor; }
    .qs-empty { display: flex; flex-direction: column; align-items: center; text-align: center; gap: .55rem; padding: 2.25rem 1.25rem; border-radius: var(--radius-xl, .75rem);
        background: white; border: 1px dashed var(--gray-300); }
    .qs-empty-ico { inline-size: 3.25rem; block-size: 3.25rem; border-radius: 1rem; display: grid; place-items: center; background: var(--primary-50); color: var(--primary-700); }
    .qs-empty-ico svg { inline-size: 1.6rem; block-size: 1.6rem; }
    .qs-empty strong { font-size: 1.05rem; color: var(--gray-950); }
    .qs-empty p { margin: 0; max-inline-size: 36rem; color: var(--gray-500); font-size: .875rem; }
    .qs-filters { display: flex; flex-wrap: wrap; align-items: flex-end; gap: .6rem; }
    .qs-filters label { display: flex; flex-direction: column; gap: .3rem; min-inline-size: 11rem; flex: 1 1 11rem; max-inline-size: 18rem; }
    .qs-filters label > span { font-size: .75rem; font-weight: 600; color: var(--gray-600); }
    .qs-table-wrap { overflow-x: auto; border: 1px solid var(--gray-200); border-radius: var(--radius-xl, .75rem); background: white; }
    .qs-table { inline-size: 100%; border-collapse: collapse; font-size: .875rem; }
    .qs-table th { text-align: start; font-weight: 600; font-size: .78rem; color: var(--gray-500); padding: .65rem .8rem; border-bottom: 1px solid var(--gray-200); white-space: nowrap; background: var(--gray-50); }
    .qs-table td { padding: .7rem .8rem; border-bottom: 1px solid var(--gray-100); vertical-align: middle; }
    .qs-table tr:last-child td { border-bottom: 0; }
    .qs-table a.qs-link, a.qs-link { color: var(--primary-600); font-weight: 600; }
    .qs-name { font-weight: 600; color: var(--gray-950); }
    .qs-num { font-variant-numeric: tabular-nums; }
    .qs-list { display: flex; flex-direction: column; }
    .qs-list > * { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .6rem 0; border-bottom: 1px solid var(--gray-100); }
    .qs-list > *:last-child { border-bottom: 0; }
    .qs-progress { block-size: .45rem; border-radius: 999px; background: var(--gray-100); overflow: hidden; }
    .qs-progress > i { display: block; block-size: 100%; border-radius: inherit; background: var(--primary-500); }
    /* The strip of the mushaf's parts: in an Arabic page the first part sits on the right, as in the mushaf. */
    .qs-strip { display: grid; grid-template-columns: repeat(var(--parts, 30), minmax(0, 1fr)); gap: 2px; align-items: end; }
    .qs-strip > span { position: relative; block-size: var(--h, 1.5rem); border-radius: 3px; background: var(--gray-100); overflow: hidden; }
    .qs-strip > span > i { position: absolute; inset-inline: 0; inset-block-end: 0; block-size: calc(var(--share, 0) * 100%); background: var(--primary-500); }
    .qs-strip > span[data-in] { background: var(--primary-100); }
    .qs-strip > span[data-edge="start"] { box-shadow: inset 0 0 0 2px var(--primary-600); }
    .qs-strip > span[data-edge="end"] { box-shadow: inset 0 0 0 2px var(--primary-400); }
    .qs-strip > span[data-at]::after { content: ''; position: absolute; inset: 0; border-radius: inherit; box-shadow: inset 0 0 0 2px var(--warning-500); }
    .qs-strip-axis { display: flex; justify-content: space-between; font-size: .68rem; color: var(--gray-400); margin-top: .25rem; font-variant-numeric: tabular-nums; }
    .qs-strip-legend { display: flex; flex-wrap: wrap; gap: .3rem .9rem; font-size: .72rem; color: var(--gray-500); }
    .qs-strip-legend i { display: inline-block; inline-size: .65rem; block-size: .65rem; border-radius: 2px; vertical-align: -1px; margin-inline-end: .25rem; }
    .qs-seg { display: inline-flex; flex-wrap: wrap; gap: .3rem; padding: .25rem; border-radius: .75rem; background: var(--gray-100); }
    .qs-seg button, .qs-seg label { padding: .35rem .75rem; border-radius: .55rem; font-size: .8125rem; font-weight: 600; color: var(--gray-600); cursor: pointer; border: 0; background: transparent; }
    .qs-seg [aria-pressed="true"], .qs-seg input:checked + span { background: white; color: var(--primary-700); box-shadow: 0 1px 2px rgb(0 0 0 / .08); }
    .qs-seg input { position: absolute; opacity: 0; pointer-events: none; }
    .qs-seg label span { display: inline-block; padding: .35rem .75rem; border-radius: .55rem; }
    .qs-seg label { padding: 0; }
    .qs-tag { display: inline-flex; align-items: center; padding: .1rem .5rem; border-radius: .4rem; background: var(--gray-100); color: var(--gray-700); font-size: .78rem; font-weight: 600; }
    .qs-banner { display: flex; gap: .7rem; align-items: flex-start; padding: .8rem 1rem; border-radius: var(--radius-lg, .6rem); background: var(--warning-50); color: var(--warning-800); font-size: .875rem; }
    .qs-banner[data-tone="info"] { background: var(--info-50); color: var(--info-800); }
    .qs-banner[data-tone="danger"] { background: var(--danger-50); color: var(--danger-800); }
    .qs-banner svg { inline-size: 1.2rem; block-size: 1.2rem; flex: none; margin-top: .05rem; }
    @media (max-width: 1023px) { .qs-cols { grid-template-columns: minmax(0, 1fr); } }
    @media (max-width: 639px) {
        .qs-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .5rem; }
        .qs-stat { flex-direction: column; align-items: flex-start; gap: .45rem; padding: .75rem; }
        .qs-stat-ico { inline-size: 2.1rem; block-size: 2.1rem; }
        .qs-stat strong { font-size: 1.3rem; }
        .qs-grid { grid-template-columns: minmax(0, 1fr); }
        .qs-filters label { max-inline-size: none; }
        .qs-strip { gap: 1px; }
    }
    .dark .qs-panel, .dark .qs-stat, .dark .qs-card, .dark .qs-empty, .dark .qs-table-wrap { background: var(--gray-900); border-color: var(--gray-800); }
    .dark .qs-h2, .dark .qs-h3, .dark .qs-card-title, .dark .qs-stat strong, .dark .qs-empty strong, .dark .qs-name { color: white; }
    .dark .qs-table th { background: var(--gray-950); border-color: var(--gray-800); color: var(--gray-400); }
    .dark .qs-table td, .dark .qs-list > * { border-color: var(--gray-800); }
    .dark .qs-meta, .dark .qs-filters label > span { color: var(--gray-300); }
    .dark .qs-pill, .dark .qs-tag, .dark .qs-seg, .dark .qs-progress { background: var(--gray-800); color: var(--gray-200); }
    .dark .qs-seg [aria-pressed="true"], .dark .qs-seg input:checked + span { background: var(--gray-700); color: white; }
    .dark .qs-pill[data-tone="success"], .dark .qs-stat[data-tone="success"] .qs-stat-ico { background: color-mix(in oklab, var(--success-500) 16%, transparent); color: var(--success-300); }
    .dark .qs-pill[data-tone="warning"], .dark .qs-stat[data-tone="warning"] .qs-stat-ico { background: color-mix(in oklab, var(--warning-500) 16%, transparent); color: var(--warning-300); }
    .dark .qs-pill[data-tone="danger"], .dark .qs-stat[data-tone="danger"] .qs-stat-ico { background: color-mix(in oklab, var(--danger-500) 16%, transparent); color: var(--danger-300); }
    .dark .qs-pill[data-tone="info"], .dark .qs-stat[data-tone="info"] .qs-stat-ico { background: color-mix(in oklab, var(--info-500) 16%, transparent); color: var(--info-300); }
    .dark .qs-pill[data-tone="primary"], .dark .qs-stat-ico, .dark .qs-empty-ico { background: color-mix(in oklab, var(--primary-500) 16%, transparent); color: var(--primary-300); }
    .dark .qs-strip > span { background: var(--gray-800); }
    .dark .qs-strip > span[data-in] { background: color-mix(in oklab, var(--primary-500) 25%, var(--gray-800)); }
    .dark .qs-banner { background: color-mix(in oklab, var(--warning-500) 14%, transparent); color: var(--warning-200); }
    .dark .qs-banner[data-tone="info"] { background: color-mix(in oklab, var(--info-500) 14%, transparent); color: var(--info-200); }
    .dark .qs-banner[data-tone="danger"] { background: color-mix(in oklab, var(--danger-500) 14%, transparent); color: var(--danger-200); }
    @media (prefers-reduced-motion: no-preference) {
        .qs-strip > span > i { transform-origin: bottom; animation: qs-rise .7s var(--lms-ease, ease-out) backwards; }
        @keyframes qs-rise { from { transform: scaleY(0); } }
    }
</style>
@endonce
