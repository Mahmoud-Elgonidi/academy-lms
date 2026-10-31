{{--
    The group screens' look (Groups, a group's page, Find a teacher's group
    mode): the group tone is Filament's "info" colour so it follows the
    academy's branding and stays apart from the brand's own (primary). Motion
    is transform and opacity only, under prefers-reduced-motion: no-preference.
--}}
<style>
    .gr-badge { display: inline-flex; align-items: center; gap: .3rem; padding: .15rem .55rem; border-radius: 999px; font-size: .72rem; font-weight: 700;
        background: var(--info-50); color: var(--info-700); border: 1px solid var(--info-200); white-space: nowrap; }
    .gr-badge-ico { width: .85rem; height: .85rem; }
    .gr-seats { display: inline-flex; align-items: center; gap: .45rem; }
    .gr-dots { display: inline-flex; flex-wrap: wrap; gap: .22rem; max-width: 9.5rem; }
    .gr-dots i { width: .62rem; height: .62rem; border-radius: 50%; border: 1.5px solid var(--info-400); background: transparent; display: block; }
    .gr-dots i[data-on] { background: var(--info-500); border-color: var(--info-500); }
    .gr-dots i[data-new] { background: var(--success-500); border-color: var(--success-500); }
    .gr-seats-n { font-size: .8rem; font-weight: 700; color: var(--info-700); font-variant-numeric: tabular-nums; }
    .gr-pill { display: inline-flex; align-items: center; gap: .3rem; padding: .15rem .6rem; border-radius: 999px; font-size: .72rem; font-weight: 700; white-space: nowrap; }
    .gr-pill::before { content: ''; width: .4rem; height: .4rem; border-radius: 50%; background: currentColor; }
    .gr-pill[data-tone="success"] { background: var(--success-50); color: var(--success-700); }
    .gr-pill[data-tone="warning"] { background: var(--warning-50); color: var(--warning-700); }
    .gr-pill[data-tone="gray"] { background: var(--gray-100); color: var(--gray-600); }
    .gr-card { position: relative; display: flex; flex-direction: column; gap: .7rem; padding: 1rem 1.1rem; border-radius: var(--radius-xl); background: white;
        border: 1px solid var(--gray-200); border-top: 3px solid var(--info-500); text-align: start; color: inherit; min-width: 0; }
    .gr-card[data-status="ended"] { border-top-color: var(--gray-300); }
    .gr-card[data-focus] { box-shadow: 0 0 0 2px var(--info-500); }
    a.gr-card:hover, a.gr-card:focus-visible { border-color: var(--info-300); border-top-color: var(--info-500); }
    a.gr-card:focus-visible { outline: 2px solid var(--primary-600); outline-offset: 2px; }
    .gr-card-head { display: flex; align-items: flex-start; justify-content: space-between; gap: .6rem; }
    .gr-card-title { margin: 0; font-size: 1rem; font-weight: 700; line-height: 1.35; color: var(--gray-950); }
    .gr-card-sub { margin: .1rem 0 0; font-size: .8rem; color: var(--gray-500); }
    .gr-times { display: flex; align-items: center; gap: .4rem; font-size: .875rem; font-weight: 600; font-variant-numeric: tabular-nums; color: var(--gray-800); }
    .gr-times svg { width: 1rem; height: 1rem; color: var(--info-600); flex-shrink: 0; }
    .gr-meta { display: flex; flex-wrap: wrap; gap: .35rem .9rem; font-size: .78rem; color: var(--gray-500); }
    .gr-meta span { display: inline-flex; align-items: center; gap: .3rem; }
    .gr-meta svg { width: .9rem; height: .9rem; }
    .gr-meta strong { color: var(--gray-800); font-weight: 600; }
    .gr-foot { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .5rem; padding-top: .6rem; border-top: 1px dashed var(--gray-200); }
    .gr-avatar { width: 2.3rem; height: 2.3rem; border-radius: 50%; display: grid; place-items: center; font-weight: 700; flex-shrink: 0; background: var(--info-50); color: var(--info-700); }
    .gr-muted { color: var(--gray-500); font-size: .8125rem; margin: 0; }
    .dark .gr-badge { background: color-mix(in oklab, var(--info-500) 16%, var(--gray-900)); color: var(--info-300); border-color: color-mix(in oklab, var(--info-500) 35%, var(--gray-900)); }
    .dark .gr-dots i { border-color: var(--info-400); }
    .dark .gr-dots i[data-on] { background: var(--info-400); border-color: var(--info-400); }
    .dark .gr-seats-n { color: var(--info-300); }
    .dark .gr-pill[data-tone="success"] { background: color-mix(in oklab, var(--success-500) 15%, transparent); color: var(--success-300); }
    .dark .gr-pill[data-tone="warning"] { background: color-mix(in oklab, var(--warning-500) 15%, transparent); color: var(--warning-300); }
    .dark .gr-pill[data-tone="gray"] { background: var(--gray-800); color: var(--gray-300); }
    .dark .gr-card { background: var(--gray-900); border-color: var(--gray-800); border-top-color: var(--info-500); }
    .dark .gr-card[data-status="ended"] { border-top-color: var(--gray-700); }
    .dark .gr-card-title { color: white; }
    .dark .gr-times { color: var(--gray-100); }
    .dark .gr-times svg { color: var(--info-400); }
    .dark .gr-meta strong { color: var(--gray-200); }
    .dark .gr-foot { border-top-color: var(--gray-800); }
    .dark .gr-avatar { background: color-mix(in oklab, var(--info-500) 18%, var(--gray-900)); color: var(--info-300); }
    @media (prefers-reduced-motion: no-preference) {
        .gr-dots i[data-new] { animation: gr-seat 1.4s ease-in-out infinite; }
        .gr-dots i[data-on] { animation: gr-fill .35s ease-out backwards; }
        @foreach (range(1, 20) as $n)
            .gr-dots i:nth-child({{ $n }}) { animation-delay: {{ $n * 40 }}ms; }
        @endforeach
        @keyframes gr-seat { 50% { transform: scale(1.35); opacity: .6; } }
        @keyframes gr-fill { from { transform: scale(.3); opacity: 0; } }
    }
</style>
