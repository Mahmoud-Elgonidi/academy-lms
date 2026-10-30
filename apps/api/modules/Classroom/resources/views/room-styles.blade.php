{{-- The class page's look: a dark stage in fixed neutrals, the academy's brand only on the main buttons and accents. Also copied into the picture-in-picture window. --}}
<style id="room-styles">
    :root {
        --brand: {{ $brand->tokens['primary'] ?? '#0F766E' }};
        --danger: {{ $brand->tokens['danger'] ?? '#DC2626' }};
        --bg: #17181b; --panel: #222326; --line: #36373c; --text: #eceef1; --muted: #a2a6ad;
        --tile: #2b2c30; --ctl: #35363b; --ctl-hover: #44464c; --ok: #34a853; --warn: #fbbc04;
        --font: 'IBM Plex Sans Arabic', system-ui, -apple-system, 'Segoe UI', Roboto, Tahoma, Arial, sans-serif;
    }
    * { box-sizing: border-box; }
    html, body { margin: 0; height: 100%; }
    body { background: var(--bg); color: var(--text); font-family: var(--font); display: flex; flex-direction: column; height: 100dvh; overflow: hidden; -webkit-font-smoothing: antialiased; }
    [hidden] { display: none !important; }
    .ic { width: 22px; height: 22px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; flex: none; }
    .sr { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }

    /* Shared basics (also the mushaf's and the plan's buttons). */
    button { font: inherit; border: 0; border-radius: 10px; padding: 10px 16px; cursor: pointer; background: var(--ctl); color: var(--text); }
    button:hover { background: var(--ctl-hover); }
    button:focus-visible, a:focus-visible, .tile:focus-visible { outline: 2px solid color-mix(in oklab, var(--brand) 70%, #fff); outline-offset: 2px; }
    button.primary { background: var(--brand); color: #fff; font-weight: 700; }
    button.primary:hover { background: color-mix(in oklab, var(--brand) 85%, #fff); }
    button:disabled { opacity: .55; cursor: default; }
    a.button { display: inline-block; text-decoration: none; border-radius: 999px; padding: 10px 18px; background: var(--ctl); color: var(--text); }
    a.button.primary { background: var(--brand); color: #fff; font-weight: 700; }
    .err { color: #fca5a5; font-size: 14px; margin: 0; }
    .row { display: flex; gap: 8px; flex-wrap: wrap; }

    /* Top bar. */
    .topbar { display: flex; align-items: center; gap: 12px; height: 56px; padding: 0 16px; flex: none; }
    .topbar .brand { font-weight: 700; white-space: nowrap; color: var(--muted); font-size: 14px; }
    .topbar .lesson { display: flex; flex-direction: column; min-width: 0; flex: 1; }
    .topbar .lesson strong { font-size: 15px; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .topbar .lesson small { color: var(--muted); font-size: 12px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .topbar .stat { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--muted); white-space: nowrap; }
    .topbar .stat .ic { width: 18px; height: 18px; }
    .timer { font-variant-numeric: tabular-nums; color: var(--text) !important; }
    .quality::before { content: ''; width: 8px; height: 8px; border-radius: 50%; background: var(--muted); }
    .quality[data-q="excellent"]::before, .quality[data-q="good"]::before { background: var(--ok); }
    .quality[data-q="poor"]::before { background: var(--warn); }
    .quality[data-q="lost"]::before { background: var(--danger); }
    .quality[data-q="poor"], .quality[data-q="lost"] { color: #fde68a; }
    .quality .q-text { display: none; }
    .quality[data-q="poor"] .q-text, .quality[data-q="lost"] .q-text { display: inline; }
    .rec { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700; padding: 3px 10px; border-radius: 999px; background: color-mix(in oklab, var(--danger) 22%, transparent); color: #fecaca; white-space: nowrap; }
    .rec::before { content: ''; width: 8px; height: 8px; border-radius: 50%; background: var(--danger); }
    .concern { display: inline-flex; align-items: center; gap: 6px; color: var(--muted); font-size: 12px; text-decoration: none; white-space: nowrap; padding: 6px 8px; border-radius: 8px; }
    .concern:hover { color: var(--text); background: var(--ctl); }
    .concern .ic { width: 16px; height: 16px; }
    .chip { font-size: 12px; padding: 3px 10px; border-radius: 999px; background: var(--ctl); color: var(--text); white-space: nowrap; }

    /* The page under the top bar: the stage, and the panel beside it. */
    main { flex: 1; min-height: 0; display: grid; grid-template-columns: minmax(0, 1fr); gap: 12px; padding: 0 12px 12px; }
    .stage { position: relative; display: flex; flex-direction: column; min-height: 0; min-width: 0; }

    /* Before joining. */
    .prejoin { margin: auto; width: 100%; max-width: 980px; display: grid; grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr); gap: 32px; align-items: center; padding: 16px; }
    .prejoin.observer { grid-template-columns: minmax(0, 1fr); max-width: 520px; text-align: center; }
    .prejoin.observer .ready .row { justify-content: center; }
    .preview { position: relative; aspect-ratio: 16 / 9; background: #000; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,.35); }
    .preview video { width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1); }
    .preview .off { position: absolute; inset: 0; display: grid; place-items: center; color: var(--muted); font-size: 15px; background: var(--tile); }
    .preview .pre-tools { position: absolute; inset-inline: 0; bottom: 14px; display: flex; justify-content: center; gap: 14px; }
    .meter { position: absolute; inset-inline-start: 14px; top: 14px; width: 84px; height: 6px; border-radius: 999px; background: rgba(255,255,255,.18); overflow: hidden; }
    .meter i { display: block; height: 100%; width: 100%; background: var(--ok); transform: scaleX(0); transform-origin: left center; }
    [dir="rtl"] .meter i { transform-origin: right center; }
    .ready { display: flex; flex-direction: column; gap: 12px; }
    .ready h1 { margin: 0; font-size: 26px; font-weight: 500; }
    .ready .what { margin: 0; font-size: 15px; }
    .ready p { margin: 0; color: var(--muted); font-size: 14px; line-height: 1.7; }
    .ready .row button { border-radius: 999px; padding: 12px 22px; }
    .may-record { font-size: 13px; color: var(--muted); border-inline-start: 3px solid var(--danger); padding-inline-start: 10px; }

    /* Round control buttons: in the bar, on the preview, in picture-in-picture. */
    .round { width: 48px; height: 48px; padding: 0; border-radius: 50%; display: inline-grid; place-items: center; background: var(--ctl); color: var(--text); position: relative; }
    .round:hover { background: var(--ctl-hover); }
    .round .off { display: none; }
    .round.toggle[aria-pressed="false"] .on { display: none; }
    .round.toggle[aria-pressed="false"] .off { display: block; }
    .round.mute[aria-pressed="false"] { background: var(--danger); color: #fff; }
    .round.state[aria-pressed="true"], .round[aria-expanded="true"] { background: color-mix(in oklab, var(--brand) 80%, #000); color: #fff; }
    .preview .round { background: rgba(32,33,36,.6); border: 1px solid rgba(255,255,255,.45); }
    .preview .round.mute[aria-pressed="false"] { background: var(--danger); border-color: transparent; }

    /* The call. */
    .call { flex: 1; min-height: 0; display: flex; flex-direction: column; gap: 8px; position: relative; }
    .tiles { flex: 1; min-height: 0; position: relative; overflow: hidden; }
    .tile { position: absolute; left: 0; top: 0; background: var(--tile); border-radius: 14px; overflow: hidden; cursor: pointer; isolation: isolate; }
    .tile video { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: 0; }
    .tile.has-video video { opacity: 1; }
    .tile.local:not(.screen) video { transform: scaleX(-1); }
    .tile.screen video, .tile.main video { object-fit: contain; }
    .tile.screen, .tile.main { background: #0b0b0c; }
    .tile .avatar { position: absolute; inset: 0; display: grid; place-items: center; }
    .tile .avatar span { width: clamp(44px, 30%, 128px); aspect-ratio: 1; border-radius: 50%; display: grid; place-items: center; font-size: clamp(18px, 4vw, 52px); font-weight: 600; color: #fff; background: hsl(var(--hue, 200) 45% 42%); text-transform: uppercase; }
    .tile.has-video .avatar { display: none; }
    .tile .name { position: absolute; inset-inline-start: 10px; bottom: 10px; max-width: calc(100% - 20px); display: inline-flex; align-items: center; gap: 4px; background: rgba(0,0,0,.5); padding: 3px 10px; border-radius: 999px; font-size: 13px; z-index: 2; }
    .tile .name > span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .tile .name .mute-ic { display: none; width: 16px; height: 16px; color: #fff; background: var(--danger); border-radius: 50%; padding: 2px; }
    .tile.muted .name .mute-ic { display: block; }
    .tile .tq { position: absolute; inset-inline-end: 10px; top: 10px; display: none; align-items: flex-end; gap: 2px; height: 14px; padding: 3px 5px; border-radius: 6px; background: rgba(0,0,0,.5); z-index: 2; }
    .tile .tq i { width: 3px; border-radius: 1px; background: var(--warn); }
    .tile .tq i:nth-child(1) { height: 4px; } .tile .tq i:nth-child(2) { height: 7px; opacity: .35; } .tile .tq i:nth-child(3) { height: 10px; opacity: .35; }
    .tile[data-q="poor"] .tq, .tile[data-q="lost"] .tq { display: inline-flex; }
    .tile[data-q="lost"] .tq i { background: var(--danger); }
    .tile .pin-ic { position: absolute; inset-inline-start: 10px; top: 10px; display: none; width: 28px; height: 28px; place-items: center; border-radius: 50%; background: rgba(0,0,0,.55); z-index: 2; }
    .tile .pin-ic .ic { width: 16px; height: 16px; }
    .tile.pinned .pin-ic { display: grid; }
    .tile .presenting { position: absolute; top: 10px; inset-inline: 0; margin-inline: auto; width: max-content; max-width: 90%; font-size: 13px; background: rgba(0,0,0,.6); padding: 4px 12px; border-radius: 999px; z-index: 2; }
    .tile::after { content: ''; position: absolute; inset: 0; border-radius: inherit; border: 3px solid var(--brand); opacity: 0; pointer-events: none; z-index: 3; }
    .tile.speaking::after { opacity: 1; }
    .tile.strip .name { font-size: 12px; bottom: 6px; inset-inline-start: 6px; padding: 2px 8px; }
    .tile.strip .avatar span { font-size: 18px; }
    .tile.float { z-index: 5; box-shadow: 0 8px 28px rgba(0,0,0,.5); touch-action: none; cursor: grab; }
    .tile.float.dragging { cursor: grabbing; }
    .tile.float .name { font-size: 12px; bottom: 6px; inset-inline-start: 6px; padding: 2px 8px; }
    .waiting { position: absolute; top: 14px; inset-inline: 0; margin-inline: auto; width: max-content; max-width: calc(100% - 24px); z-index: 6; background: rgba(32,33,36,.85); color: var(--text); padding: 8px 16px; border-radius: 999px; font-size: 14px; text-align: center; }
    .banner { position: absolute; top: 14px; inset-inline: 0; margin-inline: auto; width: max-content; max-width: calc(100% - 24px); z-index: 7; background: #78350f; color: #fef3c7; padding: 8px 16px; border-radius: 999px; font-size: 14px; }
    .toast { position: absolute; bottom: 80px; inset-inline: 0; margin-inline: auto; width: max-content; max-width: calc(100% - 24px); z-index: 8; background: #3b1414; padding: 8px 16px; border-radius: 12px; }

    /* The control bar. */
    .bar { flex: none; display: flex; align-items: center; justify-content: center; gap: 10px; padding: 6px 0 calc(4px + env(safe-area-inset-bottom)); position: relative; }
    .bar .group { position: relative; }
    .leave { display: inline-flex; align-items: center; gap: 8px; height: 48px; padding: 0 22px; border-radius: 999px; background: var(--danger); color: #fff; font-weight: 700; }
    .leave:hover { background: color-mix(in oklab, var(--danger) 85%, #fff); }
    .pop { position: absolute; bottom: calc(100% + 10px); inset-inline-end: 0; min-width: 240px; background: var(--panel); border: 1px solid var(--line); border-radius: 14px; padding: 6px; box-shadow: 0 12px 36px rgba(0,0,0,.5); display: flex; flex-direction: column; gap: 2px; z-index: 20; }
    .pop button { display: flex; align-items: center; gap: 10px; width: 100%; background: none; text-align: start; padding: 10px 12px; border-radius: 10px; font-size: 14px; }
    .pop button:hover { background: var(--ctl); }
    .pop button .ic { width: 20px; height: 20px; color: var(--muted); }
    .pop button[aria-pressed="true"] { color: #fff; background: color-mix(in oklab, var(--brand) 30%, transparent); }
    .pop button[aria-pressed="true"] .ic { color: #fff; }
    .pop button.record[aria-pressed="true"] { background: color-mix(in oklab, var(--danger) 30%, transparent); }
    .pop button.record .ic { color: var(--danger); }
    .pop .sep { height: 1px; background: var(--line); margin: 4px 6px; }
    .pop .note { font-size: 12px; color: var(--muted); margin: 2px 12px 6px; line-height: 1.6; }
    .pop .narrow { display: none !important; }

    /* The side panel: the mushaf, today's plan and homework, or the materials. */
    aside { background: var(--panel); border-radius: 16px; padding: 14px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; min-height: 0; }
    aside h2 { margin: 0; font-size: 15px; }
    .panel-head { display: none; align-items: center; justify-content: space-between; gap: 8px; }
    .panel-head strong { font-size: 16px; font-weight: 600; }
    .panel-head .round { width: 36px; height: 36px; background: none; }
    .panel-head .round:hover { background: var(--ctl); }
    body[data-state="live"] .panel-head { display: flex; }
    aside .mushaf:not(:last-child) { padding-bottom: 12px; border-bottom: 1px solid var(--line); }
    aside .line { border: 1px solid var(--line); border-radius: 10px; padding: 8px 10px; display: flex; flex-direction: column; gap: 2px; }
    aside .line small, aside .none { color: var(--muted); font-size: 12px; }
    aside .line p { margin: 4px 0 0; font-size: 13px; color: #d4d4d8; white-space: pre-line; }
    .tag { align-self: flex-start; font-size: 11px; padding: 1px 8px; border-radius: 999px; background: var(--line); }
    aside h2 + h2, aside .line + h2, aside small + h2 { margin-top: 6px; }
    .homework.submitted .tag { background: color-mix(in oklab, var(--brand) 35%, var(--line)); }
    .homework.missed .tag { background: #7f1d1d; color: #fee2e2; }
    .materials { list-style: none; margin: 6px 0 0; padding: 0; display: flex; flex-direction: column; gap: 4px; }
    .materials a { display: flex; align-items: center; gap: 8px; padding: 6px 8px; border-radius: 8px; background: color-mix(in oklab, var(--brand) 14%, transparent); color: #fff; text-decoration: none; font-size: 13px; }
    .materials a:hover, .materials a:focus-visible { background: color-mix(in oklab, var(--brand) 28%, transparent); }
    .materials svg { flex: none; width: 16px; height: 16px; }
    .materials span { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .materials em { font-style: normal; font-size: 11px; color: var(--muted); white-space: nowrap; }

    /* Notices: an outside meeting opened beside the mushaf, or the lesson left. */
    .notice { margin: auto; width: 100%; max-width: 480px; background: var(--panel); border-radius: 16px; padding: 24px; display: flex; flex-direction: column; gap: 14px; text-align: center; }
    .notice h1 { margin: 0; font-size: 20px; font-weight: 500; }
    .notice .row { justify-content: center; }
    .notice button, .notice a.button { border-radius: 999px; }

    /* Wide screens: the panel sits beside the stage (always before joining; in the call, while it is open). */
    @media (min-width: 1000px) {
        body:not([data-state="live"]) main.with-plan, body[data-state="live"][data-panel="open"] main.with-plan { grid-template-columns: minmax(0, 1fr) 340px; }
        body:not([data-state="live"]) main.with-mushaf, body[data-state="live"][data-panel="open"] main.with-mushaf { grid-template-columns: minmax(0, 1fr) 440px; }
        body[data-state="live"]:not([data-panel="open"]) aside { display: none; }
    }
    /* Narrow screens: before joining the panel follows the card; in the call it slides over the stage. */
    @media (max-width: 999px) {
        body:not([data-state="live"]) main.with-plan { overflow-y: auto; grid-auto-rows: max-content; align-content: start; }
        body:not([data-state="live"]) main.with-plan aside { overflow: visible; }
        body[data-state="live"] aside { position: fixed; z-index: 30; inset-block: 0; inset-inline-end: 0; width: min(100%, 440px); border-radius: 0; box-shadow: 0 0 40px rgba(0,0,0,.6); transform: translateX(105%); visibility: hidden; }
        [dir="rtl"] body[data-state="live"] aside { transform: translateX(-105%); }
        body[data-state="live"][data-panel="open"] aside { transform: none; visibility: visible; }
        .prejoin { grid-template-columns: minmax(0, 1fr); gap: 18px; max-width: 560px; }
        .ready { text-align: center; } .ready .row { justify-content: center; }
    }
    @media (max-width: 640px) {
        .topbar { gap: 8px; padding: 0 10px; }
        .topbar .brand, .topbar .lesson small, .concern .label, .people .label { display: none; }
        main { padding: 0 6px 6px; }
        .prejoin { padding: 8px; }
        .ready h1 { font-size: 21px; }
        .bar { gap: 8px; }
        .bar .wide { display: none !important; }
        .pop .narrow { display: flex !important; }
        .pop { position: fixed; inset-inline: 8px; bottom: calc(66px + env(safe-area-inset-bottom)); min-width: 0; }
        .round { width: 44px; height: 44px; }
        .leave { height: 44px; padding: 0 16px; }
        .leave .label { display: none; }
    }

    /* Picture in picture (the floating window). */
    body.pip { height: 100vh; display: block; position: relative; background: #000; }
    .pip-stage { position: absolute; inset: 0; background: var(--tile); }
    .pip-stage video { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: contain; opacity: 0; background: #000; }
    .pip-stage.has-video video { opacity: 1; }
    .pip-stage.local:not(.screen) video { transform: scaleX(-1); }
    .pip-stage .avatar { position: absolute; inset: 0; display: grid; place-items: center; }
    .pip-stage .avatar span { width: 72px; height: 72px; border-radius: 50%; display: grid; place-items: center; font-size: 30px; font-weight: 600; color: #fff; background: hsl(var(--hue, 200) 45% 42%); }
    .pip-stage.has-video .avatar { display: none; }
    .pip-stage .name { position: absolute; inset-inline-start: 8px; top: 8px; max-width: 55%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; background: rgba(0,0,0,.55); padding: 2px 10px; border-radius: 999px; font-size: 12px; }
    .pip-bar { position: absolute; inset-inline: 0; bottom: 10px; display: flex; justify-content: center; gap: 10px; }
    .pip-bar .round { width: 40px; height: 40px; }
    .pip-bar .leave { height: 40px; padding: 0 14px; }

    /* Motion: transform and opacity only. */
    @media (prefers-reduced-motion: no-preference) {
        .rec::before { animation: rec-pulse 1.4s ease-in-out infinite; }
        @keyframes rec-pulse { 50% { opacity: .3; } }
        .tile { animation: tile-in .28s cubic-bezier(.2,.8,.2,1) backwards; }
        @keyframes tile-in { from { opacity: 0; transform: scale(.94); } }
        .tile.moving { transition: transform .3s cubic-bezier(.2,.8,.2,1); }
        .tile video, .tile::after { transition: opacity .2s; }
        .tile.speaking::after { animation: speak 1.2s ease-in-out infinite; }
        @keyframes speak { 50% { opacity: .55; } }
        .meter i { transition: transform .1s linear; }
        .pop { animation: pop-in .16s ease-out backwards; transform-origin: bottom; }
        @keyframes pop-in { from { opacity: 0; transform: translateY(6px) scale(.98); } }
        .prejoin, .notice { animation: rise .35s cubic-bezier(.2,.8,.2,1) backwards; }
        @keyframes rise { from { opacity: 0; transform: translateY(10px); } }
        .round, .leave, button { transition: transform .12s; }
        .round:active, .leave:active { transform: scale(.94); }
        @media (max-width: 999px) { body[data-state="live"] aside { transition: transform .26s cubic-bezier(.2,.8,.2,1), visibility .26s; } }
        @media (min-width: 1000px) { body[data-state="live"][data-panel="open"] aside { animation: panel-in .26s cubic-bezier(.2,.8,.2,1) backwards; } }
        @keyframes panel-in { from { opacity: 0; transform: translateY(8px); } }
    }
</style>
