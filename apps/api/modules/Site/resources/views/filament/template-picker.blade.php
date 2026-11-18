{{-- The templates as cards, each with the shape of its blocks drawn in order. viewData: templates, count. --}}
@php $statePath = $getStatePath(); @endphp
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div style="display: grid; gap: .8rem; grid-template-columns: repeat(auto-fill, minmax(15rem, 1fr))" role="radiogroup">
        @foreach ($templates as $t)
            @php $id = 'tpl-'.$t['key']; @endphp
            <label for="{{ $id }}" wire:key="{{ $id }}" class="lms-tpl">
                <input type="radio" id="{{ $id }}" name="{{ $statePath }}" value="{{ $t['key'] }}" wire:model="{{ $statePath }}">
                <span class="lms-tpl__strip" aria-hidden="true">
                    @foreach (array_slice($t['blocks'], 0, 6) as $block)
                        <span class="lms-tpl__row">{!! $block['svg'] !!}</span>
                    @endforeach
                </span>
                <span class="lms-tpl__name">{{ $t['name'] }}</span>
                <span class="lms-tpl__line">{{ $t['line'] }}</span>
                <span class="lms-tpl__blocks">{{ implode(' · ', array_column($t['blocks'], 'name')) }}</span>
            </label>
        @endforeach
    </div>
    <p style="margin-top: .8rem; font-size: .8rem; color: var(--warning-700)">{{ __('site::builder.templates.confirm', ['count' => $count]) }}</p>
    <style>
        .lms-tpl { position: relative; display: flex; flex-direction: column; gap: .35rem; padding: .7rem; border-radius: .9rem; border: 1.5px solid var(--gray-200); background: white; cursor: pointer; transition: border-color .15s, box-shadow .15s; }
        .dark .lms-tpl { background: var(--gray-900); border-color: var(--gray-700); }
        .lms-tpl:hover { border-color: var(--primary-300); }
        .lms-tpl:has(input:checked) { border-color: var(--primary-600); box-shadow: 0 0 0 1px var(--primary-600); }
        .lms-tpl:has(input:focus-visible) { outline: 2px solid var(--primary-600); outline-offset: 2px; }
        .lms-tpl input { position: absolute; opacity: 0; pointer-events: none; }
        .lms-tpl__strip { display: flex; flex-direction: column; gap: 3px; padding: .45rem; border-radius: .6rem; background: var(--gray-50); color: var(--gray-500); max-height: 13rem; overflow: hidden; }
        .dark .lms-tpl__strip { background: var(--gray-800); color: var(--gray-300); }
        .lms-tpl__row { display: block; background: white; border-radius: .35rem; padding: 2px 6px; }
        .dark .lms-tpl__row { background: var(--gray-900); }
        .lms-tpl__row svg { display: block; width: 100%; height: 1.65rem; }
        .lms-tpl__name { font-weight: 700; font-size: .92rem; margin-top: .2rem; }
        .lms-tpl__line { font-size: .78rem; color: var(--gray-500); line-height: 1.5; }
        .lms-tpl__blocks { font-size: .7rem; color: var(--gray-400); line-height: 1.5; }
    </style>
</x-dynamic-component>
