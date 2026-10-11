{{--
    The islands world (the approved design, docs/architecture/02, section 18): a picture with its
    own palette (sea, sand, navy outlines, 3D buttons), the same for every academy like the games
    themselves, so it does not take the academy's brand colours. Motion is transform and opacity
    (and the island's circle reveal), all inside prefers-reduced-motion: no-preference.
--}}
<style>
    .gw-kids { --gw-ink: #13294B; --gw-sun: #FFD43B; color: var(--gw-ink); font-family: var(--lms-font-heading, system-ui, sans-serif); }
    .gw-kids button { font: inherit; color: inherit; }
    .gw-kids [x-cloak], .gw-hatch [x-cloak] { display: none !important; }
    .gw-3d { cursor: pointer; transition: transform .08s; }
    .gw-3d:active { transform: translateY(3px); box-shadow: 0 1px 0 var(--gw-ink) !important; }
    .gw-card3d { transition: transform .1s; }
    .gw-card3d:active { transform: translateY(4px); box-shadow: 0 1px 0 var(--gw-ink) !important; }

    /* The map: a 390-wide picture scaled to the page (--gw-s), edge to edge on phones. */
    .fi-page:has(.gw-kids) .fi-page-header-main-ctn { padding-top: 0; }
    .gw-map { position: relative; width: 100%; max-width: 520px; margin-inline: auto; height: calc(var(--gw-h, 760px) * var(--gw-s, 1)); border-radius: 28px; overflow: hidden; background: #2F9BE3; box-shadow: 0 6px 0 #1C7ED6; }
    @media (max-width: 639.98px) { .gw-map { width: 100vw; max-width: none; margin-inline: calc(50% - 50vw); margin-top: -1rem; border-radius: 0 0 28px 28px; box-shadow: none; } }
    .gw-stage { position: absolute; top: 0; left: 0; width: 390px; height: var(--gw-h, 760px); transform: scale(var(--gw-s, 1)); transform-origin: 0 0; overflow: hidden; direction: rtl; }
    .gw-world { position: absolute; inset: 0; }
    .gw-isl { position: absolute; cursor: pointer; background: none; border: 0; padding: 0; }
    .gw-isl > span { display: block; position: relative; width: 100%; height: 100%; }
    .gw-isl:focus-visible { outline: 3px solid #FFFFFF; outline-offset: 4px; border-radius: 30px; }
    .gw-label { position: absolute; left: 50%; transform: translateX(-50%); white-space: nowrap; display: flex; align-items: center; gap: 5px; padding: 1px 10px; border-radius: 999px; font-size: 12.5px; font-weight: 800; }
    .gw-label[data-kind="locked"] { bottom: -2px; background: var(--gw-ink); color: #FFFFFF; }
    .gw-label[data-kind="open"] { bottom: -10px; padding: 2px 12px; background: #FFFFFF; border: 3px solid var(--gw-ink); font-size: 13px; }
    .gw-label[data-kind="current"] { bottom: -6px; padding: 2px 12px; background: var(--gw-sun); border: 3px solid var(--gw-ink); box-shadow: 0 3px 0 var(--gw-ink); font-size: 13px; }
    .gw-label b { padding: 0 6px; border-radius: 999px; background: #FF6B6B; color: #FFFFFF; font-size: 11px; }
    .gw-pin { position: absolute; top: -6px; inset-inline-end: 10px; display: flex; align-items: center; gap: 3px; padding: 2px 8px 2px 6px; border-radius: 999px; background: #FFFFFF; border: 3px solid var(--gw-ink); box-shadow: 0 3px 0 var(--gw-ink); font-size: 12px; font-weight: 800; }
    .gw-head { position: absolute; top: 0; left: 0; right: 0; height: 68px; padding: 0 14px; display: flex; align-items: center; justify-content: space-between; z-index: 6; }
    .gw-head > div { display: flex; align-items: center; gap: 8px; }
    .gw-avatar { width: 46px; height: 46px; border-radius: 999px; background: #FFFFFF; border: 3px solid var(--gw-ink); display: flex; align-items: center; justify-content: center; overflow: hidden; box-sizing: border-box; text-decoration: none; }
    .gw-title { padding: 2px 10px; border-radius: 999px; background: rgba(19, 41, 75, .55); color: #FFFFFF; font-size: 16px; font-weight: 800; }
    .gw-pill { height: 34px; padding: 0 10px 0 8px; display: flex; align-items: center; gap: 4px; background: #FFFFFF; border: 3px solid var(--gw-ink); border-radius: 999px; box-sizing: border-box; font-weight: 800; font-size: 16px; }
    .gw-round-btn { width: 40px; height: 40px; border-radius: 999px; background: #FFFFFF; border: 3px solid var(--gw-ink); box-shadow: 0 3px 0 var(--gw-ink); display: flex; align-items: center; justify-content: center; padding: 0; }
    .gw-toast { position: absolute; left: 20px; right: 20px; top: 74px; padding: 10px 14px; border-radius: 18px; background: #FFFFFF; border: 3px solid var(--gw-ink); box-shadow: 0 4px 0 var(--gw-ink); font-size: 15.5px; font-weight: 700; text-align: center; z-index: 7; }
    .gw-toast-open { position: fixed; top: auto; bottom: calc(24px + env(safe-area-inset-bottom)); left: 0; right: 0; margin-inline: auto; width: min(350px, calc(100vw - 40px)); box-sizing: border-box; z-index: 46; }
    .gw-sheet-note { padding: 8px 12px; border-radius: 16px; background: #FFF4D6; border: 2px dashed var(--gw-ink); font-size: 14px; font-weight: 800; text-align: center; }
    .gw-note-sea { position: absolute; left: 20px; right: 20px; bottom: 16px; padding: 8px 14px; border-radius: 18px; background: rgba(19, 41, 75, .78); color: #FFFFFF; font-size: 14.5px; font-weight: 700; text-align: center; z-index: 6; }

    /* An opened island: a phone-shaped 390 picture scaled to the screen (--gw-ps), over everything. */
    .gw-open { position: fixed; inset: 0; z-index: 45; background: rgba(19, 41, 75, .6); overflow: hidden; }
    .gw-phone { position: absolute; top: 0; left: 50%; width: 390px; margin-left: -195px; height: calc(100dvh / var(--gw-ps, 1)); transform: scale(var(--gw-ps, 1)); transform-origin: 50% 0; overflow: hidden; direction: rtl; }
    .gw-back { position: absolute; top: 14px; inset-inline-start: 14px; height: 44px; padding: 0 14px 0 10px; border-radius: 999px; background: #FFFFFF; border: 3px solid var(--gw-ink); box-shadow: 0 3px 0 var(--gw-ink); display: flex; align-items: center; gap: 4px; font-size: 15px; font-weight: 800; z-index: 3; }
    [dir="ltr"] .gw-back svg { transform: scaleX(-1); }
    .gw-sheet { position: absolute; left: 0; right: 0; top: 296px; bottom: 0; background: #FFFFFF; border-radius: 30px 30px 0 0; border-top: 4px solid var(--gw-ink); padding: 14px 16px calc(16px + env(safe-area-inset-bottom)); box-sizing: border-box; display: flex; flex-direction: column; gap: 10px; overflow-y: auto; overscroll-behavior: contain; }
    .gw-sheet h1 { margin: 0; font-size: 26px; font-weight: 800; line-height: 1.1; }
    .gw-sheet-sub { font-size: 14px; font-weight: 600; color: #3B4F72; line-height: 1.35; }
    .gw-chip-progress { flex: none; max-width: 9.5rem; padding: 4px 10px; border-radius: 14px; background: #FFF4D6; border: 2px solid var(--gw-ink); font-size: 13px; font-weight: 800; text-align: center; line-height: 1.2; }
    .gw-chip-progress span { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .gw-gamecard { width: 100%; min-height: 82px; border-radius: 22px; border: 3px solid var(--gw-ink); display: flex; align-items: center; gap: 12px; padding: 8px 12px; color: var(--gw-ink); box-sizing: border-box; text-align: start; cursor: pointer; }
    .gw-gamecard[aria-disabled="true"] { opacity: .75; box-shadow: none !important; cursor: default; }
    .gw-tile { flex: none; width: 60px; height: 60px; border-radius: 18px; border: 3px solid var(--gw-ink); display: flex; align-items: center; justify-content: center; box-sizing: border-box; overflow: hidden; }
    .gw-gamecard strong { font-size: 19px; font-weight: 800; line-height: 1.15; }
    .gw-badge { padding: 0 8px; height: 20px; display: inline-flex; align-items: center; border-radius: 999px; background: var(--gw-sun); border: 2px solid var(--gw-ink); font-size: 11.5px; font-weight: 800; white-space: nowrap; }
    .gw-badge[data-kind="homework"] { background: #FF6B6B; color: #FFFFFF; }
    .gw-what { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; font-size: 13.5px; font-weight: 600; line-height: 1.3; }
    .gw-playdot { flex: none; width: 44px; height: 44px; border-radius: 999px; background: var(--gw-sun); border: 3px solid var(--gw-ink); display: flex; align-items: center; justify-content: center; box-sizing: border-box; }
    .gw-minicards { margin-top: auto; display: flex; gap: 10px; }
    .gw-minicards a { padding: 8px 12px; border-radius: 20px; border: 3px solid var(--gw-ink); box-shadow: 0 4px 0 var(--gw-ink); text-decoration: none; color: var(--gw-ink); box-sizing: border-box; }
    .gw-more { width: 32px; height: 32px; border-radius: 999px; border: 2px dashed #7048E8; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800; color: #5F3DC4; box-sizing: border-box; flex: none; }

    /* A round: the game full screen, the island's sky around it. */
    .gw-play { position: fixed; inset: 0; z-index: 50; display: flex; flex-direction: column; background: var(--gw-sky, #8ED8FF); }
    .gw-play-bar { display: flex; align-items: center; gap: 10px; padding: 10px 14px; padding-top: calc(10px + env(safe-area-inset-top)); }
    .gw-play-bar > div { min-width: 0; flex: 1; display: flex; flex-direction: column; }
    .gw-play-bar strong { align-self: flex-start; padding: 2px 12px; border-radius: 999px; background: rgba(19, 41, 75, .7); color: #FFFFFF; font-size: 17px; font-weight: 800; }
    .gw-play-bar small { margin-top: 3px; align-self: flex-start; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; padding: 1px 10px; border-radius: 999px; background: #FFFFFF; border: 2px solid var(--gw-ink); font-size: 12.5px; font-weight: 800; }
    .gw-play .gw-frame { flex: 1; min-height: 0; margin: 0 10px calc(10px + env(safe-area-inset-bottom)); border-radius: 26px; border: 4px solid var(--gw-ink); overflow: hidden; background: #FFFFFF; box-shadow: 0 6px 0 var(--gw-ink); }
    .gw-play .lms-game-frame { width: 100%; height: 100%; border: 0; display: block; }

    /* The result over the island. */
    .gw-done { position: fixed; inset: 0; z-index: 55; display: flex; align-items: center; justify-content: center; padding: 20px; background: rgba(19, 41, 75, .72); }
    .gw-done-card { position: relative; width: min(100%, 360px); padding: 22px 18px 18px; border-radius: 30px; background: #FFFFFF; border: 4px solid var(--gw-ink); box-shadow: 0 7px 0 var(--gw-ink); text-align: center; color: var(--gw-ink); }
    .gw-done-stars { display: flex; justify-content: center; gap: 6px; margin-top: -58px; }
    .gw-done-stars svg { filter: drop-shadow(0 4px 0 rgba(19, 41, 75, .25)); }
    .gw-done-stars [data-on="no"] path { fill: #E9ECEF; }
    .gw-done-card h2 { margin: 8px 0 0; font-size: 28px; font-weight: 800; line-height: 1.15; }
    .gw-done-card p { margin: 2px 0 0; font-size: 16px; font-weight: 700; color: #3B4F72; }
    .gw-done-hw { margin-top: 10px; padding: 8px 12px; border-radius: 16px; background: #D3F9D8; border: 2px solid var(--gw-ink); font-size: 14.5px; font-weight: 800; }
    .gw-eggbar { margin-top: 12px; display: flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 18px; background: #FFF4D6; border: 2px solid var(--gw-ink); font-size: 14px; font-weight: 800; text-align: start; }
    .gw-eggbar i { flex: 1; height: 12px; border-radius: 999px; background: #FFFFFF; border: 2px solid var(--gw-ink); overflow: hidden; }
    .gw-eggbar b { display: block; height: 100%; background: var(--gw-sun); transform-origin: 0 50%; }
    [dir="rtl"] .gw-eggbar b { transform-origin: 100% 50%; margin-inline-start: auto; }
    .gw-done-actions { margin-top: 14px; display: flex; flex-direction: column; gap: 10px; }
    .gw-bigbtn { height: 54px; border-radius: 999px; border: 3px solid var(--gw-ink); box-shadow: 0 4px 0 var(--gw-ink); display: flex; align-items: center; justify-content: center; gap: 6px; font-size: 19px; font-weight: 800; background: #FFFFFF; color: var(--gw-ink); text-decoration: none; box-sizing: border-box; width: 100%; }
    .gw-bigbtn[data-kind="go"] { background: var(--gw-sun); }

    /* A creature hatches (the night of the design): its own 390 x 844 picture scaled to the screen (--gw-hs). */
    .gw-hatch { position: fixed; inset: 0; z-index: 60; background: #2B1A66; overflow: hidden; color: #FFFFFF; }
    .gw-hatch-stage { position: absolute; top: 50%; left: 50%; width: 390px; height: 844px; margin: -422px 0 0 -195px; transform: scale(var(--gw-hs, 1)); direction: rtl; }
    .gw-hatch-card { position: absolute; left: 16px; right: 16px; bottom: 22px; padding: 14px 16px 16px; border-radius: 26px; background: #FFFFFF; color: #24124F; border: 4px solid #24124F; box-shadow: 0 6px 0 #120A33; z-index: 6; }
    .gw-name { height: 44px; padding: 0 16px; border-radius: 999px; font-size: 17px; font-weight: 800; border: 3px solid #24124F; background: #FFFFFF; cursor: pointer; }
    .gw-name[aria-checked="true"] { background: #B197FC; box-shadow: 0 4px 0 #24124F; }
    .gw-shell-top { transform-box: fill-box; transform-origin: 50% 100%; opacity: 0; }
    .gw-feather { opacity: 0; }


    @media (prefers-reduced-motion: no-preference) {
        .gw-world { transition: transform .75s cubic-bezier(.65, 0, .3, 1); }
        .gw-open { transition: clip-path .6s cubic-bezier(.6, 0, .3, 1); }
        .gw-wave { animation: gw-wave 3.2s ease-in-out infinite alternate; }
        .gw-wave.gw-d2 { animation-duration: 4.1s; animation-delay: -1s; }
        .gw-wave.gw-d3 { animation-duration: 3.6s; animation-delay: -2s; }
        .gw-twinkle { animation: gw-twinkle 2.2s ease-in-out infinite; }
        .gw-twinkle.gw-d2 { animation-delay: .8s; } .gw-twinkle.gw-d3 { animation-delay: 1.5s; }
        .gw-bob { animation: gw-bob 3.4s ease-in-out infinite; }
        .gw-bob.gw-d2 { animation-delay: -1.1s; } .gw-bob.gw-d3 { animation-delay: -2.2s; }
        .gw-orbit { animation: gw-orbit 7s linear infinite; }
        .gw-orbit.gw-d2 { animation-delay: -2.33s; } .gw-orbit.gw-d3 { animation-delay: -4.66s; }
        .gw-counter { animation: gw-orbit 7s linear infinite reverse; }
        .gw-counter.gw-d2 { animation-delay: -2.33s; } .gw-counter.gw-d3 { animation-delay: -4.66s; }
        .gw-fish { animation: gw-fish 6s ease-in-out infinite; }
        .gw-boat { animation: gw-boat 3s ease-in-out infinite; }
        .gw-wobble { animation: gw-wobble .55s ease-in-out; }
        .gw-pop-in { animation: gw-popin .45s cubic-bezier(.3, 1.6, .55, 1) backwards; }
        .gw-sheet-in { animation: gw-sheetin .6s cubic-bezier(.2, .9, .25, 1.05) .25s backwards; }
        .gw-rise { animation: gw-rise .5s cubic-bezier(.2, .9, .3, 1.1) calc(.45s + var(--i, 0) * .1s) backwards; }
        .gw-pin { animation: gw-bob 1.8s ease-in-out infinite; }
        .gw-label { animation: gw-labelin .4s cubic-bezier(.3, 1.6, .55, 1) backwards; }
        .gw-done-card { animation: gw-popin .45s cubic-bezier(.3, 1.6, .55, 1) backwards; }
        .gw-done-stars > * { animation: gw-starpop .5s cubic-bezier(.3, 1.8, .5, 1) calc(.25s + var(--i) * .28s) backwards; }
        .gw-eggbar b { animation: gw-fill .9s cubic-bezier(.2, .8, .3, 1) 1s backwards; }
        .gw-play .gw-frame { animation: gw-rise .5s cubic-bezier(.2, .9, .3, 1.1) backwards; }
        .gw-idle { animation: gw-idle 2.4s ease-in-out infinite; transform-origin: 50% 95%; }
        .gw-knock { animation: gw-knock .45s ease-in-out; transform-origin: 50% 95%; }
        .gw-shell-top { animation: gw-shellfly .9s cubic-bezier(.3, .7, .4, 1) both; }
        .gw-emerge { animation: gw-emerge .7s cubic-bezier(.3, 1.6, .55, 1) .15s backwards; }
        .gw-hover { animation: gw-hover 2.2s ease-in-out infinite; }
        .gw-spin { animation: gw-spin 18s linear infinite; }
        .gw-rays-in { animation: gw-popin .6s ease-out backwards; }
        .gw-feather { animation: gw-feather 2.6s ease-in backwards; }
        .gw-tap-hint { animation: gw-taphint 1.1s ease-in-out infinite; }
        .gw-hatch-card { animation: gw-popin .4s cubic-bezier(.3, 1.6, .55, 1) .9s backwards; }
    }
    @media (prefers-reduced-motion: reduce) { .gw-shell-top { opacity: 0; } .gw-feather { display: none; } }
    @keyframes gw-wave { from { transform: translateX(-10px); } to { transform: translateX(10px); } }
    @keyframes gw-bob { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-5px); } }
    @keyframes gw-orbit { from { transform: rotate(0); } to { transform: rotate(360deg); } }
    @keyframes gw-fish { 0%, 70% { transform: translate(0, 30px) rotate(0); opacity: 0; } 72% { opacity: 1; } 80% { transform: translate(-20px, -30px) rotate(-30deg); opacity: 1; } 90% { transform: translate(-40px, 20px) rotate(-120deg); opacity: 1; } 92%, 100% { transform: translate(-44px, 40px) rotate(-140deg); opacity: 0; } }
    @keyframes gw-boat { 0%, 100% { transform: translateY(0) rotate(-3deg); } 50% { transform: translateY(-4px) rotate(3deg); } }
    @keyframes gw-wobble { 0%, 100% { transform: rotate(0); } 20% { transform: rotate(-5deg); } 40% { transform: rotate(4deg); } 60% { transform: rotate(-3deg); } 80% { transform: rotate(2deg); } }
    @keyframes gw-popin { from { transform: scale(.4); opacity: 0; } to { transform: scale(1); opacity: 1; } }
    @keyframes gw-labelin { from { transform: translateX(-50%) scale(.4); opacity: 0; } to { transform: translateX(-50%) scale(1); opacity: 1; } }
    @keyframes gw-sheetin { from { transform: translateY(120%); } to { transform: translateY(0); } }
    @keyframes gw-rise { from { transform: translateY(24px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    @keyframes gw-starpop { from { transform: scale(0) rotate(-40deg); opacity: 0; } to { transform: scale(1) rotate(0); opacity: 1; } }
    @keyframes gw-fill { from { transform: scaleX(0); } }
    @keyframes gw-idle { 0%, 100% { transform: rotate(0); } 10% { transform: rotate(-3deg); } 20% { transform: rotate(3deg); } 30% { transform: rotate(0); } }
    @keyframes gw-knock { 0%, 100% { transform: rotate(0) scale(1); } 25% { transform: rotate(-9deg) scale(1.04); } 55% { transform: rotate(8deg) scale(1.05); } 80% { transform: rotate(-4deg); } }
    @keyframes gw-shellfly { 0% { transform: translate(0, 0) rotate(0); opacity: 1; } 100% { transform: translate(-90px, -240px) rotate(-140deg); opacity: 0; } }
    @keyframes gw-emerge { from { transform: translateY(60px) scale(.3); opacity: 0; } to { transform: translateY(0) scale(1); opacity: 1; } }
    @keyframes gw-hover { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-12px); } }
    @keyframes gw-feather { 0% { transform: translate(0, -40px) rotate(0); opacity: 1; } 100% { transform: translate(30px, 620px) rotate(260deg); opacity: 1; } }
    @keyframes gw-taphint { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-6px); } }
</style>
