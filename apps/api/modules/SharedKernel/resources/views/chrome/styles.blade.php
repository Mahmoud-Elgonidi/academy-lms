{{--
    The panels' frame with some life, for every panel and in the academy's
    colours (Filament's colour variables): a side menu that is a card of its
    own (a greeting, icons in chips, the open page as a pill that slides to the
    next one), a see-through top bar that takes a shadow once the page
    scrolls, the page heading's brand mark, livelier buttons and tables, and a
    sign-in page with soft moving colour. Page changes are View Transitions
    (chrome/script): the old page lifts away, the new one rises, the pill
    slides. Motion is transform and opacity only, and none at all for people
    who ask their device for less; a student's stage shell (People) is printed
    later and keeps the last word on its own look.
--}}
<style>
    :root {
        --lms-dir: 1;
        --lms-pill: linear-gradient(135deg, var(--primary-700), var(--primary-500));
        --lms-pill-glow: 0 8px 18px -8px color-mix(in oklab, var(--primary-600) 70%, transparent);
        --lms-line: color-mix(in oklab, var(--gray-950) 7%, transparent);
        --lms-out: cubic-bezier(.22, 1, .36, 1);
    }
    [dir="rtl"] { --lms-dir: -1; }

    /* ── A little colour behind the pages (low specificity: a stage shell's own background wins) ── */
    .fi-body:where(.fi-body-has-navigation) { background-image: radial-gradient(70rem 26rem at 100% -6rem, color-mix(in oklab, var(--primary-100) 55%, transparent), transparent 70%); background-repeat: no-repeat; }
    .dark .fi-body:where(.fi-body-has-navigation) { background-image: radial-gradient(70rem 26rem at 100% -6rem, color-mix(in oklab, var(--primary-500) 10%, transparent), transparent 70%); }

    /* ── Side menu ──────────────────────────────────────────────────── */
    .fi-sidebar-nav { gap: 1.25rem; scrollbar-width: thin; scrollbar-color: color-mix(in oklab, var(--gray-400) 45%, transparent) transparent; }
    .fi-sidebar-nav-groups { row-gap: 1.25rem; }
    @media (min-width: 1024px) {
        /* A card of its own beside the page; what scrolls stays inside its round corners. */
        .fi-sidebar-nav {
            margin: .75rem; margin-inline-end: .25rem; padding: 1rem 1rem 1.25rem;
            border-radius: calc(var(--radius-xl) + .4rem);
            background:
                radial-gradient(18rem 11rem at 100% 0, color-mix(in oklab, var(--primary-200) 38%, transparent), transparent 70%),
                linear-gradient(180deg, color-mix(in oklab, var(--primary-50) 75%, white), white 38%);
            box-shadow: 0 0 0 1px var(--lms-line), 0 1px 2px rgb(0 0 0 / .04), 0 18px 36px -24px rgb(0 0 0 / .22);
        }
        /* Folded to icons: a slim card, the chips centred in it. */
        .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-nav { margin-inline: .4rem; padding-inline: 0; scrollbar-gutter: auto; }
        .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-nav-groups { width: auto; margin-inline: 0; align-items: center; }
        .fi-sidebar:not(.fi-sidebar-open) .fi-sidebar-item-btn { padding: .3rem; }
        .dark .fi-sidebar-nav {
            background:
                radial-gradient(18rem 11rem at 100% 0, color-mix(in oklab, var(--primary-500) 16%, transparent), transparent 70%),
                linear-gradient(180deg, color-mix(in oklab, var(--primary-950) 35%, var(--gray-900)), var(--gray-900) 38%);
            box-shadow: 0 0 0 1px rgb(255 255 255 / .07), 0 18px 36px -24px rgb(0 0 0 / .6);
        }
    }
    @media (max-width: 1023.98px) {
        /* On phones the menu is a drawer: the same colours, a softer slide, the page behind it blurred. */
        .fi-sidebar {
            background:
                radial-gradient(18rem 11rem at 100% 0, color-mix(in oklab, var(--primary-200) 38%, transparent), transparent 70%),
                linear-gradient(180deg, color-mix(in oklab, var(--primary-50) 75%, white), white 38%);
            transition: translate .42s var(--lms-out), box-shadow .3s;
        }
        .dark .fi-sidebar { background: linear-gradient(180deg, color-mix(in oklab, var(--primary-950) 35%, var(--gray-900)), var(--gray-900) 38%); }
        .fi-sidebar.fi-sidebar-open { box-shadow: 0 0 60px -10px rgb(0 0 0 / .35); }
        .fi-sidebar-close-overlay { backdrop-filter: blur(3px); -webkit-backdrop-filter: blur(3px); }
    }

    /* The greeting at the top (chrome/greeting). */
    .lms-hello { display: flex; align-items: center; gap: .7rem; margin-inline: -.5rem; padding: .6rem .7rem; border-radius: calc(var(--radius-xl) + .1rem); background: color-mix(in oklab, white 80%, transparent); box-shadow: 0 0 0 1px var(--lms-line), 0 8px 18px -14px rgb(0 0 0 / .3); }
    .lms-hello-sky { position: relative; flex: none; display: grid; place-items: center; width: 2.5rem; height: 2.5rem; border-radius: 999px; color: var(--warning-800); background: radial-gradient(circle at 32% 30%, var(--warning-100), var(--warning-300) 70%); box-shadow: 0 6px 14px -6px color-mix(in oklab, var(--warning-500) 70%, transparent); }
    .lms-hello-sky svg { width: 1.4rem; height: 1.4rem; }
    .lms-hello[data-sky="evening"] .lms-hello-sky { color: var(--warning-900); background: radial-gradient(circle at 32% 30%, var(--warning-200), var(--danger-300) 75%); box-shadow: 0 6px 14px -6px color-mix(in oklab, var(--danger-400) 70%, transparent); }
    .lms-hello[data-sky="night"] .lms-hello-sky { color: white; background: radial-gradient(circle at 32% 30%, var(--info-400), var(--info-700) 75%); box-shadow: 0 6px 14px -6px color-mix(in oklab, var(--info-600) 70%, transparent); }
    .lms-hello-text { min-width: 0; }
    .lms-hello strong { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: .92rem; font-weight: 700; color: var(--gray-900); font-family: var(--lms-font-heading, inherit); }
    .lms-hello small { display: block; font-size: .75rem; color: var(--gray-500); }
    .dark .lms-hello { background: rgb(255 255 255 / .04); box-shadow: 0 0 0 1px rgb(255 255 255 / .07); }
    .dark .lms-hello strong { color: white; }
    .dark .lms-hello small { color: var(--gray-400); }

    /* Group names: quiet, with a line to the fold arrow, which turns softly. */
    .fi-sidebar-group-btn { padding-block: .2rem; padding-inline: .7rem .35rem; column-gap: .5rem; }
    .fi-sidebar-group-label { display: flex; align-items: center; gap: .6rem; font-size: .75rem; font-weight: 700; color: var(--gray-400); }
    .fi-sidebar-group-label::after { content: ''; flex: 1; height: 1px; background: linear-gradient(90deg, transparent, var(--gray-200) 25%, var(--gray-200) 75%, transparent); }
    .dark .fi-sidebar-group-label::after { background: linear-gradient(90deg, transparent, rgb(255 255 255 / .1) 25%, rgb(255 255 255 / .1) 75%, transparent); }
    .fi-sidebar-group-collapse-btn { transition: rotate .35s var(--lms-spring); }

    /* Items: the icon sits in a chip; hovering tints the row and tips the icon. */
    .fi-sidebar-item-btn { column-gap: .7rem; padding: .32rem .45rem; border-radius: calc(var(--radius-lg) + .25rem); transition: background-color .2s, box-shadow .3s, color .2s; }
    .fi-sidebar-item-btn > .fi-sidebar-item-icon {
        box-sizing: content-box; width: 1.2rem; height: 1.2rem; padding: .44rem; border-radius: calc(var(--radius-md) + .2rem);
        color: var(--gray-500); background: color-mix(in oklab, var(--gray-500) 9%, transparent);
        transition: transform .4s var(--lms-spring), background-color .2s, color .2s, box-shadow .2s;
    }
    .fi-sidebar-item-label { transition: transform .3s var(--lms-out), color .2s; }
    .fi-sidebar-item.fi-sidebar-item-has-url > .fi-sidebar-item-btn:hover,
    .fi-sidebar-item.fi-sidebar-item-has-url > .fi-sidebar-item-btn:focus-visible { background-color: color-mix(in oklab, var(--primary-500) 8%, transparent); }
    .fi-sidebar-item-btn:hover > .fi-sidebar-item-icon, .fi-sidebar-item-btn:focus-visible > .fi-sidebar-item-icon { color: var(--primary-600); background: color-mix(in oklab, var(--primary-500) 16%, transparent); }
    .fi-sidebar-item-btn:hover > .fi-sidebar-item-label { color: var(--gray-950); }
    .dark .fi-sidebar-item-btn > .fi-sidebar-item-icon { color: var(--gray-400); background: rgb(255 255 255 / .06); }
    .dark .fi-sidebar-item-btn:hover > .fi-sidebar-item-icon { color: var(--primary-300); background: color-mix(in oklab, var(--primary-500) 22%, transparent); }
    .dark .fi-sidebar-item-btn:hover > .fi-sidebar-item-label { color: white; }

    /* The open page: a pill in the academy's colour, which shines once as you arrive. */
    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn { overflow: hidden; background: var(--lms-pill); box-shadow: var(--lms-pill-glow); }
    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn > .fi-sidebar-item-label { color: white; font-weight: 600; }
    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn > .fi-icon { color: white; background: rgb(255 255 255 / .2); box-shadow: inset 0 0 0 1px rgb(255 255 255 / .22); }
    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn::after { content: ''; position: absolute; inset: 0; pointer-events: none; background: linear-gradient(100deg, transparent 35%, rgb(255 255 255 / .38) 50%, transparent 65%); transform: translateX(calc(130% * var(--lms-dir))); }
    .dark .fi-sidebar-item.fi-active > .fi-sidebar-item-btn > .fi-sidebar-item-label, .dark .fi-sidebar-item.fi-active > .fi-sidebar-item-btn > .fi-icon { color: white; }

    /* Clicked, on its way: the row answers at once, before the page arrives (chrome/script). */
    .fi-sidebar-item-btn[data-lms-going] { background-color: color-mix(in oklab, var(--primary-500) 12%, transparent); }

    /* ── Top bar ────────────────────────────────────────────────────── */
    .fi-topbar-ctn::before { content: ''; position: absolute; z-index: 1; inset-inline: 0; top: 0; height: 3px; pointer-events: none; background: linear-gradient(90deg, var(--primary-300), var(--primary-600) 50%, var(--primary-300)); }
    .fi-topbar { background-color: color-mix(in oklab, white 80%, transparent); backdrop-filter: saturate(1.6) blur(14px); -webkit-backdrop-filter: saturate(1.6) blur(14px); }
    .dark .fi-topbar { background-color: color-mix(in oklab, var(--gray-900) 78%, transparent); }
    .fi-topbar-ctn::after { content: ''; position: absolute; inset-inline: 0; top: 100%; height: 16px; pointer-events: none; opacity: 0; background: linear-gradient(to bottom, rgb(0 0 0 / .07), transparent); }
    /* The academy's name in its colour; the padding keeps Arabic letters whole inside the clipped background. */
    div.fi-logo { padding-block: .3em; margin-block: -.3em; background-image: linear-gradient(135deg, var(--gray-950) 35%, var(--primary-700)); -webkit-background-clip: text; background-clip: text; color: transparent; }
    .dark div.fi-logo { background-image: linear-gradient(135deg, white 35%, var(--primary-300)); }
    .fi-user-avatar { box-shadow: 0 0 0 2px white, 0 0 0 4px color-mix(in oklab, var(--primary-500) 45%, transparent); transition: box-shadow .25s; }
    .fi-user-menu-trigger:hover .fi-user-avatar { box-shadow: 0 0 0 2px white, 0 0 0 4px var(--primary-500); }
    .dark .fi-user-avatar { box-shadow: 0 0 0 2px var(--gray-900), 0 0 0 4px color-mix(in oklab, var(--primary-400) 50%, transparent); }

    /* Menus in the top bar (teachers', families'): the open page gets a tint and a bar under it. */
    .fi-topbar-item-btn { position: relative; transition: background-color .2s, color .2s; }
    .fi-topbar-item.fi-active .fi-topbar-item-btn { background-color: color-mix(in oklab, var(--primary-500) 10%, transparent); }
    .fi-topbar-item.fi-active .fi-topbar-item-label { color: var(--primary-700); font-weight: 600; }
    .fi-topbar-item.fi-active .fi-topbar-item-btn::after { content: ''; position: absolute; inset-inline: 32%; bottom: .2rem; height: 3px; border-radius: 3px; background: var(--lms-pill); }

    /* ── Page heading: the academy's mark beside it ─────────────────── */
    .fi-header-heading::before { content: ''; display: inline-block; width: .32rem; height: .85em; margin-inline-end: .45rem; vertical-align: -.06em; border-radius: 999px; background: linear-gradient(to bottom, var(--primary-400), var(--primary-700)); }

    /* ── Buttons, tables ────────────────────────────────────────────── */
    .fi-btn.fi-color:not(.fi-outlined):not(.fi-link) { background-image: linear-gradient(180deg, rgb(255 255 255 / .13), transparent 60%); --tw-shadow: 0 6px 14px -8px color-mix(in oklab, var(--color-600, var(--primary-600)) 85%, transparent); }
    .fi-btn.fi-color:not(.fi-outlined):not(.fi-link):not(:has(.fi-btn-badge-ctn)) { overflow: hidden; }
    .fi-btn.fi-color:not(.fi-outlined):not(.fi-link):not(:has(.fi-btn-badge-ctn))::after { content: ''; position: absolute; inset: 0; pointer-events: none; background: linear-gradient(100deg, transparent 35%, rgb(255 255 255 / .28) 50%, transparent 65%); transform: translateX(calc(130% * var(--lms-dir))); }
    .fi-ta-row { transition: background-color .15s; }

    /* ── Sign-in ────────────────────────────────────────────────────── */
    .fi-simple-layout { position: relative; isolation: isolate; overflow: hidden; }
    .fi-simple-layout::before, .fi-simple-layout::after { content: ''; position: absolute; z-index: -1; border-radius: 50%; pointer-events: none; }
    .fi-simple-layout::before { width: 52rem; height: 52rem; top: -24rem; inset-inline-end: -16rem; background: radial-gradient(circle, color-mix(in oklab, var(--primary-300) 50%, transparent), transparent 65%); }
    .fi-simple-layout::after { width: 44rem; height: 44rem; bottom: -22rem; inset-inline-start: -14rem; background: radial-gradient(circle, color-mix(in oklab, var(--warning-200) 60%, transparent), transparent 65%); }
    .fi-simple-main-ctn { position: relative; }
    .fi-simple-main-ctn::before { content: ''; position: absolute; z-index: -1; inset: 0; pointer-events: none; background-image: radial-gradient(color-mix(in oklab, var(--gray-500) 28%, transparent) 1px, transparent 1.4px); background-size: 22px 22px; -webkit-mask-image: radial-gradient(ellipse 60% 55% at 50% 50%, black, transparent); mask-image: radial-gradient(ellipse 60% 55% at 50% 50%, black, transparent); }
    .fi-simple-main { position: relative; background-color: color-mix(in oklab, white 86%, transparent); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); box-shadow: 0 0 0 1px var(--lms-line), 0 2px 4px rgb(0 0 0 / .03), 0 30px 60px -32px rgb(0 0 0 / .3); }
    .fi-simple-main::before { content: ''; position: absolute; inset-inline: 22%; top: 0; height: 4px; border-radius: 0 0 4px 4px; background: var(--lms-pill); }
    .fi-simple-header div.fi-logo { margin-bottom: calc(1rem - .3em); font-size: 1.4rem; font-weight: 800; }
    .dark .fi-simple-main { background-color: color-mix(in oklab, var(--gray-900) 85%, transparent); box-shadow: 0 0 0 1px rgb(255 255 255 / .08), 0 30px 60px -32px rgb(0 0 0 / .7); }
    .dark .fi-simple-layout::before { background: radial-gradient(circle, color-mix(in oklab, var(--primary-500) 22%, transparent), transparent 65%); }
    .dark .fi-simple-layout::after { background: radial-gradient(circle, color-mix(in oklab, var(--warning-500) 12%, transparent), transparent 65%); }

    /* ── Progress bar on page changes ───────────────────────────────── */
    #nprogress .bar { background: linear-gradient(90deg, var(--primary-300), var(--primary-600)) !important; box-shadow: 0 0 10px color-mix(in oklab, var(--primary-500) 60%, transparent); }
    #nprogress .peg { box-shadow: 0 0 12px var(--primary-500), 0 0 4px var(--primary-500) !important; }

    @keyframes lms-side-in { from { opacity: 0; transform: translateX(calc(-16px * var(--lms-dir))); } }
    @keyframes lms-sheen { from { transform: translateX(calc(-130% * var(--lms-dir))); } }
    @keyframes lms-sun { from { opacity: 0; transform: rotate(-120deg) scale(.4); } }
    @keyframes lms-going { 50% { transform: scale(.82); opacity: .55; } }
    @keyframes lms-drift { to { transform: translate(calc(-4rem * var(--lms-dir)), 3rem) scale(1.12); } }
    @keyframes lms-card-in { from { opacity: 0; transform: translateY(22px) scale(.97); } }
    @keyframes lms-row-in { from { opacity: 0; transform: translateY(8px); } }
    @keyframes lms-shade { to { opacity: 1; } }
    @keyframes lms-bob { 50% { transform: translateY(-6px); } }
    @keyframes lms-page-out { to { opacity: 0; transform: translateY(-8px) scale(.99); } }

    @media (prefers-reduced-motion: no-preference) {
        /* The menu comes in item by item: on the first page only (chrome/script marks the rest), and each time the phone drawer opens. */
        .fi-sidebar-group:nth-child(2) { --g: 1; } .fi-sidebar-group:nth-child(3) { --g: 2; } .fi-sidebar-group:nth-child(4) { --g: 3; }
        .fi-sidebar-group:nth-child(5) { --g: 4; } .fi-sidebar-group:nth-child(6) { --g: 5; } .fi-sidebar-group:nth-child(n + 7) { --g: 6; }
        .fi-sidebar-item:nth-child(2) { --j: 1; } .fi-sidebar-item:nth-child(3) { --j: 2; } .fi-sidebar-item:nth-child(4) { --j: 3; }
        .fi-sidebar-item:nth-child(5) { --j: 4; } .fi-sidebar-item:nth-child(6) { --j: 5; } .fi-sidebar-item:nth-child(n + 7) { --j: 6; }
        html:not(.lms-settled) :is(.fi-sidebar-item, .fi-sidebar-group-btn, .lms-hello) { animation: lms-side-in .55s var(--lms-out) backwards; animation-delay: calc(var(--g, 0) * 90ms + var(--j, 0) * 40ms + 60ms); }
        html:not(.lms-settled) .lms-hello { animation-delay: 0ms; }
        html:not(.lms-settled) .lms-hello-sky { animation: lms-sun .9s var(--lms-spring) .25s backwards; }
        html:not(.lms-settled) .fi-sidebar-item-badge-ctn { animation: lms-pop .45s var(--lms-spring) backwards; animation-delay: calc(var(--g, 0) * 90ms + var(--j, 0) * 40ms + 380ms); }
        @media (max-width: 1023.98px) {
            .fi-sidebar.fi-sidebar-open :is(.fi-sidebar-item, .fi-sidebar-group-btn, .lms-hello) { animation: lms-side-in .5s var(--lms-out) backwards; animation-delay: calc(var(--g, 0) * 70ms + var(--j, 0) * 35ms + 80ms); }
            .fi-sidebar.fi-sidebar-open .lms-hello { animation-delay: 40ms; }
        }

        .fi-sidebar-item.fi-active > .fi-sidebar-item-btn::after { animation: lms-sheen 1.1s var(--lms-out) .3s backwards; }
        :is(.fi-sidebar-item-btn[data-lms-going] > .fi-sidebar-item-icon, .fi-topbar-item-btn[data-lms-going] > .fi-icon, .lms-tab[data-lms-going] svg) { animation: lms-going .8s ease-in-out infinite; }
        @media (hover: hover) {
            .fi-sidebar-item-btn:hover > .fi-sidebar-item-icon { transform: rotate(calc(-9deg * var(--lms-dir))) scale(1.1); }
            .fi-sidebar-item-btn:hover > .fi-sidebar-item-label { transform: translateX(calc(3px * var(--lms-dir))); }
            .fi-sidebar-item.fi-active > .fi-sidebar-item-btn:hover > .fi-sidebar-item-label { transform: none; }
            .fi-btn.fi-color:not(.fi-outlined):not(.fi-link):hover { transform: translateY(-1px); }
            .fi-btn.fi-color:not(.fi-outlined):not(.fi-link):active { transform: scale(.97); }
            .fi-btn.fi-color:not(.fi-outlined):not(.fi-link):not(:has(.fi-btn-badge-ctn)):hover::after { animation: lms-sheen .75s var(--lms-out); }
        }
        .fi-topbar-item.fi-active .fi-topbar-item-btn::after { animation: lms-grow-x .5s var(--lms-spring) .1s backwards; }
        .fi-header-heading::before { transform-origin: center bottom; animation: lms-grow-y .6s var(--lms-spring) .15s backwards; }

        /* The top bar takes its shadow as the page scrolls under it. */
        @supports (animation-timeline: scroll()) {
            .fi-topbar-ctn::after { animation: lms-shade linear both; animation-timeline: scroll(root); animation-range: 0 140px; }
        }

        /* Table rows come in one after another; an empty list breathes. */
        .fi-ta-row { animation: lms-row-in .45s var(--lms-out) backwards; }
        .fi-ta-row:nth-child(2) { animation-delay: 30ms; } .fi-ta-row:nth-child(3) { animation-delay: 60ms; } .fi-ta-row:nth-child(4) { animation-delay: 90ms; }
        .fi-ta-row:nth-child(5) { animation-delay: 120ms; } .fi-ta-row:nth-child(6) { animation-delay: 150ms; } .fi-ta-row:nth-child(7) { animation-delay: 175ms; }
        .fi-ta-row:nth-child(8) { animation-delay: 200ms; } .fi-ta-row:nth-child(9) { animation-delay: 220ms; } .fi-ta-row:nth-child(n + 10) { animation-delay: 240ms; }
        .fi-empty-state-icon-bg { animation: lms-bob 3s ease-in-out .6s 3; }

        /* Sign-in: the colours drift, the card rises, its parts follow. */
        .fi-simple-layout::before { animation: lms-drift 16s ease-in-out infinite alternate; }
        .fi-simple-layout::after { animation: lms-drift 19s ease-in-out -7s infinite alternate-reverse; }
        .fi-simple-main { animation: lms-card-in .7s var(--lms-out) backwards; }
        .fi-simple-header > * { animation: lms-rise .6s var(--lms-out) backwards; }
        .fi-simple-header div.fi-logo { animation: lms-pop .7s var(--lms-spring) .2s backwards; }
        .fi-simple-header > :nth-child(2) { animation-delay: .3s; }
        .fi-simple-page-content > :not(.fi-simple-header) { animation: lms-rise .6s var(--lms-out) .4s backwards; }

        /* Page changes (View Transitions, chrome/script): the old page lifts away, then the new one fades in as it
           rises (motion/styles); the pill of the open page slides from the old item to the new one. */
        ::view-transition-group(lms-page) { animation: none; }
        ::view-transition-image-pair(lms-page) { isolation: auto; }
        ::view-transition-old(lms-page) { animation: lms-page-out .14s cubic-bezier(.4, 0, 1, 1) both; mix-blend-mode: normal; }
        ::view-transition-new(lms-page) { animation: lms-fade .3s ease-out .1s backwards; mix-blend-mode: normal; }
        ::view-transition-group(lms-nav-side), ::view-transition-group(lms-nav-top), ::view-transition-group(lms-nav-tab) { animation-duration: .45s; animation-timing-function: cubic-bezier(.3, 1.2, .5, 1); }
        ::view-transition-old(lms-nav-side), ::view-transition-new(lms-nav-side),
        ::view-transition-old(lms-nav-top), ::view-transition-new(lms-nav-top),
        ::view-transition-old(lms-nav-tab), ::view-transition-new(lms-nav-tab) { animation-duration: .3s; }
    }
</style>
