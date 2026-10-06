@php $l = 'evaluation::ratings.'; @endphp
<div class="ev-chip" @if (! $open) hidden @endif>
    @if ($open)
        <style>
            .ev-chip { grid-column: 1 / -1; display: flex; flex-wrap: wrap; align-items: center; gap: .35rem .75rem; padding-top: .55rem; border-top: 1px dashed var(--gray-200); }
            .ev-chip > span { font-size: .82rem; color: var(--gray-600); }
            .ev-stars { display: inline-flex; gap: .1rem; }
            .ev-stars button { width: 1.9rem; height: 1.9rem; display: grid; place-items: center; border-radius: .5rem; color: var(--gray-300); transition: transform .15s; }
            .ev-stars button svg { width: 1.35rem; height: 1.35rem; }
            .ev-stars button[data-on="1"] { color: var(--warning-400); }
            .ev-stars button:hover { transform: scale(1.12); }
            .ev-words { display: flex; flex-wrap: wrap; gap: .3rem; }
            .ev-words button { font-size: .75rem; padding: .15rem .6rem; border-radius: 999px; border: 1px solid var(--gray-200); color: var(--gray-700); }
            .ev-words button[aria-pressed="true"] { background: var(--primary-50); border-color: var(--primary-300); color: var(--primary-700); font-weight: 600; }
            .ev-thanks { font-size: .8rem; font-weight: 600; color: var(--success-700); }
            .dark .ev-chip { border-top-color: var(--gray-800); }
            .dark .ev-stars button { color: var(--gray-600); }
            .dark .ev-words button { border-color: var(--gray-700); color: var(--gray-300); }
            @media (prefers-reduced-motion: no-preference) { .ev-thanks { animation: ev-pop .4s cubic-bezier(.34, 1.56, .64, 1) backwards; } }
            @keyframes ev-pop { from { opacity: 0; transform: scale(.6); } }
        </style>
        <span>{{ $stars === null ? __($l.'ask') : __($l.'yours') }}</span>
        <span class="ev-stars" role="radiogroup" aria-label="{{ __($l.'ask') }}">
            @foreach (range(1, 5) as $n)
                <button type="button" wire:click="rate({{ $n }})" data-on="{{ $stars !== null && $n <= $stars ? 1 : 0 }}" role="radio"
                    aria-checked="{{ $stars === $n ? 'true' : 'false' }}" aria-label="{{ trans_choice($l.'stars', $n, ['count' => $n]) }}">
                    <x-filament::icon icon="heroicon-s-star" />
                </button>
            @endforeach
        </span>
        @if ($thanked)<span class="ev-thanks">{{ __($l.'thanks') }}</span>@endif
        @if ($stars !== null)
            <span class="ev-words">
                @foreach ($this->choices() as $tag)
                    <button type="button" wire:click="toggle('{{ $tag }}')" aria-pressed="{{ in_array($tag, $tags, true) ? 'true' : 'false' }}">{{ __($l.'tags.'.$tag) }}</button>
                @endforeach
            </span>
        @endif
    @endif
</div>
