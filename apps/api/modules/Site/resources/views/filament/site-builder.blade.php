@php
    $b = 'site::builder.';
    $verdict = $this->verdict();
    $blockers = $verdict->blockers;
    $warnings = $verdict->warnings;
    $unpublished = ! $hasPublished || $publishedRevision !== $revision;
    $on = $this->pageIsOn();
    $config = [
        'base' => $this->previewBase(),
        'token' => $previewToken,
        'revision' => $revision,
        'lang' => app()->getLocale() === 'en' ? 'en' : 'ar',
    ];
    $devices = [
        'desktop' => ['icon' => 'heroicon-o-computer-desktop', 'width' => 1280],
        'tablet' => ['icon' => 'heroicon-o-device-tablet', 'width' => 820],
        'phone' => ['icon' => 'heroicon-o-device-phone-mobile', 'width' => 390],
    ];
@endphp

<x-filament-panels::page>
    <script>
        // The builder's preview: the portal's /{locale}/site-preview of the draft, in two frames that
        // take turns (the next loads hidden, then shows), at a device's width scaled to the pane.
        window.lmsSiteBuilder = window.lmsSiteBuilder || function (cfg) {
            return {
                device: 'desktop', lang: cfg.lang, rev: cfg.revision, token: cfg.token, base: cfg.base,
                widths: { desktop: 1280, tablet: 820, phone: 390 },
                active: 0, loading: true, anchor: null, scale: 1, stageH: 640, open: false, failed: false,
                init() {
                    this.$nextTick(() => { this.measure(); this.load(); });
                    if (window.ResizeObserver) { new ResizeObserver(() => this.measure()).observe(this.$refs.stage); }
                    setInterval(async () => { this.token = await this.$wire.renewPreview(); }, 90 * 60 * 1000);
                },
                url() {
                    return this.base + '/' + this.lang + '/site-preview?t=' + encodeURIComponent(this.token) + '&r=' + this.rev + (this.anchor ? '#' + this.anchor : '');
                },
                measure() {
                    const stage = this.$refs.stage;
                    if (! stage) { return; }
                    this.stageH = Math.max(320, stage.clientHeight - 24);
                    this.scale = Math.min(1, (stage.clientWidth - 24) / this.widths[this.device]);
                },
                frameStyle() {
                    return 'width:' + this.widths[this.device] + 'px;height:' + Math.round(this.stageH / this.scale) + 'px;transform:scale(' + this.scale + ')';
                },
                shellStyle() {
                    return 'width:' + Math.round(this.widths[this.device] * this.scale) + 'px;height:' + this.stageH + 'px';
                },
                load() {
                    const next = 1 - this.active;
                    const frame = this.$refs['frame' + next];
                    if (! frame) { return; }
                    this.loading = true;
                    const timer = setTimeout(() => { this.failed = true; }, 15000);
                    frame.onload = () => { clearTimeout(timer); this.failed = false; this.active = next; this.loading = false; };
                    frame.src = this.url();
                },
                refresh(rev) { if (rev) { this.rev = rev; } this.anchor = null; this.load(); },
                focus(anchor) { this.anchor = anchor; this.open = true; this.load(); },
                setDevice(d) { this.device = d; this.$nextTick(() => this.measure()); },
                setLang(l) { if (this.lang !== l) { this.lang = l; this.load(); } },
                save() { this.$wire.saveDraft(); },
            };
        };
    </script>

    <style>
        .lms-sb { --sb-line: var(--gray-200); --sb-card: white; --sb-soft: var(--gray-50); display: flex; flex-direction: column; gap: 1rem; }
        .dark .lms-sb { --sb-line: var(--gray-800); --sb-card: var(--gray-900); --sb-soft: var(--gray-950); }

        /* Status bar: where the draft stands, and what stands between it and the page. */
        .lms-sb-status { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem 1rem; padding: .7rem 1rem; border-radius: var(--radius-xl, .75rem);
            background: var(--sb-card); border: 1px solid var(--sb-line); box-shadow: 0 1px 2px rgb(0 0 0 / .04); font-size: .85rem; }
        .lms-sb-dot { width: .55rem; height: .55rem; border-radius: 999px; background: var(--success-500); box-shadow: 0 0 0 3px color-mix(in oklab, var(--success-500) 20%, transparent); flex: none; }
        .lms-sb-dot[data-state="draft"] { background: var(--warning-500); box-shadow: 0 0 0 3px color-mix(in oklab, var(--warning-500) 22%, transparent); }
        .lms-sb-status strong { font-weight: 600; color: var(--gray-950); }
        .dark .lms-sb-status strong { color: white; }
        .lms-sb-muted { color: var(--gray-500); }
        .lms-sb-sep { width: 1px; height: 1rem; background: var(--sb-line); }
        .lms-sb-saving { display: inline-flex; align-items: center; gap: .35rem; color: var(--primary-600); font-weight: 600; }
        .lms-sb-chips { margin-inline-start: auto; display: flex; gap: .4rem; flex-wrap: wrap; }
        .lms-sb-chip { position: relative; }
        .lms-sb-chip > summary { list-style: none; cursor: pointer; display: inline-flex; align-items: center; gap: .35rem; padding: .25rem .65rem; border-radius: 999px; font-weight: 600; font-size: .78rem; }
        .lms-sb-chip > summary::-webkit-details-marker { display: none; }
        .lms-sb-chip[data-kind="blocker"] > summary { background: var(--danger-50); color: var(--danger-700); }
        .lms-sb-chip[data-kind="warning"] > summary { background: var(--warning-50); color: var(--warning-700); }
        .lms-sb-chip[data-kind="ready"] > summary { background: var(--success-50); color: var(--success-700); cursor: default; }
        .dark .lms-sb-chip[data-kind="blocker"] > summary { background: color-mix(in oklab, var(--danger-500) 18%, transparent); color: var(--danger-300); }
        .dark .lms-sb-chip[data-kind="warning"] > summary { background: color-mix(in oklab, var(--warning-500) 18%, transparent); color: var(--warning-300); }
        .dark .lms-sb-chip[data-kind="ready"] > summary { background: color-mix(in oklab, var(--success-500) 18%, transparent); color: var(--success-300); }
        .lms-sb-chip ul { position: absolute; z-index: 30; inset-inline-end: 0; top: calc(100% + .4rem); width: min(26rem, 80vw); max-height: 18rem; overflow: auto; margin: 0; padding: .5rem;
            list-style: none; background: var(--sb-card); border: 1px solid var(--sb-line); border-radius: var(--radius-lg, .5rem); box-shadow: 0 12px 32px -12px rgb(0 0 0 / .3); }
        .lms-sb-chip li { padding: .4rem .5rem; border-radius: .375rem; font-size: .8rem; line-height: 1.5; color: var(--gray-700); }
        .dark .lms-sb-chip li { color: var(--gray-300); }
        .lms-sb-chip li + li { border-top: 1px solid var(--sb-line); }

        /* Two columns on wide screens: the editor, and the preview that stays in sight. */
        .lms-sb-grid { display: grid; gap: 1.25rem; grid-template-columns: minmax(0, 1fr); align-items: start; }
        @media (min-width: 1280px) { .lms-sb-grid { grid-template-columns: minmax(400px, 470px) minmax(0, 1fr); } }

        .lms-sb-tabs { display: flex; gap: .25rem; padding: .25rem; border-radius: var(--radius-lg, .6rem); background: var(--gray-100); margin-bottom: .9rem; }
        .dark .lms-sb-tabs { background: var(--gray-800); }
        .lms-sb-tabs button { flex: 1; display: inline-flex; justify-content: center; align-items: center; gap: .4rem; padding: .5rem .75rem; border-radius: calc(var(--radius-lg, .6rem) - .15rem);
            font-size: .85rem; font-weight: 600; color: var(--gray-600); transition: background-color .15s, color .15s, box-shadow .15s; }
        .lms-sb-tabs button[aria-selected="true"] { background: var(--sb-card); color: var(--gray-950); box-shadow: 0 1px 3px rgb(0 0 0 / .12); }
        .dark .lms-sb-tabs button { color: var(--gray-400); }
        .dark .lms-sb-tabs button[aria-selected="true"] { color: white; }
        .lms-sb-tabs svg { width: 1.05rem; height: 1.05rem; }

        /* The block list: type, design and title on one line; a hidden block dimmed. */
        .lms-blk { display: inline-flex; align-items: center; gap: .45rem; min-width: 0; flex-wrap: wrap; }
        .lms-blk__type { font-weight: 700; color: var(--gray-950); }
        .dark .lms-blk__type { color: white; }
        .lms-blk__variant { font-size: .72rem; font-weight: 600; padding: .05rem .45rem; border-radius: 999px; background: var(--gray-100); color: var(--gray-600); }
        .dark .lms-blk__variant { background: var(--gray-800); color: var(--gray-300); }
        .lms-blk__title { font-weight: 400; color: var(--gray-500); font-size: .8rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 14rem; }
        .lms-blk__live { font-size: .68rem; font-weight: 700; color: var(--info-700); background: var(--info-50); padding: .05rem .4rem; border-radius: 999px; }
        .lms-blk__hidden { font-size: .68rem; font-weight: 700; color: var(--gray-600); background: var(--gray-200); padding: .05rem .4rem; border-radius: 999px; }
        .lms-sb .fi-fo-builder-item:has([data-lms-hidden]) { opacity: .62; background-image: repeating-linear-gradient(135deg, transparent 0 8px, color-mix(in oklab, var(--gray-400) 9%, transparent) 8px 9px); }
        .lms-sb .fi-fo-builder-item:has([data-lms-hidden]):hover, .lms-sb .fi-fo-builder-item:has([data-lms-hidden]):focus-within { opacity: 1; }

        /* A closed block: its design small, and a button to open it. */
        .lms-sum { display: flex; align-items: center; gap: .8rem; }
        .lms-sum__svg { flex: none; display: grid; place-items: center; width: 4.2rem; height: 2.7rem; border-radius: .45rem; background: var(--sb-soft); color: var(--gray-500); border: 1px solid var(--sb-line); }
        .lms-sum__svg svg { width: 3.6rem; height: auto; }
        .lms-sum__hint { flex: 1; min-width: 0; font-size: .78rem; line-height: 1.5; color: var(--gray-500); }

        /* Pickers drawn as cards: designs, backgrounds, colours. */
        .lms-pick { display: grid; gap: .5rem; grid-template-columns: repeat(auto-fill, minmax(6.75rem, 1fr)); }
        .lms-pick label { position: relative; display: flex; flex-direction: column; align-items: center; gap: .35rem; padding: .55rem .4rem .45rem; border-radius: var(--radius-lg, .6rem);
            border: 1.5px solid var(--sb-line); background: var(--sb-card); cursor: pointer; color: var(--gray-500); font-size: .75rem; font-weight: 600; text-align: center; transition: border-color .15s, box-shadow .15s, color .15s; }
        .lms-pick label:hover { border-color: var(--primary-300); }
        .lms-pick label:has(input:checked) { border-color: var(--primary-600); box-shadow: 0 0 0 1px var(--primary-600); color: var(--primary-700); }
        .dark .lms-pick label:has(input:checked) { color: var(--primary-300); }
        .lms-pick label:has(input:focus-visible) { outline: 2px solid var(--primary-600); outline-offset: 2px; }
        .lms-pick input { position: absolute; opacity: 0; pointer-events: none; }
        .lms-pick svg { width: 100%; max-width: 5.5rem; height: auto; color: var(--gray-600); }
        .dark .lms-pick svg { color: var(--gray-300); }
        .lms-swatch { width: 100%; height: 2.4rem; border-radius: .45rem; border: 1px solid color-mix(in oklab, var(--gray-400) 35%, transparent); display: grid; place-items: center; font-size: .9rem; font-weight: 700; }
        .lms-swatch[data-tone="paper"] { background: color-mix(in oklab, var(--gray-500) 4%, white); color: var(--gray-800); }
        .lms-swatch[data-tone="white"] { background: white; color: var(--gray-800); }
        .lms-swatch[data-tone="tint"] { background: var(--primary-50); color: var(--primary-800); }
        .lms-swatch[data-tone="brand"] { background: var(--primary-600); color: white; }
        .lms-swatch[data-tone="night"] { background: color-mix(in oklab, var(--primary-600) 22%, #0a0f1a); color: white; }
        .lms-icon-opt { display: inline-flex; align-items: center; gap: .5rem; }
        .lms-icon-opt__svg { width: 1.1rem; height: 1.1rem; color: var(--primary-600); }

        /* The media picker inside a block. */
        .lms-mp { display: flex; gap: .8rem; align-items: center; padding: .6rem; border-radius: var(--radius-lg, .6rem); border: 1px dashed var(--gray-300); background: var(--sb-soft); }
        .lms-mp[data-chosen] { border-style: solid; border-color: var(--sb-line); background: var(--sb-card); }
        .lms-mp__thumb { flex: none; width: 5.5rem; aspect-ratio: 4 / 3; border-radius: .5rem; overflow: hidden; display: grid; place-items: center; background: var(--gray-100); color: var(--gray-400); }
        .dark .lms-mp__thumb { background: var(--gray-800); }
        .lms-mp__thumb img, .lms-mp__thumb video { width: 100%; height: 100%; object-fit: cover; }
        .lms-mp__body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: .25rem; }
        .lms-mp__name { font-size: .82rem; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--gray-900); }
        .dark .lms-mp__name { color: white; }
        .lms-mp__meta { font-size: .72rem; color: var(--gray-500); font-variant-numeric: tabular-nums; direction: ltr; text-align: start; unicode-bidi: plaintext; }
        .lms-mp__actions { display: flex; flex-wrap: wrap; gap: .35rem; margin-top: .15rem; }

        /* The library grid (the Media tab, and the picker's window). */
        .lms-lib { display: grid; gap: .75rem; grid-template-columns: repeat(auto-fill, minmax(9.5rem, 1fr)); }
        .lms-lib__item { position: relative; display: flex; flex-direction: column; border-radius: var(--radius-lg, .6rem); overflow: hidden; border: 1px solid var(--sb-line); background: var(--sb-card); }
        .lms-lib__item:has(input:checked) { border-color: var(--primary-600); box-shadow: 0 0 0 2px var(--primary-600); }
        .lms-lib__item input { position: absolute; opacity: 0; pointer-events: none; }
        .lms-lib__item:has(input:focus-visible) { outline: 2px solid var(--primary-600); outline-offset: 2px; }
        .lms-lib__img { aspect-ratio: 4 / 3; background: var(--gray-100) center / cover no-repeat; display: grid; place-items: center; color: var(--gray-400); }
        .dark .lms-lib__img { background-color: var(--gray-800); }
        .lms-lib__img img, .lms-lib__img video { width: 100%; height: 100%; object-fit: cover; display: block; }
        .lms-lib__body { padding: .5rem .6rem; display: flex; flex-direction: column; gap: .15rem; }
        .lms-lib__name { font-size: .78rem; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .lms-lib__meta { font-size: .7rem; color: var(--gray-500); direction: ltr; text-align: start; unicode-bidi: plaintext; }
        .lms-lib__badge { position: absolute; top: .45rem; inset-inline-start: .45rem; font-size: .66rem; font-weight: 700; padding: .1rem .45rem; border-radius: 999px; background: rgb(255 255 255 / .92); color: var(--gray-700); box-shadow: 0 1px 3px rgb(0 0 0 / .15); }
        .lms-lib__badge[data-used] { background: var(--success-600); color: white; }
        .lms-lib__kind { position: absolute; top: .45rem; inset-inline-end: .45rem; display: grid; place-items: center; width: 1.6rem; height: 1.6rem; border-radius: 999px; background: rgb(0 0 0 / .55); color: white; }
        .lms-lib__kind svg { width: .9rem; height: .9rem; }
        .lms-lib__tools { display: flex; justify-content: flex-end; gap: .15rem; padding: 0 .35rem .35rem; }
        .lms-lib-head { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; justify-content: space-between; margin-bottom: .9rem; }
        .lms-lib-totals { font-size: .8rem; color: var(--gray-500); font-variant-numeric: tabular-nums; }
        .lms-lib-filter { display: inline-flex; gap: .2rem; padding: .2rem; border-radius: .6rem; background: var(--gray-100); }
        .dark .lms-lib-filter { background: var(--gray-800); }
        .lms-lib-filter button { padding: .3rem .7rem; border-radius: .45rem; font-size: .78rem; font-weight: 600; color: var(--gray-600); }
        .lms-lib-filter button[aria-pressed="true"] { background: var(--sb-card); color: var(--gray-950); box-shadow: 0 1px 2px rgb(0 0 0 / .1); }
        .dark .lms-lib-filter button[aria-pressed="true"] { color: white; }
        .lms-empty { display: flex; flex-direction: column; align-items: center; gap: .6rem; padding: 2.5rem 1rem; text-align: center; color: var(--gray-500); font-size: .88rem; border: 1px dashed var(--gray-300); border-radius: var(--radius-xl, .75rem); }
        .lms-empty svg { width: 2.2rem; height: 2.2rem; color: var(--gray-400); }

        /* The preview pane. */
        .lms-pv { display: none; }
        @media (min-width: 1280px) {
            .lms-pv { display: flex; flex-direction: column; position: sticky; top: 4.75rem; height: calc(100vh - 6.5rem); min-height: 34rem; border-radius: var(--radius-xl, .75rem);
                border: 1px solid var(--sb-line); background: var(--sb-card); overflow: hidden; box-shadow: 0 1px 2px rgb(0 0 0 / .04); }
            .lms-pv-open { display: none !important; }
        }
        .lms-pv[data-open] { display: flex; flex-direction: column; position: fixed; z-index: 40; inset: .75rem; height: auto; border-radius: var(--radius-xl, .75rem);
            border: 1px solid var(--sb-line); background: var(--sb-card); overflow: hidden; box-shadow: 0 30px 80px -20px rgb(0 0 0 / .5); }
        .lms-pv-bar { display: flex; align-items: center; gap: .5rem; padding: .55rem .7rem; border-bottom: 1px solid var(--sb-line); }
        .lms-pv-bar h3 { font-size: .85rem; font-weight: 700; margin-inline-end: auto; display: flex; align-items: center; gap: .4rem; }
        .lms-seg { display: inline-flex; gap: .15rem; padding: .18rem; border-radius: .55rem; background: var(--gray-100); }
        .dark .lms-seg { background: var(--gray-800); }
        .lms-seg button { display: inline-grid; place-items: center; min-width: 2rem; height: 1.85rem; padding: 0 .5rem; border-radius: .4rem; color: var(--gray-500); font-size: .78rem; font-weight: 700; }
        .lms-seg button[aria-pressed="true"] { background: var(--sb-card); color: var(--primary-700); box-shadow: 0 1px 2px rgb(0 0 0 / .12); }
        .dark .lms-seg button[aria-pressed="true"] { color: var(--primary-300); }
        .lms-seg svg { width: 1.05rem; height: 1.05rem; }
        .lms-pv-link { display: inline-grid; place-items: center; width: 2rem; height: 2rem; border-radius: .45rem; color: var(--gray-500); }
        .lms-pv-link:hover { background: var(--gray-100); color: var(--gray-900); }
        .dark .lms-pv-link:hover { background: var(--gray-800); color: white; }
        .lms-pv-link svg { width: 1.1rem; height: 1.1rem; }
        .lms-pv-stage { position: relative; flex: 1; min-height: 0; display: grid; place-items: start center; padding: 12px; overflow: hidden;
            background: radial-gradient(circle at 1px 1px, color-mix(in oklab, var(--gray-400) 35%, transparent) 1px, transparent 0) 0 0 / 18px 18px, var(--sb-soft); }
        .lms-pv-shell { position: relative; overflow: hidden; border-radius: .6rem; background: white; box-shadow: 0 0 0 1px rgb(0 0 0 / .06), 0 18px 40px -18px rgb(0 0 0 / .35); transition: width .25s ease; }
        .lms-pv-shell[data-device="phone"] { border-radius: 1.4rem; box-shadow: 0 0 0 6px var(--gray-900), 0 18px 40px -18px rgb(0 0 0 / .45); }
        .lms-pv-shell[data-device="tablet"] { border-radius: 1rem; box-shadow: 0 0 0 5px var(--gray-800), 0 18px 40px -18px rgb(0 0 0 / .4); }
        .lms-pv-shell iframe { position: absolute; top: 0; inset-inline-start: 0; border: 0; transform-origin: top left; background: white; }
        [dir="rtl"] .lms-pv-shell iframe { transform-origin: top right; }
        .lms-pv-shell iframe[data-hidden] { visibility: hidden; }
        .lms-pv-loading { position: absolute; inset-block-start: 1.2rem; inset-inline-start: 50%; transform: translateX(-50%); display: inline-flex; align-items: center; gap: .45rem; padding: .35rem .8rem;
            border-radius: 999px; background: rgb(17 24 39 / .82); color: white; font-size: .75rem; font-weight: 600; z-index: 2; pointer-events: none; }
        [dir="rtl"] .lms-pv-loading { transform: translateX(50%); }
        .lms-pv-fail { position: absolute; inset: auto 1rem 1rem; padding: .6rem .8rem; border-radius: .5rem; background: var(--warning-50); color: var(--warning-800); font-size: .78rem; z-index: 2; }
        .lms-pv-open { width: 100%; }
        [x-cloak] { display: none !important; }
        @media (prefers-reduced-motion: no-preference) {
            .lms-sb-saving svg { animation: lms-spin 1s linear infinite; }
            @keyframes lms-spin { to { transform: rotate(360deg); } }
        }
    </style>

    <div class="lms-sb" x-data="lmsSiteBuilder(@js($config))"
        x-on:site-draft-saved.window="refresh($event.detail.revision)"
        x-on:site-preview-focus.window="focus($event.detail.anchor)">

        <div class="lms-sb-status" role="status">
            <span class="lms-sb-dot" data-state="{{ $unpublished ? 'draft' : 'live' }}" aria-hidden="true"></span>
            <span>
                <strong>{{ $unpublished ? ($hasPublished ? __($b.'status.unpublished') : __($b.'status.never')) : __($b.'status.published') }}</strong>
                @if ($savedAt)
                    <span class="lms-sb-muted"> · {{ __($b.'status.saved', ['time' => $savedAt]) }}</span>
                @endif
            </span>
            <span class="lms-sb-saving" wire:loading.flex wire:target="saveDraft">
                <x-filament::icon icon="heroicon-m-arrow-path" class="h-4 w-4" />
                {{ __($b.'status.saving') }}
            </span>
            @unless ($on)
                <span class="lms-sb-sep" aria-hidden="true"></span>
                <a href="{{ \Modules\Site\Filament\Pages\SiteSettings::getUrl() }}" class="lms-sb-muted" style="text-decoration: underline">{{ __($b.'status.off') }}</a>
            @endunless
            <span class="lms-sb-chips">
                @if ($blockers !== [])
                    <details class="lms-sb-chip" data-kind="blocker">
                        <summary><x-filament::icon icon="heroicon-m-no-symbol" class="h-4 w-4" />{{ trans_choice($b.'status.blockers', count($blockers), ['count' => count($blockers)]) }}</summary>
                        <ul>@foreach ($blockers as $item)<li>{{ \Modules\Site\Support\PageWords::rule($item) }}</li>@endforeach</ul>
                    </details>
                @endif
                @if ($warnings !== [])
                    <details class="lms-sb-chip" data-kind="warning">
                        <summary><x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-4 w-4" />{{ trans_choice($b.'status.warnings', count($warnings), ['count' => count($warnings)]) }}</summary>
                        <ul>@foreach ($warnings as $item)<li>{{ \Modules\Site\Support\PageWords::rule($item) }}</li>@endforeach</ul>
                    </details>
                @endif
                @if ($blockers === [])
                    <details class="lms-sb-chip" data-kind="ready"><summary><x-filament::icon icon="heroicon-m-check-circle" class="h-4 w-4" />{{ __($b.'status.ready') }}</summary></details>
                @endif
            </span>
        </div>

        <div class="lms-sb-grid">
            <section class="lms-sb-editor" aria-label="{{ __($b.'title') }}">
                <div class="lms-sb-tabs" role="tablist">
                    @foreach (['sections' => 'heroicon-o-rectangle-stack', 'page' => 'heroicon-o-adjustments-horizontal', 'media' => 'heroicon-o-photo'] as $key => $icon)
                        <button type="button" role="tab" wire:click="$set('tab', '{{ $key }}')" aria-selected="{{ $tab === $key ? 'true' : 'false' }}">
                            <x-filament::icon :icon="$icon" />{{ __($b.'tabs.'.$key) }}
                        </button>
                    @endforeach
                </div>

                <x-filament::button class="lms-pv-open" color="gray" icon="heroicon-o-eye" x-on:click="open = true; $nextTick(() => { measure(); load(); })" style="margin-bottom: .9rem">
                    {{ __($b.'preview.show') }}
                </x-filament::button>

                <div role="tabpanel" x-on:input.debounce.900ms="save()">
                    @if ($tab === 'page')
                        {{ $this->settingsForm }}
                    @elseif ($tab === 'media')
                        @include('site::filament.builder-media', ['library' => $this->library()])
                    @else
                        {{ $this->blocksForm }}
                    @endif
                </div>
            </section>

            <aside class="lms-pv" x-bind:data-open="open ? '' : null" aria-label="{{ __($b.'preview.title') }}">
                <div class="lms-pv-bar">
                    <h3><x-filament::icon icon="heroicon-o-eye" class="h-4 w-4" />{{ __($b.'preview.title') }}</h3>
                    <div class="lms-seg" role="group" aria-label="{{ __($b.'preview.title') }}">
                        @foreach ($devices as $device => $d)
                            <button type="button" x-on:click="setDevice('{{ $device }}')" x-bind:aria-pressed="device === '{{ $device }}'" title="{{ __($b.'preview.'.$device) }} · {{ $d['width'] }}px" aria-label="{{ __($b.'preview.'.$device) }}">
                                <x-filament::icon :icon="$d['icon']" />
                            </button>
                        @endforeach
                    </div>
                    <div class="lms-seg" role="group" aria-label="{{ __('site::builder.languages.ar') }} / {{ __('site::builder.languages.en') }}">
                        <button type="button" x-on:click="setLang('ar')" x-bind:aria-pressed="lang === 'ar'" lang="ar">ع</button>
                        <button type="button" x-on:click="setLang('en')" x-bind:aria-pressed="lang === 'en'" lang="en">EN</button>
                    </div>
                    <a class="lms-pv-link" x-bind:href="url()" target="_blank" rel="noopener" title="{{ __($b.'preview.open') }}" aria-label="{{ __($b.'preview.open') }}">
                        <x-filament::icon icon="heroicon-o-arrow-top-right-on-square" />
                    </a>
                    <button type="button" class="lms-pv-link" x-show="open" x-cloak x-on:click="open = false" aria-label="{{ __('filament-actions::modal.actions.close.label') }}">
                        <x-filament::icon icon="heroicon-o-x-mark" />
                    </button>
                </div>
                <div class="lms-pv-stage" x-ref="stage" wire:ignore>
                    <div class="lms-pv-shell" x-bind:data-device="device" x-bind:style="shellStyle()">
                        <iframe x-ref="frame0" title="{{ __($b.'preview.title') }}" x-bind:style="frameStyle()" x-bind:data-hidden="active !== 0 ? '' : null"
                            sandbox="allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox allow-forms" loading="eager"></iframe>
                        <iframe x-ref="frame1" title="{{ __($b.'preview.title') }}" x-bind:style="frameStyle()" x-bind:data-hidden="active !== 1 ? '' : null"
                            sandbox="allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox allow-forms" loading="eager"></iframe>
                    </div>
                    <span class="lms-pv-loading" x-show="loading" x-transition.opacity>
                        <x-filament::loading-indicator class="h-4 w-4" />{{ __($b.'preview.loading') }}
                    </span>
                    <p class="lms-pv-fail" x-show="failed" x-cloak>{{ __($b.'preview.unreachable') }}</p>
                </div>
            </aside>
        </div>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
