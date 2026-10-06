{{--
    The look of a student's own area, by stage: the font, the background and
    the shapes of cards and buttons, over the academy's colours (Filament's
    colour variables). Printed at the start of <body>, so it goes away with
    the page on navigation; guardians and adults (classic) get nothing here.
    Fonts are served from the app (public/fonts/stages, OFL): no third party
    sees which children use the site.
--}}
@php
    $fonts = [
        'kids' => ['Baloo Bhaijaan 2 Variable', [['baloo-bhaijaan-2/baloo-bhaijaan-2-arabic-wght-normal.woff2', 'arabic', '400 800'], ['baloo-bhaijaan-2/baloo-bhaijaan-2-latin-wght-normal.woff2', 'latin', '400 800']]],
        'middle' => ['Rubik Variable', [['rubik/rubik-arabic-wght-normal.woff2', 'arabic', '300 900'], ['rubik/rubik-latin-wght-normal.woff2', 'latin', '300 900']]],
        'teen' => ['IBM Plex Sans Arabic', collect([400, 500, 600, 700])->flatMap(fn ($w) => [["ibm-plex-sans-arabic/ibm-plex-sans-arabic-arabic-{$w}-normal.woff2", 'arabic', (string) $w], ["ibm-plex-sans-arabic/ibm-plex-sans-arabic-latin-{$w}-normal.woff2", 'latin', (string) $w]])->all()],
    ];
    $ranges = [
        'arabic' => 'U+0600-06FF,U+0750-077F,U+0870-088E,U+0890-0891,U+0897-08E1,U+08E3-08FF,U+200C-200E,U+2010-2011,U+204F,U+2E41,U+FB50-FDFF,U+FE70-FE74,U+FE76-FEFC',
        'latin' => 'U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD',
    ];
    [$family, $files] = $fonts[$stage];
