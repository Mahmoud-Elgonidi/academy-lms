{{--
    One choice among a few, as large cards (a ViewField of any form): each card
    is a real radio (keyboard, screen readers), its title, a line on what it
    means, an icon and an optional note under it ("أقل بـ 35%", "−90 ر.س."). The
    chosen one carries the brand's colour; a card can wear the group tone
    (data-tone="group") so private and group never look alike. Disabled (a plan
    someone is on): shown, not changed, with the field's hint saying why.
    viewData: options (list of {value, title, line?, icon?, note?, tone?}), columns (1–4).
--}}
@php
    $statePath = $getStatePath();
    $options = $options ?? [];
    $columns = $columns ?? min(4, max(1, count($options)));
    $disabled = $isDisabled();
    $live = $live ?? true;
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <style>
        .lms-cc { display: grid; gap: .65rem; grid-template-columns: repeat(var(--cc-cols), minmax(0, 1fr)); }
        @media (max-width: 639px) { .lms-cc { grid-template-columns: minmax(0, 1fr); } }
        .lms-cc-card { position: relative; display: flex; gap: .75rem; align-items: flex-start; padding: .9rem 1rem; border-radius: var(--radius-xl, .75rem);
            border: 1.5px solid var(--gray-200); background: white; cursor: pointer; text-align: start; transition: border-color .15s, box-shadow .15s, background-color .15s; }
        .lms-cc-card:hover { border-color: var(--primary-300); }
        .lms-cc-card:has(input:focus-visible) { outline: 2px solid var(--primary-600); outline-offset: 2px; }
        .lms-cc-card:has(input:checked) { border-color: var(--primary-600); background: var(--primary-50); box-shadow: 0 0 0 1px var(--primary-600); }
        .lms-cc-card[data-tone="group"]:has(input:checked) { border-color: var(--info-600); background: var(--info-50); box-shadow: 0 0 0 1px var(--info-600); }
        .lms-cc-card[data-disabled] { cursor: not-allowed; }
        .lms-cc-card[data-disabled]:not(:has(input:checked)) { opacity: .5; }
        .lms-cc-card input { position: absolute; opacity: 0; pointer-events: none; }
        .lms-cc-ico { display: grid; place-items: center; width: 2.4rem; height: 2.4rem; border-radius: .7rem; flex-shrink: 0; background: var(--gray-100); color: var(--gray-600); }
        .lms-cc-card:has(input:checked) .lms-cc-ico { background: var(--primary-600); color: white; }
        .lms-cc-card[data-tone="group"] .lms-cc-ico { background: var(--info-50); color: var(--info-700); }
        .lms-cc-card[data-tone="group"]:has(input:checked) .lms-cc-ico { background: var(--info-600); color: white; }
        .lms-cc-body { display: flex; flex-direction: column; gap: .15rem; min-width: 0; flex: 1; }
        .lms-cc-title { font-weight: 700; font-size: .95rem; color: var(--gray-950); }
        .lms-cc-line { font-size: .8rem; line-height: 1.45; color: var(--gray-500); }
        .lms-cc-note { margin-top: .3rem; font-size: .78rem; font-weight: 700; color: var(--primary-700); font-variant-numeric: tabular-nums; }
        .lms-cc-card[data-tone="group"] .lms-cc-note { color: var(--info-700); }
        .lms-cc-check { position: absolute; top: .6rem; inset-inline-end: .6rem; width: 1.15rem; height: 1.15rem; border-radius: 50%; border: 1.5px solid var(--gray-300); display: grid; place-items: center; }
        .lms-cc-card:has(input:checked) .lms-cc-check { border-color: var(--primary-600); background: var(--primary-600); }
        .lms-cc-card[data-tone="group"]:has(input:checked) .lms-cc-check { border-color: var(--info-600); background: var(--info-600); }
        .lms-cc-check svg { width: .75rem; height: .75rem; color: white; opacity: 0; }
        .lms-cc-card:has(input:checked) .lms-cc-check svg { opacity: 1; }
        .dark .lms-cc-card { background: var(--gray-900); border-color: var(--gray-700); }
        .dark .lms-cc-card:has(input:checked) { background: color-mix(in oklab, var(--primary-500) 14%, var(--gray-900)); border-color: var(--primary-500); box-shadow: 0 0 0 1px var(--primary-500); }
        .dark .lms-cc-card[data-tone="group"]:has(input:checked) { background: color-mix(in oklab, var(--info-500) 14%, var(--gray-900)); border-color: var(--info-500); box-shadow: 0 0 0 1px var(--info-500); }
        .dark .lms-cc-ico { background: var(--gray-800); color: var(--gray-300); }
        .dark .lms-cc-card[data-tone="group"] .lms-cc-ico { background: color-mix(in oklab, var(--info-500) 18%, var(--gray-900)); color: var(--info-300); }
        .dark .lms-cc-title { color: white; }
        .dark .lms-cc-line { color: var(--gray-400); }
        .dark .lms-cc-note { color: var(--primary-400); }
        .dark .lms-cc-card[data-tone="group"] .lms-cc-note { color: var(--info-300); }
        .dark .lms-cc-check { border-color: var(--gray-600); }
        @media (prefers-reduced-motion: no-preference) {
            .lms-cc-card:has(input:checked) .lms-cc-check svg { animation: lms-cc-pop .22s ease-out backwards; }
            @keyframes lms-cc-pop { from { opacity: 0; transform: scale(.4); } }
        }
    </style>

    <div class="lms-cc" role="radiogroup" style="--cc-cols: {{ $columns }}" @if ($field->getLabel()) aria-label="{{ $field->getLabel() }}" @endif>
        @foreach ($options as $option)
            @php $id = str_replace('.', '-', $statePath).'-'.$option['value']; @endphp
            <label class="lms-cc-card" for="{{ $id }}" wire:key="{{ $id }}" data-value="{{ $option['value'] }}"
                @if (($option['tone'] ?? null) !== null) data-tone="{{ $option['tone'] }}" @endif
                @if ($disabled) data-disabled @endif>
                <input type="radio" id="{{ $id }}" value="{{ $option['value'] }}" name="{{ $statePath }}"
                    @if ($live) wire:model.live="{{ $statePath }}" @else wire:model="{{ $statePath }}" @endif
                    @disabled($disabled)>
                @if (! empty($option['icon']))
                    <span class="lms-cc-ico" aria-hidden="true"><x-filament::icon :icon="$option['icon']" style="width: 1.3rem; height: 1.3rem" /></span>
                @endif
                <span class="lms-cc-body">
                    <span class="lms-cc-title">{{ $option['title'] }}</span>
                    @if (! empty($option['line']))
                        <span class="lms-cc-line">{{ $option['line'] }}</span>
                    @endif
                    @if (! empty($option['note']))
                        <span class="lms-cc-note">{{ $option['note'] }}</span>
                    @endif
                </span>
                <span class="lms-cc-check" aria-hidden="true"><x-filament::icon icon="heroicon-m-check" /></span>
            </label>
        @endforeach
    </div>
</x-dynamic-component>
