{{-- A hifz map (Queries\Hifz\HifzMap::for): each part a row, each page a square in its strength; a legend and the counts. $map, $compact --}}
@php
    $h = 'learning::hifz.';
    $tones = ['new' => 'new', 'learning' => 'learning', 'strong' => 'strong', 'fading' => 'fading', 'weak' => 'weak'];
@endphp
@once
    <style>
        .hz { display: flex; flex-direction: column; gap: .6rem; }
        .hz-counts { display: flex; flex-wrap: wrap; gap: .4rem .9rem; font-size: .85rem; color: var(--gray-600); }
        .hz-counts strong { color: var(--gray-950); font-size: 1rem; }
        .hz-rows { display: flex; flex-direction: column; gap: .25rem; }
        .hz-row { display: flex; align-items: center; gap: .5rem; }
        .hz-row b { inline-size: 2.1rem; flex: none; font-size: .7rem; font-weight: 600; color: var(--gray-500); text-align: end; }
        .hz-pages { display: flex; flex-wrap: wrap; gap: 2px; }
        .hz-pages i { display: block; inline-size: .7rem; block-size: .7rem; border-radius: 2px; background: var(--gray-200); }
        .hz-pages i[data-s="learning"], .hz-legend i[data-s="learning"] { background: var(--info-400); }
        .hz-pages i[data-s="strong"], .hz-legend i[data-s="strong"] { background: var(--primary-500); }
        .hz-pages i[data-s="fading"], .hz-legend i[data-s="fading"] { background: var(--warning-400); }
        .hz-pages i[data-s="weak"], .hz-legend i[data-s="weak"] { background: var(--danger-500); }
        .hz-legend { display: flex; flex-wrap: wrap; gap: .35rem .85rem; font-size: .78rem; color: var(--gray-600); }
        .hz-legend span { display: inline-flex; align-items: center; gap: .3rem; }
        .hz-legend i { display: inline-block; inline-size: .7rem; block-size: .7rem; border-radius: 2px; background: var(--gray-200); }
        .dark .hz-pages i, .dark .hz-legend i { background: var(--gray-700); }
        @media (prefers-reduced-motion: no-preference) { .hz-row { animation: hz-in .35s ease-out backwards; } }
        @keyframes hz-in { from { opacity: 0; transform: translateY(4px); } }
    </style>
@endonce
<div class="hz">
    <div class="hz-counts">
        <span><strong>{{ $map['counts']['memorized'] }}</strong> {{ __($h.'counts.memorized') }}</span>
        <span><strong>{{ $map['counts']['learning'] }}</strong> {{ __($h.'counts.learning') }}</span>
        <span><strong>{{ $map['counts']['due'] }}</strong> {{ __($h.'counts.due') }}</span>
    </div>
    <div class="hz-rows" role="img" aria-label="{{ __($h.'map_label', ['memorized' => $map['counts']['memorized'], 'total' => $map['pages_total']]) }}">
        @foreach ($map['juzs'] as $juz)
            <div class="hz-row">
                <b>{{ __($h.'juz_short', ['juz' => $juz['juz']]) }}</b>
                <span class="hz-pages">
                    @foreach ($juz['pages'] as $p)<i data-s="{{ $tones[$p['strength']] ?? 'new' }}" title="{{ __($h.'page', ['page' => $p['page']]) }} · {{ __($h.'strengths.'.$p['strength']) }}"></i>@endforeach
                </span>
            </div>
        @endforeach
    </div>
    <div class="hz-legend">
        @foreach (['strong', 'fading', 'weak', 'learning', 'new'] as $s)
            <span><i data-s="{{ $s }}"></i>{{ __($h.'strengths.'.$s) }}</span>
        @endforeach
    </div>
</div>