@endphp
<style data-stage="{{ $stage }}">
    @foreach ($files as [$file, $range, $weight])
    @font-face { font-family: '{{ $family }}'; font-style: normal; font-display: swap; font-weight: {{ $weight }}; src: url('{{ asset('fonts/stages/'.$file) }}') format('woff2'); unicode-range: {{ $ranges[$range] }}; }
    @endforeach
    :root { --font-family: '{{ $family }}'; --lms-font-heading: '{{ $family }}', system-ui, sans-serif; }   /* over the academy's fonts (Platform branding) */

    /* Shared by the three: a tab bar at the bottom on phones instead of the side menu. */
    .lms-tabs { position: fixed; z-index: 30; inset-inline: 0; bottom: 0; display: flex; gap: .25rem; padding: .4rem .5rem calc(.4rem + env(safe-area-inset-bottom)); background: white; border-top: 1px solid var(--gray-200); }
    .lms-tab { position: relative; flex: 1 1 0; min-width: 0; display: flex; flex-direction: column; align-items: center; gap: .15rem; padding: .35rem .2rem; border-radius: .9rem; color: var(--gray-500); font-size: .72rem; font-weight: 600; text-decoration: none; }
    .lms-tab svg { width: 1.45rem; height: 1.45rem; }
    .lms-tab span { max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .lms-tab[aria-current] { color: var(--primary-700); background: var(--primary-50); }
    .lms-tab b { position: absolute; top: .1rem; inset-inline-end: calc(50% - 1.45rem); min-width: 1.1rem; height: 1.1rem; padding: 0 .3rem; border-radius: 999px; background: var(--danger-500); color: white; font-size: .65rem; line-height: 1.1rem; text-align: center; }
    @media (min-width: 1024px) { .lms-tabs { display: none; } }
    @media (max-width: 1023.98px) {
        .fi-body { padding-bottom: calc(5rem + env(safe-area-inset-bottom)); }
        .fi-topbar-open-sidebar-btn, .fi-topbar-close-sidebar-btn { display: none !important; }
        .fi-topbar-start { display: flex; min-width: 0; margin-inline-end: .5rem; }   /* the academy's name, where the menu button was */
        .fi-topbar-start .fi-logo { margin-inline-start: 0; font-size: 1.05rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    }
    @media (prefers-reduced-motion: no-preference) {
        .lms-tab[aria-current] svg { animation: lms-tab-hop .55s var(--lms-spring) .1s backwards; }   /* the tab just opened hops */
    }
    @keyframes lms-tab-hop { from { opacity: .4; transform: translateY(6px) scale(.6); } }
    .dark .lms-tabs { background: var(--gray-900); border-color: var(--gray-800); }
    .dark .lms-tab[aria-current] { background: color-mix(in oklab, var(--primary-500) 15%, transparent); color: var(--primary-300); }

    @if ($stage === 'kids')
    /* Children: round, bright and big; chunky buttons that look pressable. Every card, here and in the modules, follows the radii. */
    :root { --radius-sm: .6rem; --radius-md: .85rem; --radius-lg: 1.1rem; --radius-xl: 1.6rem; }
    .fi-body { font-size: 1.05rem; background:
        radial-gradient(60rem 28rem at 100% -8%, color-mix(in oklab, var(--warning-200) 50%, transparent), transparent 60%),
        radial-gradient(50rem 30rem at 0% 0%, color-mix(in oklab, var(--info-200) 50%, transparent), transparent 55%),
        color-mix(in oklab, var(--primary-50) 50%, white); background-attachment: fixed; }
    .fi-topbar { border-radius: 0 0 1.5rem 1.5rem; box-shadow: 0 4px 0 color-mix(in oklab, var(--gray-200) 80%, transparent); }
    .fi-topbar-item-btn { border-radius: 999px; padding-inline: .9rem; }
    .fi-topbar-item-label { font-weight: 700; font-size: 1rem; }
    .fi-topbar-item.fi-active .fi-topbar-item-btn { background: var(--primary-500); }
    .fi-topbar-item.fi-active .fi-topbar-item-btn :is(.fi-topbar-item-label, .fi-topbar-item-icon) { color: white; }
    .fi-header-heading { font-weight: 800; font-size: 1.7rem; }
    .fi-section { box-shadow: 0 5px 0 var(--gray-200); }
    .fi-btn { border-radius: 999px; font-weight: 800; }
    .fi-btn:not(.fi-outlined):not(.fi-link) { box-shadow: inset 0 -3px 0 rgb(0 0 0 / .18); }
    .fi-input-wrp, .fi-fo-field-wrp textarea { border-radius: 1rem; }
    .fi-modal-window { border-radius: 1.75rem; }
    .lms-tabs { inset-inline: .7rem; bottom: .7rem; padding: .45rem; border: 2px solid var(--gray-200); border-radius: 1.6rem; box-shadow: 0 5px 0 var(--gray-200); }
    .lms-tab { font-size: .85rem; font-weight: 800; border-radius: 1.2rem; }
    .lms-tab svg { width: 1.8rem; height: 1.8rem; }
    .lms-tab[aria-current] { background: var(--primary-500); color: white; box-shadow: inset 0 -3px 0 rgb(0 0 0 / .15); }
    @media (max-width: 1023.98px) { .fi-body { padding-bottom: calc(6.5rem + env(safe-area-inset-bottom)); } }
    .dark .fi-body { background: var(--gray-950); }
    .dark .fi-section { box-shadow: 0 5px 0 var(--gray-800); }
    .dark .lms-tabs { border-color: var(--gray-700); box-shadow: 0 5px 0 var(--gray-800); }
    @elseif ($stage === 'middle')
    /* Middle school: energetic and game-like; bold type, rounded-square shapes. */
    :root { --radius-sm: .45rem; --radius-md: .6rem; --radius-lg: .8rem; --radius-xl: 1.15rem; }
    .fi-body { background:
        radial-gradient(56rem 26rem at 100% -6%, color-mix(in oklab, var(--primary-200) 55%, transparent), transparent 60%),
        radial-gradient(44rem 24rem at 0% 8%, color-mix(in oklab, var(--warning-200) 40%, transparent), transparent 60%),
        var(--gray-50); background-attachment: fixed; }
    .fi-topbar-item-btn { border-radius: .8rem; }
    .fi-topbar-item-label { font-weight: 600; }
    .fi-topbar-item.fi-active .fi-topbar-item-btn { background: var(--primary-600); }
    .fi-topbar-item.fi-active .fi-topbar-item-btn :is(.fi-topbar-item-label, .fi-topbar-item-icon) { color: white; }
    .fi-header-heading { font-weight: 800; letter-spacing: -.02em; }
    .fi-btn { border-radius: .8rem; font-weight: 700; }
    .fi-input-wrp { border-radius: .8rem; }
    .lms-tab[aria-current] { background: var(--primary-600); color: white; }
    .dark .fi-body { background: var(--gray-950); }
    @else
    /* Secondary school: calm and clean, like a study planner; flat surfaces, quiet accents. */
    :root { --radius-sm: .3rem; --radius-md: .4rem; --radius-lg: .55rem; --radius-xl: .8rem; }
    .fi-body { background: var(--gray-50); }
    .fi-topbar { box-shadow: none; border-bottom: 1px solid var(--gray-200); }
    .fi-topbar-item-label { font-weight: 500; }
    .fi-header-heading { font-weight: 600; letter-spacing: -.01em; }
    .fi-section { box-shadow: none; }
    .fi-btn { border-radius: .6rem; font-weight: 600; }
    .lms-tab { font-weight: 500; }
    .lms-tab[aria-current] { background: transparent; color: var(--primary-700); }
    .lms-tab[aria-current]::before { content: ''; position: absolute; top: -.4rem; inset-inline: 30%; height: 2px; border-radius: 2px; background: var(--primary-600); }
    .dark .fi-topbar { border-color: var(--gray-800); }
    @endif
</style>
