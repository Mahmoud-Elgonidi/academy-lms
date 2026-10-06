{{-- The CRM screens: board columns, lead cards, the lead page (academy colours, Filament variables). --}}
<style>
    .crm-card-base { background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl, .75rem); }
    .crm-funnel { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: .6rem; }
    .crm-stat { padding: .8rem .9rem; display: flex; flex-direction: column; gap: .15rem; }
    .crm-stat small { color: var(--gray-500); font-size: .78rem; }
    .crm-stat strong { font-size: 1.45rem; font-variant-numeric: tabular-nums; line-height: 1.2; }
    .crm-stat[data-key="rate"] strong { color: var(--primary-700); }
    .crm-funnel-title { font-size: .8rem; color: var(--gray-500); margin: 0 0 .35rem; }
    .crm-filters { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem .75rem; margin-top: 1rem; }
    .crm-seg { display: inline-flex; padding: .2rem; border-radius: 999px; background: var(--gray-100); }
    .crm-seg button { padding: .3rem .85rem; border-radius: 999px; font-size: .82rem; font-weight: 600; color: var(--gray-600); }
    .crm-seg button[aria-pressed="true"] { background: white; color: var(--primary-700); box-shadow: 0 1px 2px rgb(0 0 0 / .08); }
    .crm-input { min-width: 0; flex: 1 1 14rem; max-width: 22rem; padding: .45rem .75rem; border-radius: var(--radius-lg, .5rem); border: 1px solid var(--gray-300); background: white; font-size: .875rem; }
    .crm-input:focus { outline: 2px solid var(--primary-500); outline-offset: -1px; }
    .crm-board { display: grid; grid-template-columns: minmax(0, 1fr); gap: .9rem; margin-top: 1rem; align-items: start; }
    @media (min-width: 900px) { .crm-board { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    @media (min-width: 1400px) { .crm-board { grid-template-columns: repeat(5, minmax(0, 1fr)); } }
    .crm-col { display: flex; flex-direction: column; gap: .5rem; padding: .6rem; border-radius: var(--radius-xl, .75rem); background: var(--gray-50); border-top: 3px solid var(--gray-300); min-width: 0; }
    .crm-col[data-stage="new"] { border-top-color: var(--info-500); }
    .crm-col[data-stage="contacted"] { border-top-color: var(--primary-300); }
    .crm-col[data-stage="trial_booked"] { border-top-color: var(--warning-500); }
    .crm-col[data-stage="attended"] { border-top-color: var(--primary-500); }
    .crm-col[data-stage="assessed"] { border-top-color: var(--success-500); }
    .crm-col > header { display: flex; justify-content: space-between; align-items: baseline; padding: .1rem .2rem; font-weight: 700; font-size: .9rem; }
    .crm-col > header small { color: var(--gray-500); font-weight: 600; }
    .crm-cards { display: flex; flex-direction: column; gap: .45rem; }
    .crm-card { display: flex; flex-direction: column; gap: .2rem; padding: .65rem .75rem; color: inherit; text-decoration: none; }
    a.crm-card:hover { border-color: var(--primary-400); }
    .crm-card strong { font-size: .92rem; display: flex; align-items: center; gap: .35rem; }
    .crm-card small { color: var(--gray-500); font-size: .76rem; }
    .crm-card .crm-note { color: var(--gray-700); font-size: .78rem; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
    .crm-dot { width: .45rem; height: .45rem; border-radius: 50%; background: var(--info-500); flex: none; }
    .crm-tags { display: flex; flex-wrap: wrap; gap: .25rem; margin-top: .15rem; }
    .crm-tag { font-size: .7rem; padding: .05rem .45rem; border-radius: 999px; background: var(--gray-100); color: var(--gray-700); white-space: nowrap; }
    .crm-tag[data-kind="owner"] { background: var(--primary-50); color: var(--primary-700); }
    .crm-empty { padding: .9rem .5rem; text-align: center; color: var(--gray-400); font-size: .8rem; }
    .crm-more { text-align: center; color: var(--gray-500); font-size: .78rem; }
    .crm-recent { display: grid; grid-template-columns: minmax(0, 1fr); gap: .9rem; margin-top: 1.25rem; }
    @media (min-width: 900px) { .crm-recent { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .crm-recent section { padding: .9rem 1rem; }
    .crm-recent h3 { font-weight: 700; font-size: .95rem; margin-bottom: .4rem; }
    .crm-recent a { display: flex; justify-content: space-between; gap: .75rem; padding: .35rem 0; border-top: 1px solid var(--gray-100); color: inherit; font-size: .86rem; }
    .crm-recent a small { color: var(--gray-500); }
    @media (max-width: 760px) { .crm-funnel { grid-template-columns: repeat(2, minmax(0, 1fr)); } .crm-stat[data-key="rate"] { grid-column: 1 / -1; } }

    /* The lead page */
    .crm-steps { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: .3rem; }
    .crm-step { display: flex; flex-direction: column; gap: .35rem; font-size: .76rem; color: var(--gray-500); }
    .crm-step::before { content: ""; height: .35rem; border-radius: 999px; background: var(--gray-200); }
    .crm-step[data-on="done"]::before { background: var(--primary-400); }
    .crm-step[data-on="now"]::before { background: var(--primary-600); }
    .crm-step[data-on="now"] { color: var(--primary-700); font-weight: 700; }
    .crm-banner { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem .9rem; padding: .7rem 1rem; border-radius: var(--radius-xl, .75rem); font-size: .9rem; }
    .crm-banner[data-kind="won"] { background: var(--success-50); color: var(--success-800); }
    .crm-banner[data-kind="lost"] { background: var(--danger-50); color: var(--danger-800); }
    .crm-banner a { font-weight: 700; text-decoration: underline; }
    .crm-lead { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1rem; align-items: start; margin-top: 1rem; }
    @media (min-width: 1100px) { .crm-lead { grid-template-columns: minmax(0, 1fr) 22rem; } .crm-lead > aside { order: 2; } }
    .crm-box { padding: 1rem; display: flex; flex-direction: column; gap: .7rem; }
    .crm-box h3 { font-weight: 700; font-size: .95rem; }
    .crm-facts { display: flex; flex-direction: column; gap: .55rem; font-size: .88rem; }
    .crm-facts div { display: flex; flex-direction: column; gap: .05rem; }
    .crm-facts small { color: var(--gray-500); font-size: .75rem; }
    .crm-phone { font-variant-numeric: tabular-nums; font-weight: 700; font-size: 1rem; }
    .crm-contact-actions { display: flex; flex-wrap: wrap; gap: .4rem; }
    .crm-trial { display: flex; flex-direction: column; gap: .5rem; padding: .8rem .9rem; border-radius: var(--radius-lg, .5rem); border: 1px solid var(--gray-200); }
    .crm-trial header { display: flex; flex-wrap: wrap; justify-content: space-between; gap: .4rem; align-items: center; }
    .crm-trial header strong { font-size: .92rem; }
    .crm-trial small { color: var(--gray-500); font-size: .8rem; }
    .crm-pill { white-space: nowrap; padding: .1rem .55rem; border-radius: 999px; font-size: .74rem; font-weight: 600; background: var(--gray-100); color: var(--gray-700); }
    .crm-pill[data-s="booked"] { background: var(--warning-50); color: var(--warning-800); }
    .crm-pill[data-s="attended"] { background: var(--primary-50); color: var(--primary-700); }
    .crm-pill[data-s="missed"], .crm-pill[data-s="cancelled"] { background: var(--danger-50); color: var(--danger-700); }
    .crm-assess { display: grid; grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr)); gap: .5rem .9rem; padding: .65rem .75rem; border-radius: var(--radius-lg, .5rem); background: var(--gray-50); font-size: .86rem; }
    .crm-assess > div { display: flex; flex-direction: column; gap: .1rem; }
    .crm-assess small { color: var(--gray-500); font-size: .74rem; }
    .crm-assess p { grid-column: 1 / -1; white-space: pre-line; color: var(--gray-700); }
    .crm-log { display: flex; flex-direction: column; gap: .5rem; }
    .crm-log textarea { width: 100%; min-height: 4.2rem; padding: .55rem .75rem; border-radius: var(--radius-lg, .5rem); border: 1px solid var(--gray-300); background: white; font-size: .875rem; resize: vertical; }
    .crm-log-row { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: .5rem; }
    .crm-timeline { display: flex; flex-direction: column; }
    .crm-event { display: grid; grid-template-columns: 1.9rem minmax(0, 1fr); gap: .6rem; padding: .55rem 0; border-top: 1px solid var(--gray-100); }
    .crm-event-icon { width: 1.9rem; height: 1.9rem; border-radius: 50%; display: grid; place-items: center; background: var(--gray-100); color: var(--gray-600); }
    .crm-event-icon svg { width: 1rem; height: 1rem; }
    .crm-event[data-kind="call"] .crm-event-icon, .crm-event[data-kind="whatsapp"] .crm-event-icon { background: var(--primary-50); color: var(--primary-700); }
    .crm-event[data-kind^="trial"] .crm-event-icon, .crm-event[data-kind="assessed"] .crm-event-icon { background: var(--warning-50); color: var(--warning-700); }
    .crm-event[data-kind="won"] .crm-event-icon { background: var(--success-50); color: var(--success-700); }
    .crm-event[data-kind="lost"] .crm-event-icon { background: var(--danger-50); color: var(--danger-700); }
    .crm-event strong { font-size: .88rem; font-weight: 600; }
    .crm-event p { font-size: .86rem; color: var(--gray-700); white-space: pre-line; margin-top: .1rem; }
    .crm-event small { color: var(--gray-500); font-size: .74rem; }

    .dark .crm-card-base, .dark .crm-input, .dark .crm-log textarea { background: var(--gray-900); border-color: var(--gray-800); }
    .dark .crm-col { background: rgb(255 255 255 / .03); }
    .dark .crm-seg { background: rgb(255 255 255 / .06); }
    .dark .crm-seg button[aria-pressed="true"] { background: var(--gray-800); color: var(--primary-300); }
    .dark .crm-tag { background: rgb(255 255 255 / .07); color: var(--gray-300); }
    .dark .crm-tag[data-kind="owner"] { background: color-mix(in oklab, var(--primary-500) 16%, transparent); color: var(--primary-300); }
    .dark .crm-card .crm-note, .dark .crm-assess p, .dark .crm-event p { color: var(--gray-300); }
    .dark .crm-assess { background: rgb(255 255 255 / .04); }
    .dark .crm-trial { border-color: var(--gray-800); }
    .dark .crm-recent a, .dark .crm-event { border-top-color: var(--gray-800); }
    .dark .crm-stat[data-key="rate"] strong { color: var(--primary-400); }
    .dark .crm-pill { background: rgb(255 255 255 / .07); color: var(--gray-300); }
    .dark .crm-pill[data-s="booked"] { background: color-mix(in oklab, var(--warning-500) 16%, transparent); color: var(--warning-300); }
    .dark .crm-pill[data-s="attended"] { background: color-mix(in oklab, var(--primary-500) 16%, transparent); color: var(--primary-300); }
    .dark .crm-pill[data-s="missed"], .dark .crm-pill[data-s="cancelled"] { background: color-mix(in oklab, var(--danger-500) 16%, transparent); color: var(--danger-300); }
    .dark .crm-banner[data-kind="won"] { background: color-mix(in oklab, var(--success-500) 14%, transparent); color: var(--success-200); }
    .dark .crm-banner[data-kind="lost"] { background: color-mix(in oklab, var(--danger-500) 14%, transparent); color: var(--danger-200); }
    .dark .crm-step::before { background: var(--gray-800); }
    .dark .crm-step[data-on="done"]::before { background: var(--primary-700); }
    .dark .crm-step[data-on="now"]::before { background: var(--primary-500); }
    .dark .crm-step[data-on="now"] { color: var(--primary-300); }
    .dark .crm-event-icon { background: rgb(255 255 255 / .06); color: var(--gray-300); }
</style>
