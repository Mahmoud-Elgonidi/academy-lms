{{-- The family games world: islands, quests, the round's stage and its result (Filament colour variables; islands keep their own sky). --}}
<style>
    .gw { display: flex; flex-direction: column; gap: 1.2rem; }
    .gw-section { display: flex; flex-direction: column; gap: .7rem; }
    .gw-section > h2 { font-weight: 800; font-size: 1.1rem; }
    .gw-empty, .gw-soon { color: var(--gray-500); font-size: .9rem; padding: .6rem 0; }
    .gw-note { padding: .7rem 1rem; border-radius: 1rem; background: var(--warning-50); color: var(--warning-800); font-size: .9rem; }
    .gw-hero { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .6rem; }
    .gw-hero-item { display: flex; flex-direction: column; align-items: center; gap: .1rem; padding: .8rem .4rem; border-radius: 1.2rem; background: white; border: 1px solid var(--gray-200); }
    .gw-hero-item span { font-size: 1.6rem; }
    .gw-hero-item strong { font-size: 1.35rem; font-weight: 800; font-variant-numeric: tabular-nums; }
    .gw-hero-item small { color: var(--gray-500); font-size: .72rem; text-align: center; }
    .gw-quests { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 15rem), 1fr)); gap: .7rem; }
    .gw-quest { position: relative; display: flex; flex-direction: column; gap: .4rem; align-items: flex-start; padding: 1rem; border-radius: 1.2rem; background: white; border: 2px solid var(--primary-100); }
    .gw-quest[data-reason="homework"] { border-color: var(--warning-300); background: linear-gradient(160deg, var(--warning-50), white 60%); }
    .gw-quest[data-reason="review"] { border-color: var(--info-200, var(--primary-200)); }
    .gw-quest[data-done] { opacity: .8; }
    .gw-quest h3 { font-weight: 800; font-size: 1rem; line-height: 1.5; overflow-wrap: anywhere; }
    .gw-quest small { color: var(--gray-600); font-size: .8rem; }
    .gw-chip { font-size: .7rem; font-weight: 700; padding: .15rem .6rem; border-radius: 999px; background: var(--primary-50); color: var(--primary-700); }
    .gw-quest[data-reason="homework"] .gw-chip { background: var(--warning-100); color: var(--warning-800); }
    .gw-tick { position: absolute; top: .8rem; inset-inline-end: .8rem; font-size: .75rem; font-weight: 700; color: var(--success-700); }
    .gw-islands { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 19rem), 1fr)); gap: .8rem; align-items: start; }
    .gw-island { border-radius: 1.4rem; overflow: hidden; color: white; display: flex; flex-direction: column; box-shadow: 0 10px 24px -14px rgba(0, 0, 0, .45); }
    .gw-island header { display: flex; gap: .7rem; align-items: center; padding: 1rem 1rem .6rem; }
    .gw-island header div { flex: 1; min-width: 0; }
    .gw-island h3 { font-weight: 800; font-size: 1.05rem; text-shadow: 0 2px 4px rgba(0, 0, 0, .25); }
    .gw-island header small { display: block; font-size: .75rem; opacity: .95; line-height: 1.5; text-shadow: 0 1px 3px rgba(0, 0, 0, .3); }
    .gw-island-emoji { font-size: 2.5rem; filter: drop-shadow(0 4px 6px rgba(0, 0, 0, .25)); }
    .gw-count { font-size: .75rem; font-weight: 800; padding: .2rem .55rem; border-radius: 999px; background: rgba(255, 255, 255, .25); }
    .gw-creatures { display: flex; gap: .45rem; padding: 0 1rem .8rem; flex-wrap: wrap; }
    .gw-creatures span { width: 2.6rem; height: 2.6rem; display: grid; place-items: center; font-size: 1.5rem; border-radius: 50%; background: rgba(255, 255, 255, .85); color: var(--gray-400); font-weight: 800; border: 2px solid transparent; }
    .gw-creatures span[data-found="no"] { background: rgba(255, 255, 255, .25); color: rgba(255, 255, 255, .85); font-size: 1.1rem; }
    .gw-creatures span[data-found="yes"][data-rarity="rare"] { border-color: #8ec5ff; }
    .gw-creatures span[data-found="yes"][data-rarity="legendary"] { border-color: #ffd23f; box-shadow: 0 0 0 3px rgba(255, 210, 63, .35); }
    .gw-games { display: flex; flex-direction: column; gap: .45rem; padding: .7rem; background: rgba(255, 255, 255, .92); color: var(--gray-900); flex: 1; }
    .gw-game { display: flex; gap: .6rem; align-items: center; justify-content: space-between; padding: .55rem .7rem; border-radius: .9rem; background: var(--gray-50); }
    .gw-game div { min-width: 0; display: flex; flex-direction: column; }
    .gw-game strong { font-weight: 700; font-size: .9rem; }
    .gw-game small { color: var(--gray-600); font-size: .74rem; line-height: 1.5; }
    .gw-game[data-locked] { opacity: .65; }
    .gw-recent { display: flex; flex-direction: column; gap: .3rem; }
    .gw-recent li { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: .1rem .6rem; padding: .5rem .7rem; border-radius: .8rem; background: white; border: 1px solid var(--gray-200); font-size: .85rem; }
    .gw-recent li span:nth-child(2) { color: var(--warning-500); letter-spacing: .1em; }
    .gw-recent li small { grid-column: 1 / -1; color: var(--gray-500); font-size: .74rem; }
    .gw-limits { padding: 1rem; border-radius: 1.2rem; background: white; border: 1px solid var(--gray-200); }
    .gw-limits p { font-size: .85rem; color: var(--gray-600); }
    .gw-limits label { display: flex; gap: .5rem; align-items: center; font-size: .9rem; }
    .gw-limits select { border-radius: .6rem; border: 1px solid var(--gray-300); padding: .3rem .6rem; background: white; }
    .gw-result { display: flex; flex-direction: column; align-items: center; gap: .6rem; padding: 1.4rem 1rem; border-radius: 1.6rem; text-align: center;
        background: radial-gradient(circle at 50% 0%, var(--warning-50), white 70%); border: 2px solid var(--warning-200); }
    .gw-result h2 { font-weight: 900; font-size: 1.5rem; }
    .gw-result p { color: var(--gray-700); }
    .gw-stars { display: flex; gap: .4rem; font-size: 3rem; line-height: 1; }
    .gw-stars span[data-on="yes"] { color: #ffb703; text-shadow: 0 4px 0 #e08e00; }
    .gw-stars span[data-on="no"] { color: var(--gray-200); }
    .gw-hatch { display: flex; gap: .8rem; align-items: center; padding: .8rem 1.1rem; border-radius: 1.2rem; background: white; border: 2px solid var(--primary-200); position: relative; }
    .gw-hatch[data-rarity="legendary"] { border-color: #ffd23f; }
    .gw-hatch div { display: flex; flex-direction: column; align-items: flex-start; }
    .gw-hatch small { color: var(--gray-500); }
    .gw-egg { font-size: 2.2rem; position: absolute; inset-inline-start: .8rem; opacity: 0; }
    .gw-born { font-size: 2.6rem; }
    .gw-done { font-weight: 700; color: var(--success-700); }
    .gw-egg-bar { width: min(100%, 22rem); display: flex; flex-direction: column; gap: .3rem; font-size: .8rem; color: var(--gray-600); }
    .gw-egg-bar i { display: block; height: .55rem; border-radius: 999px; background: var(--gray-100); overflow: hidden; }
    .gw-egg-bar b { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, #ffd23f, #fb8500); }
    .gw-actions { display: flex; gap: .5rem; flex-wrap: wrap; justify-content: center; }
    .gw-stage { display: flex; flex-direction: column; gap: .6rem; }
    .gw-stage-bar { display: flex; gap: .8rem; align-items: center; }
    .gw-stage-bar div { display: flex; flex-direction: column; min-width: 0; }
    .gw-stage-bar strong { font-weight: 800; }
    .gw-stage-bar small { color: var(--gray-500); font-size: .8rem; }
    .gw-frame { width: 100%; height: min(78vh, 820px); border-radius: 1.4rem; overflow: hidden; background: var(--gray-900); box-shadow: 0 18px 40px -18px rgba(0, 0, 0, .5); }
    .gw-frame .lms-game-frame { width: 100%; height: 100%; border: 0; display: block; }
    .gw[data-look="kids"] .gw-quest, .gw[data-look="kids"] .gw-hero-item { border-radius: 1.6rem; }
    .gw[data-look="kids"] .gw-quest h3 { font-size: 1.1rem; }
    .gw[data-look="classic"] .gw-island, .gw[data-look="teen"] .gw-island { border-radius: 1rem; }
    @media (max-width: 640px) { .gw-hero { grid-template-columns: repeat(2, minmax(0, 1fr)); } .gw-frame { height: max(420px, calc(100dvh - 330px)); border-radius: 1rem; } }
    .dark .gw-hero-item, .dark .gw-quest, .dark .gw-recent li, .dark .gw-limits { background: var(--gray-900); border-color: var(--gray-700); }
    .dark .gw-games { background: rgba(17, 24, 39, .92); color: var(--gray-100); }
    .dark .gw-game { background: var(--gray-800); }
    .dark .gw-result { background: var(--gray-900); }
    @media (prefers-reduced-motion: no-preference) {
        .gw-stars span { animation: gw-star .6s cubic-bezier(.3, 1.6, .5, 1) backwards; animation-delay: calc(var(--i) * 180ms); }
        .gw-hatch .gw-egg { animation: gw-egg 1.1s ease-out backwards; }
        .gw-hatch .gw-born { animation: gw-born .7s cubic-bezier(.3, 1.6, .5, 1) .9s backwards; }
        .gw-island-emoji { animation: gw-bob 3.2s ease-in-out infinite; }
        .gw-creatures span[data-found="yes"] { animation: gw-pop .5s cubic-bezier(.3, 1.6, .5, 1) backwards; }
    }
    @keyframes gw-star { from { transform: scale(0) rotate(-40deg); opacity: 0; } }
    @keyframes gw-egg { 0% { opacity: 1; transform: scale(1); } 40% { transform: rotate(-12deg); opacity: 1; } 60% { transform: rotate(10deg); opacity: 1; } 100% { transform: scale(1.4); opacity: 0; } }
    @keyframes gw-born { from { transform: scale(0); opacity: 0; } }
    @keyframes gw-bob { 50% { transform: translateY(-5px) rotate(-3deg); } }
    @keyframes gw-pop { from { transform: scale(.4); opacity: 0; } }
</style>
