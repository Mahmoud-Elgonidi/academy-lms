{{--
    The games' parts around the journey (docs/architecture/02, section 18): a guardian's limits and week (the
    approved design, Guardian: calm cards in the academy's colours) and a round's result for middle school,
    secondary school and adults. The children's result and hatch keep the world's own look (world-styles).
--}}
<style>
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
    .gw-res { position: fixed; inset: 0; z-index: 55; display: flex; align-items: center; justify-content: center; padding: 20px; background: rgb(0 0 0 / .55); }
    .gw-res-card { width: min(100%, 380px); padding: 20px 18px 16px; border-radius: 22px; background: white; color: var(--gray-900); text-align: center; display: flex; flex-direction: column; gap: 6px; }
    .dark .gw-res-card { background: var(--gray-900); color: var(--gray-100); }
    .gw-res-kicker { font-size: 14px; font-weight: 800; color: var(--primary-700); }
    .gw-res-card h2 { margin: 0; font-size: 22px; font-weight: 800; line-height: 1.25; }
    .gw-res-card p { margin: 0; font-size: 14.5px; color: var(--gray-600); }
    .dark .gw-res-card p { color: var(--gray-400); }
    .gw-res-stars { display: flex; justify-content: center; gap: 4px; font-size: 34px; line-height: 1; }
    .gw-res-stars [data-on="yes"] { color: var(--warning-400); } .gw-res-stars [data-on="no"] { color: var(--gray-200); }
    .gw-res-hatch { display: flex; align-items: center; justify-content: center; gap: 8px; font-weight: 700; }
    .gw-res-acts { display: flex; flex-direction: column; gap: 8px; margin-top: 8px; }
    .gw-res-acts button { min-height: 48px; border-radius: 14px; font-size: 16px; font-weight: 700; cursor: pointer; border: 1px solid var(--gray-300); background: transparent; color: inherit; }
    .gw-res-acts button[data-kind="go"] { border: 0; background: var(--primary-600); color: white; }
    @media (prefers-reduced-motion: no-preference) {
        .gw-ring { transition: stroke-dasharray .9s cubic-bezier(.2, .8, .3, 1); }
        .gw-hearts i { animation: gw-heart 1.1s ease-out both; }
        .gw-hearts i:nth-child(2) { animation-delay: .15s; } .gw-hearts i:nth-child(3) { animation-delay: .3s; }
        .gw-res-card { animation: gw-popin .35s cubic-bezier(.3, 1.6, .55, 1) backwards; }
        .gw-res-stars > * { display: inline-block; animation: gw-starpop .5s cubic-bezier(.3, 1.8, .5, 1) calc(.2s + var(--i) * .25s) backwards; }
    }
    @keyframes gw-heart { 0% { transform: translateY(0) scale(.4); opacity: 0; } 30% { opacity: 1; transform: translateY(-8px) scale(1.1); } 100% { transform: translateY(-34px) scale(.9); opacity: 0; } }
</style>
