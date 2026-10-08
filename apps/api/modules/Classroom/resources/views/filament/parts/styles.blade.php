<style>
    .rc-stack { display: flex; flex-direction: column; gap: 1rem; }
    .rc-card { background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl, .75rem); padding: 1rem 1.1rem; display: flex; flex-direction: column; gap: .75rem; }
    .rc-card[data-state="none"] { border-color: var(--warning-300); }
    .rc-head { display: flex; justify-content: space-between; align-items: center; gap: .75rem; flex-wrap: wrap; }
    .rc-head h3 { margin: 0; font-size: 1.05rem; font-weight: 700; }
    .rc-pill { font-size: .75rem; padding: .1rem .6rem; border-radius: 999px; background: var(--gray-100); color: var(--gray-700); }
    .rc-pill[data-tone="good"] { background: var(--success-50); color: var(--success-700); }
    .rc-pill[data-tone="warn"] { background: var(--warning-50); color: var(--warning-700); }
    .rc-intro { margin: 0; line-height: 1.7; }
    .rc-points { list-style: none; margin: 0; padding: 0; display: grid; gap: .5rem; }
    .rc-points li { background: var(--primary-50); border-radius: var(--radius-lg, .5rem); padding: .6rem .75rem; display: flex; flex-direction: column; gap: .1rem; font-size: .9rem; }
    .rc-agree { display: flex; gap: .6rem; align-items: flex-start; border: 1px solid var(--gray-200); border-radius: var(--radius-lg, .5rem); padding: .7rem .8rem; font-weight: 600; font-size: .92rem; cursor: pointer; }
    .rc-agree input { margin-top: .25rem; accent-color: var(--primary-600); width: 1.1rem; height: 1.1rem; flex: none; }
    .rc-error { margin: 0; color: var(--danger-600); font-size: .85rem; }
    .rc-muted { margin: 0; color: var(--gray-500); font-size: .9rem; }
    .rc-section { margin: .5rem 0 0; font-size: 1rem; font-weight: 700; }
    .rc-list { display: grid; gap: .6rem; }
    .rc-item { display: flex; align-items: center; justify-content: space-between; gap: .75rem; background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl, .75rem); padding: .75rem .9rem; }
    .rc-item[data-playing="yes"] { border-color: var(--primary-400); box-shadow: 0 0 0 3px var(--primary-50); }
    .rc-item-text { display: grid; gap: .15rem; min-width: 0; }
    .rc-item-text small { color: var(--gray-500); overflow-wrap: anywhere; }
    .rc-empty { padding: 1.5rem; text-align: center; color: var(--gray-500); border: 1px dashed var(--gray-300); border-radius: var(--radius-xl, .75rem); }
    .rc-player { border-radius: var(--radius-xl, .75rem); padding: .75rem; background: var(--gray-950); display: grid; gap: .5rem; }
    .rc-player-top { display: flex; justify-content: space-between; align-items: center; gap: .5rem; }
    .rc-player-top small { color: var(--gray-400); }
    .rc-player video { width: 100%; max-height: 60vh; border-radius: var(--radius-lg, .5rem); background: #000; }
    .rc-player audio { width: 100%; }
    .dark .rc-card, .dark .rc-item { background: var(--gray-900); border-color: var(--gray-800); }
    .dark .rc-points li { background: var(--gray-800); }
</style>
