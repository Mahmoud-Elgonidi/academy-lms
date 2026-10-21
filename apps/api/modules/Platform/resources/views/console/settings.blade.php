@php $l = 'platform::settings.'; @endphp
<x-filament-panels::page>
    @include('platform::console.styles')
    <style>
        .ps-promise { margin: 0; padding: .8rem 1rem; display: flex; gap: .6rem; align-items: flex-start; font-size: .88rem; color: var(--gray-700); }
        .ps-promise svg { inline-size: 1.15rem; block-size: 1.15rem; flex-shrink: 0; color: var(--primary-600); margin-block-start: .1rem; }
        /* The save bar stays in reach at the bottom, over the tab's cards rather than across them. */
        .ps-save { position: sticky; inset-block-end: 0; z-index: 5; display: flex; justify-content: flex-end; gap: .75rem; align-items: center;
            margin-inline: -.5rem; padding: .75rem .5rem; border-block-start: 1px solid var(--gray-200);
            background: color-mix(in oklab, var(--gray-50) 88%, transparent); backdrop-filter: blur(6px); }
        .dark .ps-save { border-block-start-color: var(--gray-800); background: color-mix(in oklab, var(--gray-950) 88%, transparent); }
        .ps-save small { font-size: .8rem; color: var(--gray-500); }
        .ps-quran { display: flex; flex-direction: column; gap: .8rem; }
        .ps-quran-bar { display: flex; justify-content: flex-end; }
        .ps-quran dl { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 14rem), 1fr)); gap: .6rem 1rem; }
        .ps-quran dt { font-size: .75rem; color: var(--gray-500); }
        .ps-quran dd { font-size: .88rem; overflow-wrap: anywhere; }
        .ps-hash { font-family: ui-monospace, monospace; font-size: .75rem !important; }
        .ps-missing { color: var(--warning-700) !important; font-size: .88rem; }
        .ps-trouble { padding: .9rem 1.1rem; display: flex; flex-direction: column; gap: .6rem; border-inline-start: 4px solid var(--danger-600); }
        .ps-trouble.is-warn { border-inline-start-color: var(--warning-500); }
        .ps-trouble h2 { font-weight: 600; font-size: 1rem; display: flex; gap: .5rem; align-items: center; }
        .ps-trouble h2 svg { inline-size: 1.2rem; block-size: 1.2rem; color: var(--danger-600); }
        .ps-trouble.is-warn h2 svg { color: var(--warning-600); }
        .ps-trouble ul { display: flex; flex-direction: column; gap: .45rem; margin: 0; padding: 0; list-style: none; font-size: .88rem; }
        .ps-trouble li span { color: var(--gray-600); }
        .ps-trouble li .fail { color: var(--danger-700); font-weight: 600; }
        .ps-trouble li .warn { color: var(--warning-700); font-weight: 600; }
        .ps-trouble a { color: var(--primary-600); font-weight: 600; text-decoration: underline; white-space: nowrap; }
    </style>

    <p class="pc-base ps-promise">
        <x-filament::icon icon="heroicon-o-lock-closed" />
        <span>{{ __($l.'promise') }}</span>
    </p>

    @php $trouble = $this->trouble; @endphp
    @if ($trouble !== [])
        <section @class(['pc-base', 'ps-trouble', 'is-warn' => collect($trouble)->doesntContain('status', 'fail')]) role="status">
            <h2><x-filament::icon icon="heroicon-o-exclamation-triangle" />{{ __($l.'checks.banner.heading') }}</h2>
            <ul>
                @foreach ($trouble as $t)
                    <li>
                        <strong @class([$t['status']])><bdi>{{ $t['name'] }}</bdi></strong>: {{ $t['value'] }}
                        @if ($t['hint'])
                            <span>{{ $t['hint'] }}</span>
                        @endif
                        <a href="?tab={{ $t['tab'] }}#ps-{{ $t['card'] }}">{{ __($l.'checks.banner.go') }}</a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- The AI section's lists of models, from each provider, once the page is open. --}}
    <div wire:init="warmModels" hidden></div>

    <form wire:submit="save" class="flex flex-col gap-6">
        {{ $this->form }}
        <div class="ps-save">
            <small>{{ __($l.'save_all') }}</small>
            <x-filament::button type="submit" icon="heroicon-m-check">{{ __($l.'save') }}</x-filament::button>
        </div>
    </form>

</x-filament-panels::page>
