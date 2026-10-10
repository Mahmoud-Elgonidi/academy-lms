{{-- Messaging screens: the conversations list, one conversation, writing, and oversight (academy colours, Filament variables). --}}
<style>
    .ms-card { background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl, .75rem); }
    .ms-note { display: flex; gap: .55rem; align-items: flex-start; padding: .65rem .9rem; border-radius: var(--radius-xl, .75rem); background: var(--primary-50); color: var(--primary-800); font-size: .86rem; line-height: 1.6; }
    .ms-note svg { width: 1.15rem; height: 1.15rem; flex: none; margin-top: .15rem; }
    .ms-chat { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1rem; align-items: start; }
    @media (min-width: 1024px) { .ms-chat { grid-template-columns: 19rem minmax(0, 1fr); } }
    @media (max-width: 1023px) { .ms-chat[data-open] .ms-list { display: none; } .ms-chat:not([data-open]) .ms-pick { display: none; } }
    .ms-list { display: flex; flex-direction: column; gap: .45rem; min-width: 0; }
    .ms-item { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; gap: .7rem; align-items: center; padding: .65rem .8rem; width: 100%; text-align: start; color: inherit; }
    .ms-item[aria-current="true"] { border-color: var(--primary-500); box-shadow: 0 0 0 2px var(--primary-100); }
    .ms-avatar { width: 2.4rem; height: 2.4rem; border-radius: 999px; display: grid; place-items: center; background: var(--primary-100); color: var(--primary-700); font-weight: 700; }
    .ms-item-main { display: flex; flex-direction: column; gap: .1rem; min-width: 0; }
    .ms-item-main strong { font-size: .92rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ms-item-main small { color: var(--gray-500); font-size: .8rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ms-item-side { display: flex; flex-direction: column; align-items: flex-end; gap: .25rem; font-size: .72rem; color: var(--gray-500); }
    .ms-unread { min-width: 1.35rem; padding: 0 .35rem; border-radius: 999px; background: var(--primary-600); color: white; font-size: .72rem; line-height: 1.35rem; text-align: center; font-variant-numeric: tabular-nums; }
    .ms-lock { width: .9rem; height: .9rem; color: var(--gray-400); }
    .ms-empty { padding: 2rem 1rem; text-align: center; color: var(--gray-500); border: 1px dashed var(--gray-300); border-radius: var(--radius-xl, .75rem); }
    .ms-pick { padding: 3rem 1rem; text-align: center; color: var(--gray-500); }

    .ms-box { display: flex; flex-direction: column; min-width: 0; overflow: hidden; }
    .ms-head { display: flex; align-items: center; gap: .7rem; padding: .75rem 1rem; border-bottom: 1px solid var(--gray-100); }
    .ms-head > div { display: flex; flex-direction: column; min-width: 0; }
    .ms-head strong { font-size: 1rem; }
    .ms-head small { color: var(--gray-500); font-size: .78rem; overflow-wrap: anywhere; }
    .ms-back { flex: none; }
    @media (min-width: 1024px) { .ms-back { display: none; } }
    :where([dir="rtl"]) .ms-back svg { transform: scaleX(-1); }
    .ms-thread { display: flex; flex-direction: column; gap: .6rem; padding: 1rem; max-height: min(62vh, 40rem); overflow-y: auto; overscroll-behavior: contain; }
    .ms-line { display: flex; flex-direction: column; gap: .2rem; max-width: min(34rem, 88%); align-self: flex-start; }
    .ms-line[data-mine] { align-self: flex-end; align-items: flex-end; }
    .ms-bubble { padding: .6rem .85rem; border-radius: 1.1rem; border-start-start-radius: .3rem; background: var(--gray-100); font-size: .92rem; line-height: 1.65; white-space: pre-line; overflow-wrap: anywhere; }
    .ms-line[data-mine] .ms-bubble { background: var(--primary-600); color: white; border-start-start-radius: 1.1rem; border-start-end-radius: .3rem; }
    .ms-line[data-side="guardian"]:not([data-mine]) .ms-bubble { background: var(--warning-50); }
    .ms-line small { color: var(--gray-500); font-size: .72rem; }
    .ms-hidden { display: inline-flex; align-items: center; gap: .3rem; color: var(--gray-600); font-size: .74rem; }
    .ms-hidden svg { width: .9rem; height: .9rem; }
    .ms-first { margin: auto; padding: 2rem 1rem; text-align: center; color: var(--gray-500); }
    .ms-why { margin: 0; padding: .8rem 1rem; border-top: 1px solid var(--gray-100); background: var(--gray-50); color: var(--gray-600); font-size: .86rem; }
    .ms-compose { display: flex; gap: .5rem; align-items: flex-end; padding: .75rem; border-top: 1px solid var(--gray-100); }
    .ms-compose textarea { flex: 1; min-width: 0; min-height: 2.75rem; max-height: 9rem; padding: .6rem .8rem; border-radius: 1.2rem; border: 1px solid var(--gray-300); background: white; font-size: .92rem; resize: none; field-sizing: content; }
    .ms-compose textarea:focus, .ms-input:focus { outline: 2px solid var(--primary-500); outline-offset: -1px; }
    .ms-hint { padding: 0 1rem .6rem; color: var(--gray-400); font-size: .72rem; }
    @media (pointer: coarse) { .ms-hint { display: none; } }

    /* Oversight */
    .ms-tabs { display: flex; gap: .3rem; padding: .25rem; border-radius: var(--radius-xl, .75rem); background: var(--gray-100); overflow-x: auto; scrollbar-width: none; max-width: 100%; }
    .ms-tabs::-webkit-scrollbar { display: none; }
    .ms-tabs button { display: inline-flex; align-items: center; gap: .4rem; flex: none; padding: .4rem .8rem; border-radius: var(--radius-lg, .5rem); font-size: .84rem; font-weight: 600; color: var(--gray-600); white-space: nowrap; }
    .ms-tabs button[aria-pressed="true"] { background: white; color: var(--primary-700); box-shadow: 0 1px 2px rgb(0 0 0 / .08); }
    .ms-count { min-width: 1.35rem; padding: 0 .35rem; border-radius: 999px; background: var(--gray-200); color: var(--gray-700); font-size: .72rem; line-height: 1.35rem; text-align: center; }
    .ms-count[data-tone="warning"] { background: var(--warning-100); color: var(--warning-800); }
    .ms-filters { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; justify-content: space-between; }
    .ms-input { min-width: 0; flex: 1 1 12rem; max-width: 22rem; padding: .45rem .75rem; border-radius: var(--radius-lg, .5rem); border: 1px solid var(--gray-300); background: white; font-size: .875rem; }
    .ms-row { display: flex; flex-direction: column; gap: .3rem; padding: .7rem .85rem; width: 100%; text-align: start; color: inherit; }
    .ms-row[aria-current="true"] { border-color: var(--primary-500); box-shadow: 0 0 0 2px var(--primary-100); }
    .ms-row[data-look] { border-inline-start: 4px solid var(--warning-500); }
    .ms-row strong { font-size: .9rem; overflow-wrap: anywhere; }
    .ms-meta { display: flex; flex-wrap: wrap; gap: .3rem .5rem; align-items: center; color: var(--gray-500); font-size: .76rem; }
    .ms-pill { display: inline-flex; align-items: center; white-space: nowrap; padding: .05rem .5rem; border-radius: 999px; font-size: .7rem; font-weight: 600; background: var(--gray-100); color: var(--gray-700); }
    .ms-pill[data-tone="warning"] { background: var(--warning-50); color: var(--warning-800); }
    .ms-pill[data-tone="danger"] { background: var(--danger-50); color: var(--danger-700); }
    .ms-banner { padding: .65rem 1rem; background: var(--danger-50); color: var(--danger-800); font-size: .84rem; border-bottom: 1px solid var(--danger-100); }
    .ms-staff { display: flex; flex-wrap: wrap; gap: .35rem .6rem; align-items: center; }
    .ms-staff button { font-size: .74rem; font-weight: 600; color: var(--primary-700); text-decoration: underline; }
    .ms-original { margin-top: .3rem; padding: .5rem .7rem; border-radius: .6rem; border: 1px dashed var(--danger-300); background: var(--danger-50); color: var(--danger-800); font-size: .84rem; white-space: pre-line; overflow-wrap: anywhere; }
    .ms-original b { display: block; font-size: .72rem; margin-bottom: .2rem; }
    .ms-logged { padding: .5rem 1rem; color: var(--gray-500); font-size: .74rem; border-top: 1px solid var(--gray-100); }
    .ms-actions { display: flex; flex-wrap: wrap; gap: .4rem; margin-inline-start: auto; }

    @media (prefers-reduced-motion: no-preference) {
        .ms-line { animation: ms-in .26s ease-out backwards; }
        @keyframes ms-in { from { opacity: 0; transform: translateY(6px); } }
    }

    .dark .ms-card, .dark .ms-input, .dark .ms-compose textarea { background: var(--gray-900); border-color: var(--gray-800); }
    .dark .ms-note { background: color-mix(in oklab, var(--primary-500) 14%, transparent); color: var(--primary-200); }
    .dark .ms-avatar { background: color-mix(in oklab, var(--primary-500) 20%, transparent); color: var(--primary-200); }
    .dark .ms-bubble { background: rgb(255 255 255 / .07); }
    .dark .ms-line[data-side="guardian"]:not([data-mine]) .ms-bubble { background: color-mix(in oklab, var(--warning-500) 14%, transparent); }
    .dark .ms-head, .dark .ms-compose, .dark .ms-why, .dark .ms-logged { border-color: var(--gray-800); }
    .dark .ms-why { background: rgb(255 255 255 / .03); color: var(--gray-300); }
    .dark .ms-tabs { background: rgb(255 255 255 / .06); }
    .dark .ms-tabs button[aria-pressed="true"] { background: var(--gray-800); color: var(--primary-300); }
    .dark .ms-pill, .dark .ms-count { background: rgb(255 255 255 / .08); color: var(--gray-300); }
    .dark .ms-pill[data-tone="warning"], .dark .ms-count[data-tone="warning"] { background: color-mix(in oklab, var(--warning-500) 18%, transparent); color: var(--warning-300); }
    .dark .ms-pill[data-tone="danger"], .dark .ms-banner, .dark .ms-original { background: color-mix(in oklab, var(--danger-500) 14%, transparent); color: var(--danger-200); }
</style>
