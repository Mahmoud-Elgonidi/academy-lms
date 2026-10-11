{{-- The games studio: islands, games, drafts, the phone preview and the conversation (Filament colour variables). --}}
<style>
    .gs, .gd { display: flex; flex-direction: column; gap: 1.4rem; }
    .gs-section { display: flex; flex-direction: column; gap: .7rem; }
    .gs-section > h2 { font-weight: 800; font-size: 1.05rem; }
    .gs-note { display: flex; gap: .55rem; align-items: flex-start; padding: .8rem 1rem; border-radius: var(--radius-xl, .75rem); background: var(--warning-50); color: var(--warning-800); font-size: .88rem; border: 1px solid var(--warning-200); }
    .gs-note-icon { width: 1.15rem; height: 1.15rem; flex-shrink: 0; margin-top: .1rem; }
    .gs-state { flex-shrink: 0; font-size: .7rem; font-weight: 700; padding: .15rem .6rem; border-radius: 999px; background: var(--gray-100); color: var(--gray-700); white-space: nowrap; }
    .gs-state[data-state="live"], .gs-state[data-state="ready"] { background: var(--success-50); color: var(--success-700); }
    .gs-state[data-state="designing"] { background: var(--primary-50); color: var(--primary-700); }
    .gs-state[data-state="failed"], .gs-state[data-state="retired"] { background: var(--danger-50); color: var(--danger-700); }
    .gs-draft { display: flex; flex-direction: column; gap: .5rem; padding: 1rem; color: inherit; }
    .gs-draft header { display: flex; justify-content: space-between; gap: .5rem; align-items: flex-start; }
    .gs-draft h3 { font-weight: 700; overflow-wrap: anywhere; }
    .gs-draft p { font-size: .85rem; color: var(--gray-600); line-height: 1.6; }
    .gs-draft footer { display: flex; justify-content: space-between; gap: .5rem; font-size: .76rem; color: var(--gray-500); }
    .gs-world { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 24rem), 1fr)); gap: .9rem; }
    .gs-island { overflow: hidden; display: flex; flex-direction: column; }
    .gs-island-head { position: relative; display: flex; gap: .8rem; align-items: center; padding: 1.1rem 1rem; color: white; min-height: 6.4rem; }
    .gs-island-head::after { content: ""; position: absolute; inset: auto 0 0 0; height: 40%; background: linear-gradient(transparent, rgba(0, 0, 0, .18)); pointer-events: none; }
    .gs-island-emoji { font-size: 2.6rem; filter: drop-shadow(0 4px 6px rgba(0, 0, 0, .25)); position: relative; z-index: 1; }
    .gs-island-head div { position: relative; z-index: 1; min-width: 0; }
    .gs-island-head h3 { font-weight: 800; font-size: 1.1rem; text-shadow: 0 2px 4px rgba(0, 0, 0, .3); }
    .gs-island-head p { font-size: .8rem; opacity: .95; line-height: 1.5; text-shadow: 0 1px 3px rgba(0, 0, 0, .35); }
    .gs-creatures { display: flex; gap: .4rem; padding: .7rem 1rem 0; flex-wrap: wrap; }
    .gs-creatures span { font-size: 1.45rem; width: 2.4rem; height: 2.4rem; display: grid; place-items: center; border-radius: 999px; background: var(--gray-50); border: 2px solid var(--gray-100); }
    .gs-creatures span[data-rarity="rare"] { border-color: var(--info-300, var(--primary-300)); }
    .gs-creatures span[data-rarity="legendary"] { border-color: var(--warning-400); box-shadow: 0 0 0 3px var(--warning-50); }
    .gs-games { display: flex; flex-direction: column; gap: .7rem; }
    .gs-game { display: flex; gap: 1rem; align-items: center; padding: .75rem; }
    .gs-game[data-status="retired"] { opacity: .65; }
    .gs-game-art { flex: none; width: 7.5rem; height: 5.6rem; border-radius: var(--radius-lg, .5rem); overflow: hidden; position: relative; display: block; }
    .gs-game-art > span { position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%) scale(.54); display: block; }
    .gs-game-main { min-width: 0; flex: 1; display: flex; flex-direction: column; gap: .3rem; }
    .gs-game-main > div:first-child { display: flex; gap: .5rem; align-items: center; flex-wrap: wrap; }
    .gs-game-main strong { font-weight: 800; font-size: 1rem; }
    .gs-game-main a:hover strong { color: var(--primary-600); }
    .gs-game-main small { color: var(--gray-500); font-size: .76rem; }
    .gs-game-main p { font-size: .84rem; color: var(--gray-600); line-height: 1.55; }
    .gs-tags { display: flex; flex-wrap: wrap; gap: .3rem; }
    .gs-tags span { font-size: .7rem; font-weight: 600; padding: .1rem .55rem; border-radius: 999px; background: var(--gray-50); border: 1px solid var(--gray-200); color: var(--gray-600); }
    .gs-tags span[data-kind] { background: var(--primary-50); border-color: var(--primary-200); color: var(--primary-700); }
    .gs-game-actions { display: flex; flex-wrap: wrap; gap: .4rem; justify-content: flex-end; flex: none; max-width: 24rem; }
    @media (max-width: 760px) { .gs-game { flex-wrap: wrap; } .gs-game-actions { max-width: none; width: 100%; justify-content: flex-start; } }
    .gs-empty { font-size: .85rem; color: var(--gray-500); padding: 1rem; text-align: center; }
    .gs-stages { display: flex; gap: .35rem; flex-wrap: wrap; justify-content: center; }
    .gs-stages button { font-size: .78rem; font-weight: 600; padding: .3rem .8rem; border-radius: 999px; border: 1px solid var(--gray-200); background: white; color: var(--gray-700); }
    .gs-stages button[aria-pressed="true"] { background: var(--primary-600); border-color: var(--primary-600); color: white; }
    .gs-phone { width: min(100%, 390px); height: min(76vh, 760px); margin-inline: auto; border-radius: 2.2rem; border: .6rem solid var(--gray-900); background: var(--gray-900); overflow: hidden; box-shadow: 0 24px 48px -16px rgba(0, 0, 0, .35); }
    .gs-player, .lms-game-frame { width: 100%; height: 100%; border: 0; display: block; background: var(--gray-100); }
    .gs-hint { text-align: center; font-size: .78rem; color: var(--gray-500); }
    .gd-grid { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1rem; align-items: start; }
    @media (min-width: 1100px) { .gd-grid { grid-template-columns: minmax(0, 1fr) minmax(0, 1.1fr); } }
    .gd-play { padding: 1rem; display: flex; flex-direction: column; gap: .8rem; }
    .gd-play > header { display: flex; justify-content: space-between; gap: .6rem; align-items: center; flex-wrap: wrap; }
    .gd-play > header h3 { font-weight: 700; }
    .gd-tools { display: flex; gap: .35rem; }
    .gd-waiting { height: min(76vh, 760px); display: grid; place-items: center; text-align: center; border-radius: 1.4rem; background: linear-gradient(160deg, var(--primary-50), var(--gray-50)); color: var(--primary-700); padding: 2rem; }
    .gd-waiting b { display: block; font-size: 1.1rem; margin-top: .8rem; }
    .gd-waiting small { display: block; color: var(--gray-500); margin-top: .3rem; font-size: .8rem; }
    .gd-orbit { width: 5.5rem; height: 5.5rem; margin: 0 auto; position: relative; }
    .gd-orbit i { position: absolute; inset: 0; border-radius: 50%; border: 3px solid transparent; border-top-color: var(--primary-500); }
    .gd-orbit i:nth-child(2) { inset: .8rem; border-top-color: var(--warning-400); }
    .gd-orbit span { position: absolute; inset: 0; display: grid; place-items: center; font-size: 1.9rem; }
    .gd-events { display: flex; flex-direction: column; gap: .4rem; border-top: 1px solid var(--gray-100); padding-top: .7rem; }
    .gd-events h4 { font-weight: 700; font-size: .85rem; display: flex; justify-content: space-between; align-items: center; gap: .5rem; }
    .gd-events ol { display: flex; flex-direction: column; gap: .2rem; max-height: 11rem; overflow: auto; font-size: .78rem; }
    .gd-events li { display: flex; gap: .4rem; color: var(--gray-700); }
    .gd-events li[data-kind="right"] { color: var(--success-700); }
    .gd-events li[data-kind="wrong"], .gd-events li[data-kind="error"] { color: var(--danger-700); }
    .gd-events p { font-size: .78rem; color: var(--gray-500); }
    .gd-side { display: flex; flex-direction: column; gap: 1rem; }
    .gd-box { padding: 1rem; display: flex; flex-direction: column; gap: .6rem; }
    .gd-box h3 { font-weight: 700; font-size: .95rem; }
    .gd-status { display: flex; gap: .7rem; align-items: center; }
    .gd-status[data-state="failed"] { color: var(--danger-700); }
    .gd-dots { display: inline-flex; gap: .25rem; }
    .gd-dots i { width: .45rem; height: .45rem; border-radius: 50%; background: var(--primary-500); }
    .gd-chat { display: flex; flex-direction: column; gap: .55rem; max-height: 26rem; overflow: auto; padding-inline-end: .2rem; }
    .gd-turn { display: flex; flex-direction: column; gap: .2rem; max-width: 92%; }
    .gd-turn p { white-space: pre-line; font-size: .86rem; line-height: 1.7; padding: .6rem .8rem; border-radius: 1rem; background: var(--gray-100); overflow-wrap: anywhere; }
    .gd-turn small { font-size: .7rem; color: var(--gray-500); padding-inline: .3rem; }
    .gd-turn[data-role="admin"] { align-self: flex-start; }
    .gd-turn[data-role="admin"] p { background: var(--primary-600); color: white; border-start-start-radius: .3rem; }
    .gd-turn[data-role="designer"] { align-self: flex-end; }
    .gd-turn[data-role="designer"] p { border-start-end-radius: .3rem; }
    .gd-form { display: flex; flex-direction: column; gap: .5rem; }
    .gd-form textarea { width: 100%; min-height: 6rem; border-radius: var(--radius-lg, .5rem); border: 1px solid var(--gray-300); padding: .6rem .75rem; font-size: .88rem; background: white; }
    .gd-form textarea:focus { outline: 2px solid var(--primary-500); outline-offset: 1px; }
    .gd-form footer { display: flex; justify-content: flex-end; gap: .4rem; }
    .gd-issues { display: flex; flex-direction: column; gap: .3rem; font-size: .82rem; }
    .gd-issues li { display: flex; flex-direction: column; padding: .45rem .6rem; border-radius: var(--radius-lg, .5rem); background: var(--danger-50); color: var(--danger-800); }
    .gd-issues code { font-size: .74rem; color: var(--danger-700); direction: ltr; text-align: start; unicode-bidi: isolate; overflow-wrap: anywhere; }
    .gd-facts { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: .35rem .8rem; font-size: .84rem; }
    .gd-facts dt { color: var(--gray-500); }
    .gd-facts dd { overflow-wrap: anywhere; }
    .gd-actions { display: flex; gap: .5rem; flex-wrap: wrap; }
    @media (prefers-reduced-motion: no-preference) {
        .gd-orbit i { animation: gd-spin 1.4s linear infinite; }
        .gd-orbit i:nth-child(2) { animation-duration: 2.1s; animation-direction: reverse; }
        .gd-orbit span { animation: gd-bob 1.8s ease-in-out infinite; }
        .gd-dots i { animation: gd-dot 1.2s ease-in-out infinite backwards; }
        .gd-dots i:nth-child(2) { animation-delay: .15s; }
        .gd-dots i:nth-child(3) { animation-delay: .3s; }
        .gd-turn { animation: gd-in .35s ease-out backwards; }
    }
    @keyframes gd-spin { to { transform: rotate(360deg); } }
    @keyframes gd-bob { 50% { transform: translateY(-6px) scale(1.06); } }
    @keyframes gd-dot { 0%, 100% { opacity: .25; transform: translateY(0); } 50% { opacity: 1; transform: translateY(-3px); } }
    @keyframes gd-in { from { opacity: 0; transform: translateY(8px); } }
    /* The studio's numbers, tabs and island cards (the approved design, Studio). */
    .gs-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 12rem), 1fr)); gap: .8rem; }
    .gs-stat { padding: .9rem 1.1rem; }
    .gs-stat small { display: block; font-size: .8rem; color: var(--gray-500); }
    .gs-stat b { display: block; font-size: 1.6rem; font-weight: 800; font-variant-numeric: tabular-nums; }
    .gs-stat span { font-size: .78rem; color: var(--gray-500); }
    .gs-stat span[data-good] { color: var(--success-700); }
    .gs-tabs { display: flex; gap: .3rem; border-bottom: 1px solid var(--gray-200); overflow-x: auto; }
    .gs-tabs button { padding: .6rem 1rem; font-weight: 600; font-size: .92rem; color: var(--gray-500); border-bottom: 3px solid transparent; white-space: nowrap; }
    .gs-tabs button[aria-selected="true"] { color: var(--primary-700); border-color: var(--primary-600); }
    .gs-icards { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 16rem), 1fr)); gap: .9rem; }
    .gs-icard { overflow: hidden; display: flex; flex-direction: column; }
    .gs-icard-art { position: relative; height: 8.2rem; display: flex; align-items: center; justify-content: center; overflow: hidden; }
    .gs-icard-art > .gs-icard-island { position: relative; transform: scale(.72); transform-origin: center; }
    .gs-icard-art .gs-pos, .gs-icard-art .gs-pub { position: absolute; top: .55rem; font-size: .7rem; font-weight: 800; padding: .1rem .55rem; border-radius: 999px; z-index: 2; }
    .gs-icard-art .gs-pos { inset-inline-start: .6rem; background: white; color: #13294B; }
    .gs-icard-art .gs-pub { inset-inline-end: .6rem; background: var(--success-100); color: var(--success-800); }
    .gs-icard-art .gs-pub[data-state="draft"] { background: var(--warning-100); color: var(--warning-800); }
    .gs-icard-body { display: flex; flex-direction: column; gap: .45rem; padding: .9rem 1rem 1rem; flex: 1; }
    .gs-icard-body h3 { font-weight: 800; font-size: 1.05rem; }
    .gs-chiprow { display: flex; flex-wrap: wrap; gap: .3rem; }
    .gs-chiprow span { font-size: .72rem; font-weight: 600; padding: .12rem .55rem; border-radius: 999px; background: var(--primary-50); color: var(--primary-700); }
    .gs-chiprow span[data-tone="green"] { background: var(--success-50); color: var(--success-700); }
    .gs-chiprow span[data-tone="orange"] { background: var(--warning-50); color: var(--warning-800); }
    .gs-icard-body p { font-size: .8rem; color: var(--gray-600); }
    .gs-icard-body footer { margin-top: auto; display: flex; gap: .5rem; padding-top: .4rem; }
    .gs-ccards { display: grid; grid-template-columns: repeat(auto-fill, minmax(8.5rem, 1fr)); gap: .7rem; }
    .gs-ccard { display: flex; flex-direction: column; align-items: center; gap: .2rem; padding: .8rem .5rem; text-align: center; }
    .gs-ccard strong { font-size: .9rem; } .gs-ccard small { font-size: .72rem; color: var(--gray-500); }
    .gs-ccard[data-rarity="rare"] { border-color: var(--info-300, var(--primary-300)); }
    .gs-ccard[data-rarity="legendary"] { border-color: var(--warning-400); }
    .gs-sounds { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 15rem), 1fr)); gap: .7rem; }
    .gs-sound { display: flex; align-items: center; gap: .7rem; padding: .7rem .9rem; }
    .gs-sound > span { font-size: 1.6rem; }
    .gs-sound div { flex: 1; min-width: 0; } .gs-sound small { display: block; color: var(--gray-500); font-size: .76rem; }
    .gs-scene-box { position: relative; width: 390px; max-width: 100%; height: 330px; margin-inline: auto; border-radius: 1rem; overflow: hidden; }

    /* One game's page (the approved design, Studio-Game). */
    .ge { display: flex; flex-direction: column; gap: 1rem; }
    .ge-head { display: flex; justify-content: space-between; gap: 1rem; align-items: flex-end; flex-wrap: wrap; }
    .ge-crumbs { font-size: .8rem; color: var(--gray-500); } .ge-crumbs a { color: var(--primary-600); text-decoration: underline; }
    .ge-head h1 { font-size: 1.6rem; font-weight: 800; display: flex; gap: .6rem; align-items: center; flex-wrap: wrap; }
    .ge-actions { display: flex; gap: .5rem; }
    .ge-grid { display: grid; grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr); gap: 1rem; align-items: start; }
    @media (max-width: 1023.98px) { .ge-grid { grid-template-columns: 1fr; } }
    .ge-main, .ge-side { display: flex; flex-direction: column; gap: 1rem; }
    .ge-card { display: flex; flex-direction: column; gap: .7rem; padding: 1.1rem 1.2rem; }
    .ge-card h2 { font-weight: 800; font-size: 1.02rem; }
    .ge-card label { display: flex; flex-direction: column; gap: .3rem; font-size: .85rem; font-weight: 600; }
    .ge-card input, .ge-card textarea, .ge-card select { width: 100%; border-radius: .6rem; border: 1px solid var(--gray-300); padding: .55rem .7rem; font-weight: 400; background: white; color: var(--gray-900); }
    .ge-two { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .7rem; }
    @media (max-width: 639.98px) { .ge-two { grid-template-columns: 1fr; } }
    .ge-label { font-size: .85rem; font-weight: 600; margin-bottom: .3rem; }
    .ge-pills { display: flex; gap: .4rem; flex-wrap: wrap; }
    .ge-pills button { padding: .45rem 1rem; border-radius: 999px; border: 1px solid var(--gray-300); font-weight: 600; font-size: .88rem; }
    .ge-pills button[aria-pressed="true"] { background: var(--primary-600); border-color: var(--primary-600); color: white; }
    .ge-muted { font-size: .84rem; color: var(--gray-500); line-height: 1.6; }
    .ge-chips { display: flex; flex-wrap: wrap; gap: .35rem; } .ge-chips span { font-size: .78rem; font-weight: 600; padding: .15rem .6rem; border-radius: 999px; background: var(--primary-50); color: var(--primary-700); }
    .ge-table { overflow-x: auto; border: 1px solid var(--gray-200); border-radius: var(--radius-lg, .5rem); }
    .ge-table table { width: 100%; border-collapse: collapse; font-size: .84rem; }
    .ge-table th { text-align: start; font-size: .74rem; font-weight: 700; color: var(--gray-500); padding: .55rem .7rem; background: var(--gray-50); white-space: nowrap; }
    .ge-table td { padding: .55rem .7rem; border-top: 1px solid var(--gray-100); vertical-align: top; }
    .ge-table td small { color: var(--gray-400); font-size: .7rem; }
    .ge-table td:first-child { color: var(--gray-500); width: 3.2rem; }
    .ge-right { display: inline-block; font-weight: 700; padding: .05rem .5rem; border-radius: 999px; background: var(--success-50); color: var(--success-700); }
    .ge-sample { display: flex; flex-direction: column; gap: .35rem; padding-inline-start: 1.1rem; list-style: decimal; font-size: .86rem; }
    .ge-sample small { color: var(--gray-500); margin-inline-start: .4rem; }
    .ge-error { color: var(--danger-600); font-size: .8rem; }
    .ge-stats { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .5rem; }
    .ge-stats div { padding: .55rem .7rem; border-radius: .7rem; background: var(--gray-50); }
    .ge-stats small { display: block; font-size: .74rem; color: var(--gray-500); } .ge-stats b { font-size: 1.2rem; font-weight: 800; }
    .ge-bars { display: flex; flex-direction: column; gap: .35rem; }
    .ge-bars div { display: grid; grid-template-columns: 4.5rem 1fr 2.6rem; gap: .5rem; align-items: center; font-size: .8rem; }
    .ge-bars i { height: .55rem; border-radius: 999px; background: var(--gray-100); overflow: hidden; }
    .ge-bars b { display: block; height: 100%; background: var(--primary-600); border-radius: 999px; }
    .ge-bars b[data-weak] { background: var(--warning-500); }
    .ge-bars em { font-style: normal; font-weight: 700; text-align: end; }
    .ge-hint { padding: .6rem .8rem; border-radius: .7rem; background: var(--warning-50); color: var(--warning-800); font-size: .82rem; line-height: 1.6; }
    .dark .ge-card input, .dark .ge-card textarea, .dark .ge-card select { background: var(--gray-900); color: var(--gray-100); border-color: var(--gray-700); }
</style>
