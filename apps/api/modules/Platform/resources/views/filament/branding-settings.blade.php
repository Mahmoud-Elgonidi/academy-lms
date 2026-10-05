@php
    $t = $this->previewTokens();
    $r = $t->radii();
    $neutralInk = ['slate' => '#475569', 'gray' => '#4B5563', 'zinc' => '#52525B', 'neutral' => '#525252', 'stone' => '#57534E'][$t->neutral];
    $neutralBg = ['slate' => '#F8FAFC', 'gray' => '#F9FAFB', 'zinc' => '#FAFAFA', 'neutral' => '#FAFAFA', 'stone' => '#FAFAF9'][$t->neutral];
    $applied = app(\Modules\Platform\Contracts\BrandingApi::class)->current();
    $base = $this->baseTemplate();
    $modified = $this->isModified();
    $differsFromApplied = $this->baseRef !== $applied->templateRef || $modified !== $applied->modified;
@endphp

<x-filament-panels::page>
    <link rel="stylesheet" href="{{ $this->fontsUrl() }}">

    <style>
        .lms-brand-status { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; font-size: .875rem; }
        .lms-chip { display: inline-flex; align-items: center; gap: .35rem; padding: .15rem .6rem; border-radius: 999px; font-size: .75rem; font-weight: 600; background: rgb(0 0 0 / .05); }
        .lms-chip--warn { background: color-mix(in srgb, var(--lms-attention) 16%, white); color: color-mix(in srgb, var(--lms-attention) 70%, black); }
        .lms-chip--ok { background: color-mix(in srgb, var(--lms-primary) 14%, white); color: var(--lms-primary); }
        .lms-templates { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: .75rem; }
        .lms-template { position: relative; border: 1px solid rgb(0 0 0 / .1); border-radius: var(--radius-xl); background: white; }
        .lms-template[data-base="true"] { border-color: var(--lms-primary); box-shadow: 0 0 0 2px color-mix(in srgb, var(--lms-primary) 30%, transparent); }
        .lms-template > button { display: flex; flex-direction: column; gap: .5rem; width: 100%; padding: .85rem; text-align: start; cursor: pointer; background: none; border: 0; font: inherit; color: inherit; }
        .lms-swatches { display: flex; gap: .25rem; }
        .lms-swatches span { width: 1.5rem; height: 1.5rem; border-radius: 999px; border: 1px solid rgb(0 0 0 / .08); }
        .lms-template-name { font-weight: 700; }
        .lms-template-meta { font-size: .75rem; opacity: .7; }
        .lms-template-delete { position: absolute; top: .4rem; inset-inline-end: .4rem; }
        .lms-brand-layout { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1.5rem; align-items: start; }
        @media (min-width: 1100px) { .lms-brand-layout { grid-template-columns: minmax(0, 1fr) 380px; } .lms-preview-wrap { position: sticky; top: 5rem; } }
        .lms-warnings { display: flex; flex-direction: column; gap: .4rem; margin-bottom: .75rem; }
        .lms-warning { padding: .6rem .8rem; border-radius: var(--radius-lg); background: color-mix(in srgb, var(--lms-attention) 14%, white); color: color-mix(in srgb, var(--lms-attention) 70%, black); font-size: .85rem; }

        /* The preview is drawn only from the tokens on screen, not from the panel's current look. */
        .lms-preview { --p: {{ $t->primary }}; --a: {{ $t->attention }}; --d: {{ $t->danger }}; --i: {{ $t->info }};
            --ink: #12201B; --muted: {{ $neutralInk }}; --bg: {{ $neutralBg }};
            --r-sm: {{ $r['sm'] }}; --r-md: {{ $r['md'] }}; --r-lg: {{ $r['lg'] }}; --r-xl: {{ $r['xl'] }};
            font-family: '{{ $t->bodyFont }}', system-ui, sans-serif; color: var(--ink); background: var(--bg);
            border: 1px solid rgb(0 0 0 / .1); border-radius: var(--r-xl); overflow: hidden; }
        .lms-preview h3, .lms-preview .pv-num, .lms-preview .pv-brand { font-family: '{{ $t->headingFont }}', '{{ $t->bodyFont }}', sans-serif; }
        .pv-top { display: flex; align-items: center; gap: .6rem; padding: .7rem .9rem; background: white; border-bottom: 1px solid rgb(0 0 0 / .08); }
        .pv-logo { width: 2rem; height: 2rem; border-radius: var(--r-lg); background: var(--p); color: white; display: grid; place-items: center; font-weight: 700; overflow: hidden; }
        .pv-logo img { width: 100%; height: 100%; object-fit: contain; background: white; }
        .pv-brand { font-weight: 700; }
        .pv-body { padding: 1rem; display: flex; flex-direction: column; gap: .8rem; }
        .pv-body h3 { margin: 0; font-size: 1.35rem; font-weight: 600; }
        .pv-body p { margin: 0; color: var(--muted); font-size: .85rem; }
        .pv-row { display: flex; flex-wrap: wrap; gap: .4rem; }
        .pv-btn { padding: .5rem .9rem; border-radius: var(--r-lg); font-weight: 600; font-size: .85rem; border: 1px solid transparent; }
        .pv-btn--primary { background: var(--p); color: white; }
        .pv-btn--ghost { background: white; border-color: rgb(0 0 0 / .14); color: var(--ink); }
        .pv-btn--danger { background: var(--d); color: white; }
        .pv-badge { padding: .1rem .55rem; border-radius: 999px; font-size: .75rem; font-weight: 600; }
        .pv-badge--p { background: color-mix(in srgb, var(--p) 14%, white); color: color-mix(in srgb, var(--p) 85%, black); }
        .pv-badge--a { background: color-mix(in srgb, var(--a) 18%, white); color: color-mix(in srgb, var(--a) 65%, black); }
        .pv-badge--d { background: color-mix(in srgb, var(--d) 12%, white); color: color-mix(in srgb, var(--d) 85%, black); }
        .pv-badge--i { background: color-mix(in srgb, var(--i) 12%, white); color: color-mix(in srgb, var(--i) 85%, black); }
        .pv-cards { display: grid; grid-template-columns: 1fr 1fr; gap: .6rem; }
        .pv-card { background: white; border: 1px solid rgb(0 0 0 / .08); border-radius: var(--r-xl); padding: .7rem .8rem; }
        .pv-card small { color: var(--muted); font-size: .75rem; }
        .pv-num { font-size: 1.6rem; font-weight: 600; line-height: 1.2; }
        .pv-alert { display: flex; align-items: center; justify-content: space-between; gap: .6rem; padding: .7rem .8rem; border-radius: var(--r-xl);
            background: color-mix(in srgb, var(--a) 12%, white); border: 1px solid color-mix(in srgb, var(--a) 35%, white); }
        .pv-alert strong { font-size: .9rem; }
        .pv-alert > div > span { display: block; font-size: .78rem; color: var(--muted); }
        .pv-nav { display: flex; flex-direction: column; gap: .15rem; font-size: .85rem; }
        .pv-nav span { padding: .4rem .6rem; border-radius: var(--r-lg); color: var(--muted); }
        .pv-nav span[data-active] { background: color-mix(in srgb, var(--p) 12%, white); color: var(--p); font-weight: 600; }
    </style>

    <div class="lms-brand-status">
        <span>{{ __('platform::branding.applied_now') }}</span>
        <span class="lms-chip lms-chip--ok">{{ $applied->templateName ?? __('platform::branding.custom') }}@if ($applied->modified) · {{ __('platform::branding.edited') }}@endif</span>
        @if ($differsFromApplied)
            <span class="lms-chip lms-chip--warn">{{ __('platform::branding.unsaved') }}</span>
        @endif
    </div>

    <x-filament::section :heading="__('platform::branding.sections.templates')" :description="__('platform::branding.templates_hint')">
        <div class="lms-templates" role="list">
            @foreach ($this->templates() as $template)
                <div class="lms-template" role="listitem" data-base="{{ $template['base'] ? 'true' : 'false' }}" wire:key="tpl-{{ $template['ref'] }}">
                    <button type="button" wire:click="pickTemplate(@js($template['ref']))" aria-pressed="{{ $template['base'] ? 'true' : 'false' }}">
                        <span class="lms-swatches" aria-hidden="true">
                            @foreach ($template['swatches'] as $swatch)
                                <span style="background: {{ $swatch }}"></span>
                            @endforeach
                        </span>
                        <span class="lms-template-name">{{ $template['name'] }}</span>
                        <span class="lms-template-meta">{{ $template['fonts'] }}</span>
                        <span class="lms-row" style="display: flex; gap: .3rem; flex-wrap: wrap">
                            <span class="lms-chip">{{ $template['builtIn'] ? __('platform::branding.built_in') : __('platform::branding.yours') }}</span>
                            @if ($template['active'])
                                <span class="lms-chip lms-chip--ok">{{ __('platform::branding.in_use') }}</span>
                            @endif
                            @if ($template['base'] && $modified)
                                <span class="lms-chip lms-chip--warn">{{ __('platform::branding.edited') }}</span>
                            @endif
                        </span>
                    </button>
                    @unless ($template['builtIn'])
                        <div class="lms-template-delete">{{ ($this->deleteTemplateAction)(['ref' => $template['ref']]) }}</div>
                    @endunless
                </div>
            @endforeach
            @if ($this->baseRef === null)
                <div class="lms-template" data-base="true">
                    <button type="button" disabled>
                        <span class="lms-swatches" aria-hidden="true">
                            @foreach ([$t->primary, $t->attention, $t->danger, $t->info] as $swatch)
                                <span style="background: {{ $swatch }}"></span>
                            @endforeach
                        </span>
                        <span class="lms-template-name">{{ __('platform::branding.custom') }}</span>
                        <span class="lms-template-meta">{{ __('platform::branding.custom_hint') }}</span>
                    </button>
                </div>
            @endif
        </div>
    </x-filament::section>

    <div class="lms-brand-layout">
        <div>
            {{ $this->form }}
        </div>

        <div class="lms-preview-wrap">
            @if ($warnings = $this->contrastWarnings())
                <div class="lms-warnings" role="status">
                    @foreach ($warnings as $warning)
                        <div class="lms-warning">{{ $warning }}</div>
                    @endforeach
                </div>
            @endif
            <div class="lms-preview" aria-label="{{ __('platform::branding.preview') }}">
                <div class="pv-top">
                    <span class="pv-logo">
                        @if ($applied->logoUrl)
                            <img src="{{ $applied->logoUrl }}" alt="">
                        @else
                            {{ mb_substr($this->data['name_ar'] ?? 'ن', 0, 1) }}
                        @endif
                    </span>
                    <span class="pv-brand">{{ app()->getLocale() === 'ar' ? ($this->data['name_ar'] ?? '') : ($this->data['name_en'] ?? '') }}</span>
                    <span class="pv-badge pv-badge--p" style="margin-inline-start: auto">{{ __('platform::branding.preview') }}</span>
                </div>
                <div class="pv-body">
                    <div class="pv-nav">
                        <span data-active>{{ __('platform::branding.sample.nav_live') }}</span>
                        <span>{{ __('platform::branding.sample.nav_schedule') }}</span>
                    </div>
                    <div>
                        <h3>{{ __('platform::branding.sample.heading') }}</h3>
                        <p>{{ __('platform::branding.sample.subheading') }}</p>
                    </div>
                    <div class="pv-row">
                        <span class="pv-btn pv-btn--primary">{{ __('platform::branding.sample.primary') }}</span>
                        <span class="pv-btn pv-btn--ghost">{{ __('platform::branding.sample.secondary') }}</span>
                        <span class="pv-btn pv-btn--danger">{{ __('platform::branding.sample.danger') }}</span>
                    </div>
                    <div class="pv-row">
                        <span class="pv-badge pv-badge--p">{{ __('platform::branding.sample.live') }}</span>
                        <span class="pv-badge pv-badge--a">{{ __('platform::branding.sample.late') }}</span>
                        <span class="pv-badge pv-badge--d">{{ __('platform::branding.sample.absent') }}</span>
                        <span class="pv-badge pv-badge--i">{{ __('platform::branding.sample.trial') }}</span>
                    </div>
                    <div class="pv-cards">
                        <div class="pv-card"><small>{{ __('platform::branding.sample.kpi_today') }}</small><div class="pv-num">186</div></div>
                        <div class="pv-card"><small>{{ __('platform::branding.sample.kpi_live') }}</small><div class="pv-num" style="color: var(--p)">23</div></div>
                    </div>
                    <div class="pv-alert">
                        <div><strong>{{ __('platform::branding.sample.alert') }}</strong><span>{{ __('platform::branding.sample.alert_hint') }}</span></div>
                        <span class="pv-btn pv-btn--primary">{{ __('platform::branding.sample.call') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
