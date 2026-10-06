{{--
    Motion for every panel: quick and calm, transform and opacity only (no
    layout work), once when something appears, and none at all for people who
    ask their device for less motion. Pages slide in, cards follow one after
    another, bars fill, numbers count up, buttons give under the finger.
    Utilities for screens: .lms-stagger (its children come in one by one),
    .lms-lift (a card you can press), .lms-bar-fill (the filled part of a bar),
    data-count (see motion/script).
--}}
<style>
    :root {
        --lms-ease: cubic-bezier(.2, .7, .2, 1);
        --lms-spring: cubic-bezier(.34, 1.56, .64, 1);
        --livewire-progress-bar-color: var(--primary-500);
    }
    #nprogress .bar { height: 3px !important; border-radius: 0 3px 3px 0; }

    @keyframes lms-page-in { from { opacity: 0; transform: translateY(8px); } }
    @keyframes lms-rise { from { opacity: 0; transform: translateY(14px); } }
    @keyframes lms-fade { from { opacity: 0; } }
    @keyframes lms-pop { from { opacity: 0; transform: scale(.6); } }
    @keyframes lms-grow-x { from { transform: scaleX(0); } }
    @keyframes lms-grow-y { from { transform: scaleY(0); } }
    @keyframes lms-shine { 0% { transform: translateX(-150%); } 55%, 100% { transform: translateX(350%); } }
    @keyframes lms-confetti {
        0% { transform: translate(0, 0) rotate(0); opacity: 1; }
        55% { transform: translate(var(--dx), var(--dy)) rotate(var(--rot)); opacity: 1; }
        100% { transform: translate(calc(var(--dx) * 1.15), calc(var(--dy) + 140px)) rotate(calc(var(--rot) * 2)); opacity: 0; }
    }
    @keyframes lms-cheer { 0% { opacity: 0; transform: translate(-50%, 10px) scale(.7); } 15%, 80% { opacity: 1; transform: translate(-50%, -30px) scale(1); } 100% { opacity: 0; transform: translate(-50%, -50px) scale(.95); } }

    @media (prefers-reduced-motion: no-preference) {
        /* A page comes in; the top bar stays put. */
        .fi-main > .fi-page { animation: lms-page-in .32s var(--lms-ease) backwards; }

        /* Cards one after another: the dashboard's widgets (also when they load late) and any .lms-stagger list. */
        .fi-wi-widget, .lms-stagger > * { animation: lms-rise .5s var(--lms-ease) backwards; }
        .fi-grid-col:nth-child(2) > * > .fi-wi-widget, .lms-stagger > :nth-child(2) { animation-delay: 60ms; }
        .fi-grid-col:nth-child(3) > * > .fi-wi-widget, .lms-stagger > :nth-child(3) { animation-delay: 120ms; }
        .fi-grid-col:nth-child(4) > * > .fi-wi-widget, .lms-stagger > :nth-child(4) { animation-delay: 180ms; }
        .lms-stagger > :nth-child(5) { animation-delay: 240ms; }
        .lms-stagger > :nth-child(6) { animation-delay: 290ms; }
        .lms-stagger > :nth-child(7) { animation-delay: 340ms; }
        .lms-stagger > :nth-child(n + 8) { animation-delay: 380ms; }

        /* What you press gives a little; cards you can open lift. */
        .fi-btn, .fi-icon-btn, .fi-topbar-item-btn, .fi-tabs-item, .lms-tab { transition: transform .15s var(--lms-ease), background-color .2s, color .2s, box-shadow .2s; }
        .fi-btn:active, .fi-icon-btn:active, .fi-tabs-item:active, .lms-tab:active, .lms-lift:active { transform: scale(.97); }
        .lms-lift { transition: transform .2s var(--lms-ease), box-shadow .2s var(--lms-ease), border-color .2s; }
        @media (hover: hover) {
            .lms-lift:hover { transform: translateY(-2px); box-shadow: 0 12px 24px -14px rgb(0 0 0 / .3); }
        }

        /* Bars fill from where they start (the right in Arabic). */
        .lms-bar-fill { transform-origin: left center; animation: lms-grow-x 1s var(--lms-ease) .2s backwards; }
        [dir="rtl"] .lms-bar-fill { transform-origin: right center; }
    }

    /* A cheer: confetti and a word, for something new (motion/script). */
    .lms-confetti { position: fixed; z-index: 60; width: 0; height: 0; pointer-events: none; }
    .lms-confetti i { position: absolute; width: 8px; height: 12px; margin: -6px 0 0 -4px; border-radius: 2px; animation: lms-confetti 1.5s cubic-bezier(.15, .6, .35, 1) both; }
    .lms-confetti i:nth-child(3n) { width: 10px; height: 10px; border-radius: 50%; }
    .lms-confetti b { position: absolute; left: 0; top: 0; white-space: nowrap; padding: .35rem .9rem; border-radius: 999px; background: var(--gray-950); color: white; font-size: .95rem; font-weight: 800; box-shadow: 0 10px 25px -10px rgb(0 0 0 / .45); animation: lms-cheer 2.2s var(--lms-ease) both; }
</style>
