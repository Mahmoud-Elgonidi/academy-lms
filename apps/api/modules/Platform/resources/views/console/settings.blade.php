@php $l = 'platform::settings.'; @endphp
<x-filament-panels::page>
    @include('platform::console.styles')
    <style>
        .ps-promise { margin: 0; padding: .8rem 1rem; display: flex; gap: .6rem; align-items: flex-start; font-size: .88rem; color: var(--gray-700); }
        .ps-promise svg { inline-size: 1.15rem; block-size: 1.15rem; flex-shrink: 0; color: var(--primary-600); margin-block-start: .1rem; }
        .ps-save { position: sticky; inset-block-end: .75rem; z-index: 5; display: flex; justify-content: flex-end; }
        .ps-quran { padding: 1rem 1.1rem; display: flex; flex-direction: column; gap: .8rem; }
        .ps-quran header { display: flex; flex-wrap: wrap; gap: .75rem; justify-content: space-between; align-items: flex-start; }
        .ps-quran h2 { font-weight: 600; font-size: 1rem; }
        .ps-quran p { font-size: .85rem; color: var(--gray-600); margin-block-start: .2rem; }
        .ps-quran dl { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 14rem), 1fr)); gap: .6rem 1rem; }
        .ps-quran dt { font-size: .75rem; color: var(--gray-500); }
        .ps-quran dd { font-size: .88rem; overflow-wrap: anywhere; }
        .ps-hash { font-family: ui-monospace, monospace; font-size: .75rem !important; }
        .ps-missing { color: var(--warning-700) !important; }
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
                        <a href="#ps-{{ $t['group'] }}">{{ __($l.'checks.banner.go') }}</a>
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
            <x-filament::button type="submit" icon="heroicon-m-check">{{ __($l.'save') }}</x-filament::button>
        </div>
    </form>

    @php $q = $this->quran; @endphp
    <section class="pc-base ps-quran">
        <header>
            <div>
                <h2>{{ __($l.'quran.title') }}</h2>
                <p>{{ __($l.'quran.about') }}</p>
            </div>
            {{ $this->fetchQuranAction }}
        </header>
        @if ($q['available'])
            <dl>
                <div><dt>{{ __($l.'quran.where') }}</dt><dd>{{ __($l.'quran.where_'.$q['where']) }}{{ $q['fetched_at'] ? ' · '.$q['fetched_at'] : '' }}</dd></div>
                <div><dt>{{ __($l.'quran.copy') }}</dt><dd dir="ltr">{{ $q['notice'] }}</dd></div>
                <div><dt>{{ __($l.'quran.verses') }}</dt><dd>{{ number_format((int) $q['verses']) }}</dd></div>
                @if ($q['text_sha256'])
                    <div><dt>SHA-256</dt><dd dir="ltr" class="ps-hash">{{ $q['text_sha256'] }}</dd></div>
                @endif
            </dl>
        @else
            <p class="ps-missing">{{ __($l.'quran.missing') }}</p>
        @endif
    </section>
</x-filament-panels::page>
