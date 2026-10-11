{{-- The middle and teen games screen (the approved designs, Middle and Teen: a dark screen in the look's own palette) and the guardian's (Guardian: calm cards in the academy's colours). --}}
<style>
    .gw-hub { --hb-bg: #0B1222; --hb-card: #111A2E; --hb-line: #263248; --hb-muted: #94A3B8; --hb-accent: #C6F432; --hb-ink: #0B1222; --hb-alt: #8B7CF6;
        position: relative; max-width: 560px; margin-inline: auto; padding: 0 12px 24px; border-radius: 26px; background: var(--hb-bg); color: #F1F5F9; display: flex; flex-direction: column; gap: 12px; overflow: hidden; }
    .gw-hub[data-look="middle"] { --hb-bg: #1B1446; --hb-card: #241A5C; --hb-line: #4A3FA0; --hb-muted: #B9B2F0; --hb-accent: #A9E34B; --hb-ink: #0B0726; --hb-alt: #63E6BE; }
    @media (max-width: 639.98px) { .gw-hub { width: 100vw; margin-inline: calc(50% - 50vw); margin-top: -3rem; padding-top: 14px; border-radius: 0; min-height: calc(100dvh - 4rem); box-sizing: border-box; } }
    .gw-hub button { font: inherit; color: inherit; }
    .gw-hub-head { min-height: 70px; padding: 0 4px; display: flex; align-items: center; justify-content: space-between; gap: 10px; }
    .gw-hub-head h1 { margin: 0; font-size: 21px; font-weight: 800; line-height: 1.25; overflow-wrap: anywhere; }
    .gw-hub-kicker { font-size: 12.5px; font-weight: 600; color: var(--hb-muted); }
    .gw-hub-pill { flex: none; height: 38px; padding: 0 12px; border-radius: 12px; background: var(--hb-card); border: 2px solid var(--hb-line); display: flex; align-items: center; gap: 6px; font-weight: 800; font-size: 15px; }
    .gw-hub-note { margin: 0; padding: 10px 14px; border-radius: 14px; background: var(--hb-card); border: 1px dashed var(--hb-accent); font-weight: 700; font-size: 14px; }
    .gw-hub-card { margin: 0; padding: 12px 14px; border-radius: 18px; background: var(--hb-card); border: 2px solid var(--hb-line); }
    .gw-hub[data-look="teen"] .gw-hub-card, .gw-hub[data-look="classic"] .gw-hub-card { border-width: 1px; border-radius: 16px; }
    .gw-hub-card h2 { margin: 0; font-size: 16px; font-weight: 700; }
    .gw-hub-ends { display: flex; justify-content: space-between; font-size: 12.5px; font-weight: 600; color: var(--hb-muted); margin-bottom: 6px; }
    .gw-track { position: relative; height: 128px; }
    .gw-track > i { position: absolute; left: 0; right: 0; height: 2px; background: color-mix(in oklab, var(--hb-line) 80%, transparent); }
    .gw-flag { position: absolute; left: 0; top: 4px; }
    .gw-ghost, .gw-racer { position: absolute; width: 70px; text-align: center; transform: translateX(-10%); }
    .gw-ghost { top: 0; opacity: .5; }
    .gw-racer { top: 60px; }
    .gw-ghost small, .gw-racer small { display: block; font-size: 11px; font-weight: 700; color: var(--hb-muted); }
    .gw-racer small { color: var(--hb-alt); }
    .gw-timebar { display: flex; align-items: center; gap: 10px; margin-top: 4px; }
    .gw-timebar > div { flex: 1; height: 10px; border-radius: 999px; background: color-mix(in oklab, var(--hb-bg) 70%, black); overflow: hidden; }
    .gw-timebar b { display: block; height: 100%; background: #3BC9DB; }
    .gw-timebar span { font-size: 15px; font-weight: 800; min-width: 40px; }
    .gw-hub-ready { text-align: center; }
    .gw-hub-ready p { margin: 0 0 4px; font-size: 16px; font-weight: 600; color: color-mix(in oklab, white 85%, var(--hb-muted)); }
    .gw-hub-big { font-size: 26px !important; font-weight: 800 !important; color: #FFFFFF !important; margin-bottom: 14px !important; }
    .gw-hub-go { width: 100%; height: 58px; border-radius: 16px; background: var(--hb-accent); border: 2px solid var(--hb-ink); box-shadow: 0 5px 0 var(--hb-ink); color: var(--hb-ink) !important; font-size: 21px; font-weight: 800; cursor: pointer; transition: transform .08s; }
    .gw-hub-go:active { transform: translateY(3px); box-shadow: 0 1px 0 var(--hb-ink); }
    .gw-hub-go:disabled { opacity: .5; cursor: default; }
    .gw-hub[data-look="teen"] .gw-hub-go, .gw-hub[data-look="classic"] .gw-hub-go { height: 50px; border-radius: 12px; border: 0; box-shadow: none; font-size: 17px; }
    .gw-hub-ghostbtn { width: 100%; height: 50px; border-radius: 14px; background: transparent; border: 2px solid var(--hb-line); font-size: 16px; font-weight: 700; cursor: pointer; }
    .gw-hub-row { display: flex; flex-direction: column; gap: 8px; margin-top: 12px; }
    .gw-hub-over { padding: 18px; border-radius: 20px; background: #FFFFFF; color: var(--hb-ink); text-align: center; }
    .gw-hub-over h2 { margin: 2px 0 6px; font-size: 24px; font-weight: 800; line-height: 1.2; }
    .gw-hub-over p { margin: 4px 0; font-weight: 600; }
    .gw-hub-over .gw-hub-ghostbtn { border-color: color-mix(in oklab, var(--hb-ink) 25%, transparent); }
    .gw-hub-over-kicker { font-size: 14px; font-weight: 800; color: #5C4A99; }
    .gw-hub-over .gw-hub-sub { color: #5C4A99; }
    .gw-hub-hatch { display: flex; align-items: center; justify-content: center; gap: 8px; }
    .gw-hub-stars { display: flex; justify-content: center; gap: 4px; font-size: 34px; line-height: 1; }
    .gw-hub-stars [data-on="yes"] { color: #FCC419; } .gw-hub-stars [data-on="no"] { color: #DEE2E6; }
    .gw-hub-sub { font-size: 12.5px; color: var(--hb-muted); }
    .gw-const { position: relative; height: 160px; margin-top: 6px; }
    .gw-const .gw-lit { filter: drop-shadow(0 0 6px var(--hb-accent)); }
    .gw-hub-deck { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 26px 18px 18px; border-radius: 18px; background: var(--hb-card); border: 1px solid #334155; text-align: center; }
    .gw-hub-deck strong { font-size: 26px; font-weight: 700; line-height: 1.35; }
    .gw-hub-h2 { margin: 6px 4px 0; font-size: 15px; font-weight: 700; color: var(--hb-muted); }
    .gw-hub-list { display: flex; flex-direction: column; gap: 8px; }
    .gw-hub-item { display: flex; flex-direction: column; align-items: flex-start; gap: 3px; padding: 12px 14px; border-radius: 16px; background: var(--hb-card); border: 1px solid var(--hb-line); text-align: start; cursor: pointer; }
    .gw-hub-item:disabled { opacity: .55; cursor: default; }
    .gw-hub-item strong { font-size: 16px; font-weight: 700; line-height: 1.4; }
    .gw-hub-item small { font-size: 12.5px; color: var(--hb-muted); }
    .gw-hub-tag { font-size: 11.5px; font-weight: 700; padding: 1px 8px; border-radius: 999px; background: color-mix(in oklab, var(--hb-alt) 20%, transparent); color: var(--hb-alt); }
    .gw-hub-tag[data-reason="homework"] { background: color-mix(in oklab, #FF922B 22%, transparent); color: #FFA94D; }
    .gw-hub-games { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
    .gw-hub-game { width: 100%; display: flex; flex-direction: column; align-items: flex-start; gap: 2px; padding: 12px; border-radius: 16px; background: var(--hb-card); border: 1px solid var(--hb-line); text-align: start; cursor: pointer; }
    .gw-hub-game:disabled { opacity: .5; cursor: default; }
    .gw-hub-game strong { font-size: 14.5px; font-weight: 700; }
    .gw-hub-game small { font-size: 12px; color: var(--hb-accent); }
    .gw-hub-emoji { font-size: 22px; }
    .gw-hub-recent { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 6px; }
    .gw-hub-recent li { display: grid; grid-template-columns: 1fr auto; gap: 2px 8px; padding: 10px 12px; border-radius: 14px; background: var(--hb-card); border: 1px solid var(--hb-line); font-size: 14px; }
    .gw-hub-recent b { color: #FCC419; font-weight: 400; }
    .gw-hub-recent small { grid-column: 1 / -1; color: var(--hb-muted); font-size: 12px; }
    .gw-empty { color: var(--gray-500); font-size: .9rem; padding: .6rem 0; }
    .gw-guard { max-width: 560px; margin-inline: auto; display: flex; flex-direction: column; gap: 12px; color: var(--gray-900); }
    .gw-guard-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; min-height: 56px; }
    .gw-guard-head h1 { margin: 0; font-size: 20px; font-weight: 700; }
    .gw-guard-head [role="tablist"] { display: flex; gap: 6px; flex-wrap: wrap; justify-content: flex-end; }
    .gw-guard-head [role="tab"] { height: 38px; padding: 0 14px; border-radius: 999px; border: 1px solid var(--gray-200); background: white; font-weight: 500; font-size: 14.5px; cursor: pointer; }
    .gw-guard-head [role="tab"][aria-selected="true"] { background: var(--primary-600); border-color: var(--primary-600); color: white; font-weight: 600; }
    .gw-guard-card { padding: 14px; border-radius: 18px; background: white; border: 1px solid var(--gray-200); }
    .gw-guard-card h2 { margin: 0; font-size: 16px; font-weight: 600; }
    .gw-guard-card p { margin: 2px 0 0; font-size: 13.5px; color: var(--gray-600); }
    .gw-guard-warn { color: var(--warning-700) !important; font-weight: 600; }
    .gw-guard-label { margin-top: 12px; font-size: 13.5px; font-weight: 600; }
    .gw-guard-chips { margin-top: 6px; display: grid; gap: 6px; }
    .gw-guard-chips button { height: 44px; border-radius: 12px; font-size: 14px; font-weight: 600; background: white; border: 1px solid var(--gray-300); cursor: pointer; }
    .gw-guard-chips button[aria-checked="true"] { background: var(--primary-600); border-color: var(--primary-600); color: white; }
    .gw-guard-switch { margin-top: 8px; min-height: 48px; display: flex; align-items: center; justify-content: space-between; gap: 10px; border-top: 1px solid var(--gray-200); cursor: pointer; font-size: 14px; }
    .gw-guard-switch input { width: 22px; height: 22px; accent-color: var(--primary-600); }
    .gw-guard-lines { display: flex; flex-direction: column; gap: 8px; }
    .gw-guard-lines > div { display: flex; align-items: center; gap: 10px; font-size: 14.5px; }
    .gw-ico { flex: none; width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; }
    .gw-guard-muted { font-size: 13px; color: var(--gray-500); }
    .gw-guard-hatch { display: flex; align-items: center; gap: 12px; }
    .gw-guard-pet { position: relative; flex: none; width: 76px; height: 72px; border-radius: 16px; background: #FFF0D6; display: flex; align-items: center; justify-content: center; }
    .gw-hearts { position: absolute; top: -6px; left: 0; right: 0; display: flex; justify-content: center; gap: 4px; pointer-events: none; }
    .gw-hearts i { font-style: normal; font-size: 14px; }
    .gw-guard-btn { flex: none; height: 44px; padding: 0 14px; border-radius: 12px; border: 0; color: white !important; font-weight: 600; font-size: 14.5px; background: var(--primary-600); cursor: pointer; }
    .gw-guard-btn:disabled { opacity: .5; }
    .gw-guard-safe { display: flex; align-items: flex-start; gap: 10px; padding: 10px 12px; border-radius: 14px; background: var(--success-50); color: var(--success-800); font-size: 13.5px; line-height: 1.5; }
    .dark .gw-guard { color: var(--gray-100); }
    .dark .gw-guard-card, .dark .gw-guard-head [role="tab"], .dark .gw-guard-chips button { background: var(--gray-900); border-color: var(--gray-700); }
    .dark .gw-guard-pet { background: var(--gray-800); }
    @media (prefers-reduced-motion: no-preference) {
        .gw-ring { transition: stroke-dasharray .9s cubic-bezier(.2, .8, .3, 1); }
        .gw-hearts i { animation: gw-heart 1.1s ease-out both; }
        .gw-hearts i:nth-child(2) { animation-delay: .15s; } .gw-hearts i:nth-child(3) { animation-delay: .3s; }
        .gw-float { animation: gw-bob 2s ease-in-out infinite; }
        .gw-hub-over { animation: gw-popin .35s cubic-bezier(.3, 1.6, .55, 1) backwards; }
        .gw-hub-stars > * { display: inline-block; animation: gw-starpop .5s cubic-bezier(.3, 1.8, .5, 1) calc(.2s + var(--i) * .25s) backwards; }
        .gw-const .gw-lit { animation: gw-popin .4s cubic-bezier(.3, 1.6, .55, 1) calc(var(--i) * 60ms) backwards; transform-box: fill-box; transform-origin: center; }
        .gw-racer { animation: gw-rise .6s cubic-bezier(.3, 1.2, .5, 1) backwards; }
    }
    @keyframes gw-heart { 0% { transform: translateY(0) scale(.4); opacity: 0; } 30% { opacity: 1; transform: translateY(-8px) scale(1.1); } 100% { transform: translateY(-34px) scale(.9); opacity: 0; } }
</style>
