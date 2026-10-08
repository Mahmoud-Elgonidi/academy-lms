{{-- Support screens: the queue, a ticket's conversation, the requester's own tickets (academy colours, Filament variables). --}}
<style>
    .sp-card { background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl, .75rem); }
    .sp-banner { display: flex; align-items: center; gap: .6rem; padding: .65rem .9rem; border-radius: var(--radius-xl, .75rem); background: var(--danger-50); color: var(--danger-800); font-size: .9rem; font-weight: 600; }
    .sp-banner svg { width: 1.15rem; height: 1.15rem; flex: none; }
    .sp-tabs { display: flex; gap: .3rem; padding: .25rem; border-radius: var(--radius-xl, .75rem); background: var(--gray-100); overflow-x: auto; scrollbar-width: none; max-width: 100%; }
    .sp-tabs::-webkit-scrollbar { display: none; }
    .sp-tabs button { display: inline-flex; align-items: center; gap: .4rem; flex: none; padding: .4rem .8rem; border-radius: var(--radius-lg, .5rem); font-size: .84rem; font-weight: 600; color: var(--gray-600); white-space: nowrap; }
    .sp-tabs button[aria-pressed="true"] { background: white; color: var(--primary-700); box-shadow: 0 1px 2px rgb(0 0 0 / .08); }
    .sp-count { min-width: 1.35rem; padding: 0 .35rem; border-radius: 999px; background: var(--gray-200); color: var(--gray-700); font-size: .72rem; line-height: 1.35rem; text-align: center; font-variant-numeric: tabular-nums; }
    .sp-tabs button[aria-pressed="true"] .sp-count { background: var(--primary-100); color: var(--primary-700); }
    .sp-count[data-tone="danger"] { background: var(--danger-100); color: var(--danger-700); }
    .sp-filters { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .75rem; }
    .sp-input { min-width: 0; flex: 1 1 12rem; max-width: 22rem; padding: .45rem .75rem; border-radius: var(--radius-lg, .5rem); border: 1px solid var(--gray-300); background: white; font-size: .875rem; }
    .sp-select { min-width: 0; flex: 0 1 11rem; padding: .45rem 2rem .45rem .75rem; border-radius: var(--radius-lg, .5rem); border: 1px solid var(--gray-300); background-color: white; font-size: .875rem; }
    .sp-input:focus, .sp-select:focus, .sp-compose textarea:focus { outline: 2px solid var(--primary-500); outline-offset: -1px; }
    .sp-list { display: flex; flex-direction: column; gap: .5rem; margin-top: .9rem; }
    .sp-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: .35rem .9rem; padding: .75rem .9rem; color: inherit; text-decoration: none; }
    a.sp-row:hover { border-color: var(--primary-400); }
    .sp-row[data-late] { border-inline-start: 4px solid var(--danger-500); }
    .sp-row-main { display: flex; flex-direction: column; gap: .25rem; min-width: 0; }
    .sp-row-title { display: flex; align-items: baseline; gap: .45rem; min-width: 0; }
    .sp-row-title strong { font-size: .95rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .sp-ref { color: var(--gray-500); font-size: .8rem; font-variant-numeric: tabular-nums; flex: none; }
    .sp-meta { color: var(--gray-500); font-size: .8rem; display: flex; flex-wrap: wrap; gap: .15rem .6rem; }
    .sp-tags { display: flex; flex-wrap: wrap; gap: .3rem; }
    .sp-side { display: flex; flex-direction: column; align-items: flex-end; gap: .3rem; text-align: end; font-size: .78rem; color: var(--gray-500); }
    @media (max-width: 640px) { .sp-row { grid-template-columns: minmax(0, 1fr); } .sp-side { align-items: flex-start; text-align: start; flex-direction: row; flex-wrap: wrap; } }
    .sp-pill { display: inline-flex; align-items: center; gap: .25rem; white-space: nowrap; padding: .08rem .55rem; border-radius: 999px; font-size: .72rem; font-weight: 600; background: var(--gray-100); color: var(--gray-700); }
    .sp-pill[data-kind="complaint"] { background: var(--danger-50); color: var(--danger-700); }
    .sp-pill[data-kind="billing"] { background: var(--warning-50); color: var(--warning-800); }
    .sp-pill[data-kind="technical"] { background: var(--info-50); color: var(--info-700); }
    .sp-pill[data-priority="urgent"] { background: var(--danger-600); color: white; }
    .sp-pill[data-priority="high"] { background: var(--warning-100); color: var(--warning-800); }
    .sp-pill[data-target="late"] { background: var(--danger-50); color: var(--danger-700); }
    .sp-pill[data-target="soon"] { background: var(--warning-50); color: var(--warning-800); }
    .sp-pill[data-target="ok"] { background: var(--success-50); color: var(--success-700); }
    .sp-pill[data-status="waiting"] { background: var(--primary-50); color: var(--primary-700); }
    .sp-pill[data-status="resolved"] { background: var(--success-50); color: var(--success-700); }
    .sp-pill[data-complaint="pending"] { background: var(--danger-50); color: var(--danger-700); }
    .sp-pill[data-complaint="validated"] { background: var(--danger-600); color: white; }
    .sp-pill[data-complaint="dismissed"] { background: var(--gray-100); color: var(--gray-600); }
    .sp-empty { padding: 2rem 1rem; text-align: center; color: var(--gray-500); border: 1px dashed var(--gray-300); border-radius: var(--radius-xl, .75rem); }

    /* A ticket */
    .sp-ticket { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1rem; align-items: start; }
    @media (min-width: 1100px) { .sp-ticket { grid-template-columns: minmax(0, 1fr) 21rem; } .sp-ticket > aside { order: 2; position: sticky; top: 5rem; } }
    .sp-box { padding: 1rem; display: flex; flex-direction: column; gap: .75rem; min-width: 0; }
    .sp-box h3 { font-weight: 700; font-size: .95rem; }
    .sp-facts { display: flex; flex-direction: column; gap: .6rem; font-size: .88rem; }
    .sp-facts > div { display: flex; flex-direction: column; gap: .1rem; min-width: 0; }
    .sp-facts small { color: var(--gray-500); font-size: .74rem; display: flex; justify-content: space-between; gap: .5rem; align-items: center; }
    .sp-facts span { overflow-wrap: anywhere; }
    .sp-strip { display: flex; flex-wrap: wrap; gap: .35rem; align-items: center; }
    .sp-complaint { border-radius: var(--radius-lg, .5rem); padding: .75rem .85rem; background: var(--danger-50); display: flex; flex-direction: column; gap: .5rem; font-size: .86rem; }
    .sp-complaint[data-status="dismissed"] { background: var(--gray-50); }
    .sp-complaint p { margin: 0; color: var(--gray-700); }
    .sp-complaint .sp-note { white-space: pre-line; font-style: italic; }
    .sp-actions { display: flex; flex-wrap: wrap; gap: .4rem; }

    .sp-thread { display: flex; flex-direction: column; gap: .7rem; }
    .sp-line { display: flex; flex-direction: column; gap: .2rem; max-width: min(36rem, 92%); align-self: flex-start; }
    .sp-line[data-mine] { align-self: flex-end; align-items: flex-end; }
    .sp-bubble { padding: .65rem .85rem; border-radius: 1rem; border-start-start-radius: .3rem; background: var(--gray-100); font-size: .9rem; line-height: 1.6; white-space: pre-line; overflow-wrap: anywhere; }
    .sp-line[data-mine] .sp-bubble { background: var(--primary-50); border-start-start-radius: 1rem; border-start-end-radius: .3rem; }
    .sp-line[data-kind="note"] .sp-bubble { background: var(--warning-50); border: 1px dashed var(--warning-300); }
    .sp-line small { color: var(--gray-500); font-size: .74rem; }
    .sp-line .sp-tag-note { color: var(--warning-700); font-weight: 700; }
    .sp-file { display: inline-flex; align-items: center; gap: .4rem; margin-top: .4rem; font-size: .82rem; font-weight: 600; color: var(--primary-700); text-decoration: underline; }
    .sp-file svg { width: 1rem; height: 1rem; }
    .sp-shot { display: block; margin-top: .45rem; max-width: 100%; max-height: 14rem; border-radius: .6rem; border: 1px solid var(--gray-200); }
    .sp-event { align-self: center; display: flex; flex-direction: column; align-items: center; gap: .1rem; text-align: center; font-size: .78rem; color: var(--gray-500); padding: .15rem .8rem; max-width: 100%; }
    .sp-event span { background: var(--gray-50); border: 1px solid var(--gray-200); border-radius: 999px; padding: .15rem .7rem; }
    .sp-event em { font-style: italic; color: var(--gray-600); }

    .sp-compose { display: flex; flex-direction: column; gap: .55rem; border-top: 1px solid var(--gray-100); padding-top: .85rem; }
    .sp-compose textarea { width: 100%; min-height: 5.5rem; padding: .6rem .8rem; border-radius: var(--radius-lg, .5rem); border: 1px solid var(--gray-300); background: white; font-size: .9rem; resize: vertical; }
    .sp-compose[data-mode="note"] textarea { background: var(--warning-50); border-color: var(--warning-300); }
    .sp-compose-row { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: .5rem; }
    .sp-seg { display: inline-flex; padding: .2rem; border-radius: 999px; background: var(--gray-100); }
    .sp-seg button { padding: .3rem .85rem; border-radius: 999px; font-size: .82rem; font-weight: 600; color: var(--gray-600); }
    .sp-seg button[aria-pressed="true"] { background: white; color: var(--primary-700); box-shadow: 0 1px 2px rgb(0 0 0 / .08); }
    .sp-attach { display: inline-flex; align-items: center; gap: .35rem; font-size: .82rem; color: var(--gray-600); cursor: pointer; }
    .sp-attach input { position: absolute; width: 1px; height: 1px; opacity: 0; }
    .sp-attach svg { width: 1.05rem; height: 1.05rem; }
    .sp-chosen { display: inline-flex; align-items: center; gap: .35rem; font-size: .8rem; background: var(--gray-100); border-radius: 999px; padding: .1rem .3rem .1rem .65rem; max-width: 100%; }
    .sp-chosen span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 14rem; }
    .sp-chosen button { width: 1.3rem; height: 1.3rem; border-radius: 50%; display: grid; place-items: center; color: var(--gray-500); }
    .sp-error { margin: 0; color: var(--danger-600); font-size: .84rem; }
    .sp-muted { color: var(--gray-500); font-size: .86rem; }

    /* The requester's own tickets */
    .sp-mine { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1rem; align-items: start; }
    @media (min-width: 1024px) { .sp-mine[data-open] { grid-template-columns: 20rem minmax(0, 1fr); } }
    .sp-mine-list { display: flex; flex-direction: column; gap: .45rem; }
    .sp-mine-item { display: flex; flex-direction: column; gap: .25rem; padding: .7rem .85rem; color: inherit; text-decoration: none; text-align: start; width: 100%; }
    .sp-mine-item[aria-current="true"] { border-color: var(--primary-500); box-shadow: 0 0 0 2px var(--primary-100); }
    .sp-mine-item strong { font-size: .92rem; overflow-wrap: anywhere; }
    .sp-mine-item small { color: var(--gray-500); font-size: .78rem; }
    .sp-turn { color: var(--primary-700); font-weight: 700; font-size: .78rem; }
    :where([dir="rtl"]) .sp-back svg { transform: scaleX(-1); }
    @media (max-width: 1023px) { .sp-mine[data-open] .sp-mine-list { display: none; } }

    @media (prefers-reduced-motion: no-preference) {
        .sp-line { animation: sp-in .28s ease-out backwards; }
        @keyframes sp-in { from { opacity: 0; transform: translateY(6px); } }
    }

    .dark .sp-card, .dark .sp-input, .dark .sp-select, .dark .sp-compose textarea { background: var(--gray-900); border-color: var(--gray-800); }
    .dark .sp-tabs, .dark .sp-seg { background: rgb(255 255 255 / .06); }
    .dark .sp-tabs button[aria-pressed="true"], .dark .sp-seg button[aria-pressed="true"] { background: var(--gray-800); color: var(--primary-300); }
    .dark .sp-count { background: rgb(255 255 255 / .1); color: var(--gray-300); }
    .dark .sp-pill { background: rgb(255 255 255 / .07); color: var(--gray-300); }
    .dark .sp-pill[data-kind="complaint"], .dark .sp-pill[data-target="late"], .dark .sp-pill[data-complaint="pending"] { background: color-mix(in oklab, var(--danger-500) 18%, transparent); color: var(--danger-300); }
    .dark .sp-pill[data-kind="billing"], .dark .sp-pill[data-priority="high"], .dark .sp-pill[data-target="soon"] { background: color-mix(in oklab, var(--warning-500) 18%, transparent); color: var(--warning-300); }
    .dark .sp-pill[data-kind="technical"] { background: color-mix(in oklab, var(--info-500) 18%, transparent); color: var(--info-300); }
    .dark .sp-pill[data-target="ok"], .dark .sp-pill[data-status="resolved"] { background: color-mix(in oklab, var(--success-500) 18%, transparent); color: var(--success-300); }
    .dark .sp-pill[data-status="waiting"] { background: color-mix(in oklab, var(--primary-500) 18%, transparent); color: var(--primary-300); }
    .dark .sp-bubble { background: rgb(255 255 255 / .06); }
    .dark .sp-line[data-mine] .sp-bubble { background: color-mix(in oklab, var(--primary-500) 16%, transparent); }
    .dark .sp-line[data-kind="note"] .sp-bubble, .dark .sp-compose[data-mode="note"] textarea { background: color-mix(in oklab, var(--warning-500) 12%, transparent); border-color: var(--warning-700); }
    .dark .sp-event span { background: rgb(255 255 255 / .04); border-color: var(--gray-800); }
    .dark .sp-complaint { background: color-mix(in oklab, var(--danger-500) 10%, transparent); }
    .dark .sp-complaint[data-status="dismissed"] { background: rgb(255 255 255 / .04); }
    .dark .sp-complaint p { color: var(--gray-300); }
    .dark .sp-compose { border-top-color: var(--gray-800); }
    .dark .sp-banner { background: color-mix(in oklab, var(--danger-500) 14%, transparent); color: var(--danger-200); }
    .dark .sp-chosen { background: rgb(255 255 255 / .07); }
</style>
