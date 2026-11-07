@php $m = 'platform::modules.'; @endphp
<x-filament-panels::page>
    <style>
        .am-page { display: grid; gap: 2.25rem; }
        .am-section { display: grid; gap: .75rem; }
        .am-head { display: flex; flex-wrap: wrap; align-items: baseline; gap: .25rem .75rem; }
        .am-head h2 { margin: 0; font-size: 1rem; font-weight: 700; color: var(--gray-950); }
        .dark .am-head h2 { color: white; }
        .am-head p { margin: 0; font-size: .875rem; color: var(--gray-500); }
        .am-grid { display: grid; gap: .75rem; grid-template-columns: repeat(auto-fill, minmax(min(100%, 17rem), 1fr)); }
        .am-card { display: grid; gap: .5rem; align-content: start; padding: 1rem; border-radius: var(--radius-xl); background: white; box-shadow: 0 0 0 1px var(--gray-200); }
        .dark .am-card { background: rgb(255 255 255 / .04); box-shadow: 0 0 0 1px rgb(255 255 255 / .1); }
        .am-card.am-off { background: var(--gray-50); }
        .dark .am-card.am-off { background: transparent; }
        .am-top { display: flex; align-items: center; justify-content: space-between; gap: .5rem; }
        .am-name { margin: 0; font-weight: 600; color: var(--gray-950); }
        .dark .am-name { color: white; }
        .am-line { margin: 0; font-size: .8125rem; line-height: 1.6; color: var(--gray-600); }
        .dark .am-line { color: var(--gray-400); }
        .am-note { margin: 0; font-size: .8125rem; color: var(--gray-500); }
        .am-chip { display: inline-flex; align-items: center; gap: .3rem; padding: .1rem .55rem; border-radius: 999px; font-size: .75rem; font-weight: 600; white-space: nowrap; }
        .am-chip-on { color: var(--success-700); background: var(--success-50); }
        .am-chip-off { color: var(--gray-600); background: var(--gray-100); }
        .am-chip-core { color: var(--primary-700); background: var(--primary-50); }
        .am-chip-never { color: var(--warning-700); background: var(--warning-50); }
        .dark .am-chip-on { color: var(--success-300); background: rgb(255 255 255 / .06); }
        .dark .am-chip-off, .dark .am-chip-core, .dark .am-chip-never { color: var(--gray-300); background: rgb(255 255 255 / .06); }
        .am-foot { display: flex; align-items: center; justify-content: space-between; gap: .5rem; margin-top: .25rem; }
        .am-core { display: grid; gap: .25rem .75rem; grid-template-columns: repeat(auto-fill, minmax(min(100%, 15rem), 1fr)); padding: 1rem; border-radius: var(--radius-xl); box-shadow: 0 0 0 1px var(--gray-200); }
        .dark .am-core { box-shadow: 0 0 0 1px rgb(255 255 255 / .1); }
        .am-core-row { display: grid; gap: .1rem; padding: .5rem 0; }
        .am-core-row .am-top { justify-content: flex-start; }
    </style>

    <div class="am-page">
        <section class="am-section">
            <div class="am-head"><h2>{{ __($m.'sections.optional.title') }}</h2><p>{{ __($m.'sections.optional.hint') }}</p></div>
            <div class="am-grid lms-stagger">
                @foreach ($optional as $module)
                    <article @class(['am-card', 'lms-lift', 'am-off' => ! $module['on']]) wire:key="module-{{ $module['key'] }}" data-module="{{ $module['key'] }}">
                        <div class="am-top">
                            <p class="am-name">{{ $module['label'] }}</p>
                            <span @class(['am-chip', 'am-chip-on' => $module['on'], 'am-chip-off' => ! $module['on']])>{{ __($m.'state.'.($module['on'] ? 'on' : 'off')) }}</span>
                        </div>
                        <p class="am-line">{{ $module['line'] }}</p>
                        @if ($module['note'])
                            <p class="am-note">{{ $module['note'] }}</p>
                        @endif
                        @if ($module['switchable'])
                            <div class="am-foot">
                                <span></span>
                                {{ ($this->toggleAction)(['feature' => $module['key'], 'on' => $module['hidden']]) }}
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>

        <section class="am-section">
            <div class="am-head"><h2>{{ __($m.'sections.paid.title') }}</h2><p>{{ __($m.'sections.paid.hint') }}</p></div>
            <div class="am-grid lms-stagger">
                @foreach ($paid as $module)
                    <article @class(['am-card', 'am-off' => ! $module['on']]) wire:key="paid-{{ $module['key'] }}" data-module="{{ $module['key'] }}">
                        <div class="am-top">
                            <p class="am-name">{{ $module['label'] }}</p>
                            <span @class(['am-chip', 'am-chip-on' => $module['on'], 'am-chip-off' => ! $module['on']])>{{ __($m.'state.'.($module['on'] ? 'on' : 'off')) }}</span>
                        </div>
                        <p class="am-line">{{ $module['line'] }}</p>
                        <p class="am-note">{{ $module['note'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="am-section">
            <div class="am-head"><h2>{{ __($m.'sections.core.title') }}</h2><p>{{ __($m.'sections.core.hint') }}</p></div>
            <div class="am-core lms-stagger">
                @foreach ($core as $module)
                    <div class="am-core-row" wire:key="core-{{ $module['key'] }}">
                        <div class="am-top">
                            <p class="am-name">{{ $module['label'] }}</p>
                            <span @class(['am-chip', 'am-chip-never' => $module['never_off'], 'am-chip-core' => ! $module['never_off']])>{{ __($m.($module['never_off'] ? 'never_off' : 'always_on')) }}</span>
                        </div>
                        <p class="am-line">{{ $module['line'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</x-filament-panels::page>
