{{--
    The journey's screens (docs/architecture/02, section 18). Around the world's art (islands, creatures:
    their own palette, shared::games.*) everything takes the academy's colours (Filament's variables) and
    the stage shell's shapes. Motion is transform and opacity, inside prefers-reduced-motion: no-preference.
--}}
<style>
    .jm { --jm-card: white; --jm-line: var(--gray-200); --jm-soft: var(--gray-50); --jm-muted: var(--gray-500); display: flex; flex-direction: column; gap: 1rem; min-width: 0; }
    .dark .jm { --jm-card: var(--gray-900); --jm-line: var(--gray-800); --jm-soft: var(--gray-800); --jm-muted: var(--gray-400); }
    .jm [x-cloak] { display: none !important; }
    .jm button { font: inherit; }
    .jm-empty { padding: 1.5rem; text-align: center; color: var(--gray-500); border: 1px dashed var(--gray-300); border-radius: var(--radius-xl, .75rem); }
    .jm-card { background: var(--jm-card); border: 1px solid var(--jm-line); border-radius: var(--radius-xl, .75rem); padding: 1rem 1.1rem; display: flex; flex-direction: column; gap: .7rem; min-width: 0; }
    .jm-card h2, .jm-card h3 { margin: 0; font-size: 1.02rem; font-weight: 700; line-height: 1.35; }
    .jm-row { display: flex; align-items: center; justify-content: space-between; gap: .6rem; flex-wrap: wrap; min-width: 0; }
    .jm-row > div { min-width: 0; }
    .jm-muted { color: var(--jm-muted); font-size: .85rem; }
    .jm-h { margin: .25rem 0 0; font-size: 1rem; font-weight: 700; }
    .jm-bar { height: .6rem; border-radius: 999px; background: var(--jm-soft); overflow: hidden; }
    .jm-bar i { display: block; height: 100%; border-radius: inherit; background: var(--primary-500); }
    .jm-pill { display: inline-flex; align-items: center; gap: .3rem; font-size: .78rem; font-weight: 600; padding: .12rem .6rem; border-radius: 999px; background: var(--jm-soft); color: var(--gray-700); white-space: nowrap; }
    .dark .jm-pill { color: var(--gray-200); }
    .jm-pill[data-tone="done"] { background: var(--success-50); color: var(--success-700); }
    .jm-pill[data-tone="here"] { background: var(--primary-50); color: var(--primary-700); }
    .jm-pill[data-tone="review"], .jm-pill[data-tone="open"] { background: var(--info-50, var(--primary-50)); color: var(--info-700, var(--primary-700)); }
    .jm-pill[data-tone="late"] { background: var(--danger-50); color: var(--danger-700); }
    .jm-pill[data-tone="star"] { background: var(--warning-50); color: var(--warning-800); }
    .dark .jm-pill[data-tone] { background: color-mix(in oklab, currentColor 14%, transparent); }
    .jm-btn { display: inline-flex; align-items: center; justify-content: center; gap: .35rem; min-height: 2.5rem; padding: .35rem .95rem; border-radius: var(--radius-lg, .5rem); border: 0; background: var(--primary-600); color: white !important; font-weight: 700; font-size: .9rem; text-decoration: none; cursor: pointer; white-space: nowrap; }
    .jm-btn:disabled { opacity: .5; cursor: default; }
    .jm-btn[data-kind="ghost"] { background: transparent; color: var(--primary-700) !important; border: 1px solid var(--primary-200); }
    .dark .jm-btn[data-kind="ghost"] { color: var(--primary-300) !important; border-color: var(--primary-800); }
    .jm-btn[data-kind="success"] { background: var(--success-600); }
    .jm-links { display: flex; gap: .5rem 1rem; flex-wrap: wrap; font-size: .88rem; }
    .jm-links a { color: var(--primary-700); font-weight: 600; text-decoration: none; }
    .dark .jm-links a { color: var(--primary-300); }
    .jm-chips { display: flex; gap: .4rem; overflow-x: auto; padding-bottom: .15rem; scrollbar-width: none; }
    .jm-chips button { flex: none; min-height: 2.3rem; padding: 0 .95rem; border-radius: 999px; border: 1px solid var(--jm-line); background: var(--jm-card); font-weight: 600; font-size: .88rem; cursor: pointer; color: inherit; }
    .jm-chips button[aria-pressed="true"] { background: var(--primary-600); border-color: var(--primary-600); color: white; }

    /* Missions: the next lesson, the teacher's homework, reviews due. */
    .jm-missions { display: flex; flex-direction: column; gap: .55rem; margin: 0; padding: 0; list-style: none; }
    .jm-mission { display: flex; align-items: center; gap: .75rem; padding: .7rem .8rem; border-radius: var(--radius-lg, .6rem); background: var(--jm-card); border: 1px solid var(--jm-line); min-width: 0; }
    .jm-mission[data-state="done"] { opacity: .7; }
    .jm-mission > span:first-child { flex: none; width: 2.4rem; height: 2.4rem; border-radius: .8rem; display: grid; place-items: center; font-size: 1.15rem; background: var(--jm-soft); }
    .jm-mission > div { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: .1rem; }
    .jm-mission strong { font-size: .95rem; font-weight: 700; overflow-wrap: anywhere; }
    .jm-mission small { color: var(--jm-muted); font-size: .8rem; }
    .jm-mission .jm-acts { flex: none; display: flex; gap: .35rem; flex-wrap: wrap; justify-content: flex-end; }
    @media (max-width: 479.98px) { .jm-mission { flex-wrap: wrap; } .jm-mission .jm-acts { width: 100%; justify-content: flex-start; padding-inline-start: 3.15rem; } }

    /* Levels and their stations (middle school, the calm view, a guardian's). */
    .jm-level { display: flex; flex-direction: column; gap: .6rem; }
    .jm-level-head { display: flex; align-items: center; gap: .7rem; min-width: 0; }
    .jm-level-art { position: relative; flex: none; width: 3.6rem; height: 3rem; overflow: hidden; border-radius: .9rem; background: color-mix(in oklab, var(--info-200, var(--primary-200)) 55%, white); }
    .jm-level-art > span { position: absolute; left: 50%; top: 50%; width: 200px; height: 190px; transform: translate(-50%, -54%) scale(.27); transform-origin: 50% 50%; }
    .jm-level[data-state="ahead"] .jm-level-art { filter: grayscale(.7); opacity: .7; }
    .jm-path { position: relative; display: flex; flex-direction: column; gap: .2rem; padding-inline-start: .4rem; margin: 0; list-style: none; }
    .jm-path::before { content: ''; position: absolute; inset-block: 1.2rem; inset-inline-start: 1.55rem; width: 3px; border-radius: 3px; background: var(--jm-line); }
    .jm-node { position: relative; display: flex; align-items: center; gap: .7rem; width: 100%; padding: .3rem .35rem; border: 0; border-radius: var(--radius-lg, .6rem); background: none; text-align: start; color: inherit; cursor: pointer; }
    .jm-node:hover { background: var(--jm-soft); }
    .jm-node > span:first-child { position: relative; z-index: 1; flex: none; width: 2.35rem; height: 2.35rem; border-radius: 999px; display: grid; place-items: center; font-size: .95rem; font-weight: 800; background: var(--jm-card); border: 2px solid var(--jm-line); color: var(--jm-muted); }
    .jm-node[data-state="done"] > span:first-child { background: var(--success-500); border-color: var(--success-500); color: white; }
    .jm-node[data-state="review"] > span:first-child { background: var(--info-500, var(--primary-400)); border-color: var(--info-500, var(--primary-400)); color: white; }
    .jm-node[data-state="here"] > span:first-child { width: 2.6rem; height: 2.6rem; margin-inline-start: -.12rem; background: var(--primary-600); border-color: var(--primary-600); color: white; box-shadow: 0 0 0 .35rem color-mix(in oklab, var(--primary-400) 25%, transparent); }
    .jm-node[data-state="todo"] { opacity: .65; }
    .jm-node > span:nth-child(2) { flex: 1; min-width: 0; display: flex; flex-direction: column; }
    .jm-node strong { font-size: .92rem; font-weight: 600; overflow-wrap: anywhere; }
    .jm-node small { color: var(--jm-muted); font-size: .76rem; }
    .jm-node .jm-flag { flex: none; font-size: .95rem; }

    /* A station's sheet (not the children's: theirs is over the island). */
    .jm-sheet-bg { position: fixed; inset: 0; z-index: 40; background: rgb(0 0 0 / .45); }
    .jm-sheet { position: fixed; z-index: 41; left: 0; right: 0; bottom: 0; max-width: 34rem; margin-inline: auto; max-height: 88dvh; overflow-y: auto; overscroll-behavior: contain; box-sizing: border-box; padding: 1rem 1.1rem calc(1.2rem + env(safe-area-inset-bottom)); border-radius: 1.4rem 1.4rem 0 0; background: var(--jm-card); color: var(--gray-900); display: flex; flex-direction: column; gap: .75rem; }
    .dark .jm-sheet { color: var(--gray-100); }
    @media (min-width: 640px) { .jm-sheet { bottom: auto; top: 50%; transform: translateY(-50%); border-radius: 1.2rem; max-height: 80dvh; } }
    .jm-sheet h2 { margin: 0; font-size: 1.2rem; font-weight: 800; line-height: 1.3; overflow-wrap: anywhere; }
    .jm-x { flex: none; width: 2.4rem; height: 2.4rem; border-radius: 999px; border: 1px solid var(--jm-line); background: var(--jm-card); display: grid; place-items: center; cursor: pointer; color: inherit; }
    .jm-games { display: grid; grid-template-columns: repeat(auto-fill, minmax(11rem, 1fr)); gap: .5rem; }
    .jm-game { display: flex; flex-direction: column; align-items: flex-start; gap: .15rem; padding: .7rem .8rem; border-radius: var(--radius-lg, .6rem); border: 1px solid var(--jm-line); background: var(--jm-soft); text-align: start; cursor: pointer; color: inherit; }
    .jm-game:hover { border-color: var(--primary-300); }
    .jm-game strong { font-size: .95rem; }
    .jm-game small { color: var(--jm-muted); font-size: .78rem; }
    .jm-race { display: flex; gap: .5rem; flex-wrap: wrap; }

    /* Stars and the rank: middle school's bar, everyone's chip. */
    .jm-xp { display: flex; flex-direction: column; gap: .35rem; }
    .jm-xp .jm-bar { height: .85rem; }
    .jm-xp .jm-bar i { background: linear-gradient(90deg, var(--warning-400), var(--warning-500)); }
    [dir="rtl"] .jm-xp .jm-bar i { background: linear-gradient(-90deg, var(--warning-400), var(--warning-500)); }
    .jm-big { font-size: 2rem; font-weight: 800; line-height: 1; font-variant-numeric: tabular-nums; }
    .jm-hello { display: flex; align-items: center; gap: .8rem; }
    .jm-buddy { flex: none; width: 3.4rem; height: 3.4rem; border-radius: 1rem; display: grid; place-items: center; background: var(--warning-50); overflow: hidden; text-decoration: none; }
    .jm-lastl { display: flex; align-items: center; gap: .7rem; padding: .7rem .9rem; border-radius: var(--radius-lg, .6rem); background: var(--warning-50); color: var(--warning-900); }
    .dark .jm-lastl { background: color-mix(in oklab, var(--warning-500) 14%, transparent); color: var(--warning-200); }
    .jm-lastl b { font-size: 1.1rem; letter-spacing: .05em; }
    .jm-cheer { display: flex; align-items: center; gap: .6rem; padding: .7rem .9rem; border-radius: var(--radius-lg, .6rem); background: var(--danger-50); color: var(--danger-800); font-weight: 600; }
    .dark .jm-cheer { background: color-mix(in oklab, var(--danger-500) 14%, transparent); color: var(--danger-200); }
    .jm-cheers { display: flex; gap: .4rem; flex-wrap: wrap; }
    .jm-cheers button { min-width: 3rem; min-height: 2.75rem; border-radius: 999px; border: 1px solid var(--jm-line); background: var(--jm-card); font-size: 1.3rem; cursor: pointer; }
    .jm-cheers button:disabled { opacity: .45; cursor: default; }

    /* The week's chest. */
    .jm-chest { display: flex; align-items: center; gap: .8rem; }
    .jm-chest > span:first-child { flex: none; font-size: 2.2rem; line-height: 1; }
    .jm-chest > div { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: .35rem; }
    .jm-chest .jm-bar i { background: var(--warning-500); }
    .jm-stickers { display: flex; flex-wrap: wrap; gap: .2rem; font-size: 1.35rem; line-height: 1.2; }
    .jm-egg { display: flex; align-items: center; gap: .7rem; }
    .jm-egg-btn { flex: none; border: 0; background: none; padding: 0; cursor: pointer; }

    /* Two columns on wide screens: the journey, and what to do now beside it. */
    .jm-cols { display: grid; gap: 1rem; grid-template-columns: minmax(0, 1fr); align-items: start; }
    .jm-side { display: flex; flex-direction: column; gap: 1rem; min-width: 0; }
    @media (min-width: 1024px) { .jm-cols { grid-template-columns: minmax(0, 1.35fr) minmax(0, 1fr); } }

    /* Children: the islands map (shared::games.world-styles) and around it, in the world's look. */
    .jk { display: grid; gap: 1rem; grid-template-columns: minmax(0, 1fr); align-items: start; }
    @media (min-width: 1024px) { .jk { grid-template-columns: minmax(0, 520px) minmax(0, 1fr); } }
    .jk-side { display: flex; flex-direction: column; gap: .9rem; min-width: 0; }
    .jk-card { background: white; color: var(--gw-ink, #13294B); border: 3px solid var(--gw-ink, #13294B); border-radius: 1.4rem; box-shadow: 0 4px 0 var(--gw-ink, #13294B); padding: .8rem .9rem; display: flex; flex-direction: column; gap: .5rem; }
    .jk-card h2 { margin: 0; font-size: 1.15rem; font-weight: 800; }
    .jk-strip { display: flex; gap: .7rem; overflow-x: auto; padding: .2rem .1rem .6rem; scroll-snap-type: x mandatory; scrollbar-width: none; }
    .jk-strip > * { scroll-snap-align: start; flex: 0 0 min(15.5rem, 78vw); }
    @media (min-width: 1024px) { .jk-strip { flex-direction: column; overflow: visible; } .jk-strip > * { flex: none; } }
    .jk-mission { position: relative; display: flex; flex-direction: column; gap: .3rem; padding: .7rem .8rem; border-radius: 1.2rem; border: 3px solid var(--gw-ink, #13294B); box-shadow: 0 4px 0 var(--gw-ink, #13294B); background: #FFFFFF; color: var(--gw-ink, #13294B); min-width: 0; box-sizing: border-box; }
    .jk-mission[data-kind="lesson"] { background: #D0EBFF; }
    .jk-mission[data-kind="homework"] { background: #FFE8CC; }
    .jk-mission[data-kind="review"] { background: #C3FAE8; }
    .jk-mission[data-state="done"] { opacity: .75; }
    .jk-mission strong { font-size: 1.02rem; font-weight: 800; line-height: 1.25; overflow-wrap: anywhere; }
    .jk-mission small { font-size: .82rem; font-weight: 700; color: #3B4F72; }
    .jk-kind { align-self: flex-start; font-size: .72rem; font-weight: 800; padding: .05rem .55rem; border-radius: 999px; background: #FFFFFF; border: 2px solid var(--gw-ink, #13294B); }
    .jk-go { margin-top: auto; align-self: stretch; display: flex; align-items: center; justify-content: center; gap: .3rem; min-height: 2.6rem; border-radius: 999px; border: 3px solid var(--gw-ink, #13294B); box-shadow: 0 3px 0 var(--gw-ink, #13294B); background: var(--gw-sun, #FFD43B); color: var(--gw-ink, #13294B) !important; font-weight: 800; font-size: .95rem; text-decoration: none; cursor: pointer; }
    .jk-go[data-kind="plain"] { background: #FFFFFF; }
    .jk-go:active { transform: translateY(3px); box-shadow: 0 0 0 var(--gw-ink, #13294B); }
    .jk-chips { display: flex; gap: .45rem; overflow-x: auto; scrollbar-width: none; }
    .jk-chips button { flex: none; min-height: 2.5rem; padding: 0 1rem; border-radius: 999px; border: 3px solid var(--gw-ink, #13294B); background: #FFFFFF; color: var(--gw-ink, #13294B); font-weight: 800; cursor: pointer; }
    .jk-chips button[aria-pressed="true"] { background: var(--gw-sun, #FFD43B); box-shadow: 0 3px 0 var(--gw-ink, #13294B); }
    .jk-bar { height: .8rem; border-radius: 999px; background: #FFFFFF; border: 2px solid var(--gw-ink, #13294B); overflow: hidden; }
    .jk-bar i { display: block; height: 100%; background: var(--gw-sun, #FFD43B); }
    .jk-chest-open { animation: none; }
    .jk-rank { position: absolute; top: 58px; inset-inline-start: 66px; padding: 0 9px; border-radius: 999px; background: #FFFFFF; border: 2px solid var(--gw-ink, #13294B); font-size: 11.5px; font-weight: 800; z-index: 6; }
    .jk-here { position: absolute; display: flex; flex-direction: column; align-items: center; pointer-events: none; z-index: 4; }
    .jk-here b { padding: 2px 10px; border-radius: 999px; background: #FF6B6B; color: #FFFFFF; border: 3px solid var(--gw-ink, #13294B); font-size: 12.5px; font-weight: 800; white-space: nowrap; }
    .jk-egg { position: absolute; z-index: 5; display: flex; flex-direction: column; align-items: center; gap: 2px; background: none; border: 0; padding: 0; cursor: pointer; }
    .jk-egg b { padding: 1px 9px; border-radius: 999px; background: #FFFFFF; border: 2px solid var(--gw-ink, #13294B); font-size: 12px; font-weight: 800; white-space: nowrap; color: var(--gw-ink, #13294B); }
    .gw-label { max-width: 190px; }
    .gw-label > span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0; }

    /* An opened island's stations: a winding path of stops, then a stop's sheet over it. */
    .jk-path { position: relative; flex: none; margin: 2px 0 6px; }
    .jk-path svg { position: absolute; inset: 0; width: 100%; height: 100%; overflow: visible; pointer-events: none; }
    .jk-stop { position: absolute; left: var(--x); top: calc(var(--k) * 92px + 8px); transform: translateX(-50%); width: 128px; display: flex; flex-direction: column; align-items: center; gap: 3px; background: none; border: 0; padding: 0; cursor: pointer; color: var(--gw-ink, #13294B); text-align: center; }
    .jk-dot { position: relative; width: 52px; height: 52px; border-radius: 999px; display: grid; place-items: center; font-size: 22px; font-weight: 800; border: 3px solid var(--gw-ink, #13294B); box-shadow: 0 4px 0 var(--gw-ink, #13294B); background: #FFFFFF; box-sizing: border-box; }
    .jk-stop[data-state="done"] .jk-dot { background: #FFD43B; }
    .jk-stop[data-state="review"] .jk-dot { background: #74C0FC; }
    .jk-stop[data-state="here"] .jk-dot { width: 62px; height: 62px; background: #FF6B6B; color: #FFFFFF; }
    .jk-stop[data-state="todo"] .jk-dot { background: #E9ECEF; box-shadow: none; border-style: dashed; color: #868E96; font-size: 18px; }
    .jk-stop[data-state="todo"] .jk-name { opacity: .6; }
    .jk-name { max-width: 128px; font-size: 13px; font-weight: 800; line-height: 1.2; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .jk-flag { position: absolute; top: -10px; inset-inline-end: -12px; font-size: 18px; }
    .jk-bubble { position: absolute; bottom: calc(100% + 4px); padding: 1px 9px; border-radius: 999px; background: var(--gw-ink, #13294B); color: #FFFFFF; font-size: 12px; font-weight: 800; white-space: nowrap; }
    .jk-boat { position: absolute; top: calc(var(--k) * 92px + 14px); z-index: 2; display: flex; align-items: center; gap: 4px; max-width: 104px; padding: 4px 9px; border-radius: 14px; background: #D0EBFF; border: 2px solid var(--gw-ink, #13294B); font-size: 12px; font-weight: 800; color: var(--gw-ink, #13294B); text-decoration: none; line-height: 1.2; }
    .jk-boat[data-open] { background: #FFD43B; box-shadow: 0 3px 0 var(--gw-ink, #13294B); }
    .jk-ssheet { position: absolute; left: 0; right: 0; bottom: 0; max-height: 76%; z-index: 4; overflow-y: auto; overscroll-behavior: contain; padding: 14px 16px calc(18px + env(safe-area-inset-bottom)); box-sizing: border-box; background: #FFFFFF; border-top: 4px solid var(--gw-ink, #13294B); border-radius: 30px 30px 0 0; display: flex; flex-direction: column; gap: 10px; color: var(--gw-ink, #13294B); box-shadow: 0 -10px 30px rgba(19, 41, 75, .25); }
    .jk-ssheet h2 { margin: 0; font-size: 24px; font-weight: 800; line-height: 1.15; overflow-wrap: anywhere; }
    .jk-ssheet .gw-sheet-close { flex: none; width: 40px; height: 40px; border-radius: 999px; background: #FFFFFF; border: 3px solid var(--gw-ink, #13294B); box-shadow: 0 3px 0 var(--gw-ink, #13294B); display: grid; place-items: center; padding: 0; cursor: pointer; }
    .jk-state { align-self: flex-start; padding: 1px 10px; border-radius: 999px; border: 2px solid var(--gw-ink, #13294B); font-size: 12.5px; font-weight: 800; background: #FFF4D6; }
    .jk-line { display: flex; align-items: center; gap: 8px; padding: 8px 10px; border-radius: 16px; border: 2px solid var(--gw-ink, #13294B); background: #FFF4D6; font-size: 14px; font-weight: 700; }
    .jk-line > span { flex: 1; min-width: 0; overflow-wrap: anywhere; }
    .jk-line .jk-go { flex: none; min-height: 36px; padding: 0 14px; margin: 0; }
    .jk-race { padding: 8px 12px; border-radius: 16px; background: #F3F0FF; border: 2px dashed #7048E8; font-size: 13.5px; font-weight: 800; }

    @media (prefers-reduced-motion: no-preference) {
        .jk-egg svg, .jm-egg-btn svg { animation: jm-eggwob 2.6s ease-in-out infinite; transform-origin: 50% 90%; }
        .jk-here { animation: gw-bob 1.8s ease-in-out infinite; }
        .jk-ssheet { animation: gw-sheetin .45s cubic-bezier(.2, .9, .25, 1.05) backwards; }
        .jk-stop[data-state="here"] .jk-dot { animation: jk-pulse 2.4s ease-in-out infinite; }
        .jm-sheet { animation: jm-rise .3s cubic-bezier(.2, .9, .3, 1.05) backwards; }
        .jm-chest[data-ready] > span:first-child { animation: jm-glow 1.6s ease-in-out infinite; display: inline-block; }
        .jk-strip > * { animation: gw-rise .5s cubic-bezier(.2, .9, .3, 1.1) calc(.15s + var(--i, 0) * .08s) backwards; }
    }
    @keyframes jk-pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.08); } }
    @keyframes jm-rise { from { opacity: 0; transform: translateY(1.2rem); } }
    @media (min-width: 640px) { @keyframes jm-rise { from { opacity: 0; transform: translateY(-46%); } } }
    @keyframes jm-glow { 0%, 100% { transform: scale(1) rotate(0); } 25% { transform: scale(1.12) rotate(-6deg); } 50% { transform: scale(1.12) rotate(6deg); } }
    @keyframes jm-eggwob { 0%, 100% { transform: rotate(0); } 15% { transform: rotate(-7deg); } 30% { transform: rotate(6deg); } 45% { transform: rotate(-3deg); } 60% { transform: rotate(0); } }
</style>
