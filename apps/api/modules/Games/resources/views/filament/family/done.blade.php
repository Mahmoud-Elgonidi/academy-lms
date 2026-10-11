{{-- A round's result over the island (the world's look): stars one by one, how many right first time, homework handed in, the egg filling. --}}
@php
    $l = 'games::games.';
    $pct = round(100 * $result['egg']['have'] / max(1, $result['egg']['need']));
@endphp
<div class="gw-done gw-kids" role="dialog" aria-modal="true" aria-labelledby="gw-done-title" wire:key="done-{{ $result['play'] }}" x-data x-init="LmsIslands.stars({{ (int) $result['stars'] }})">
    <div class="gw-done-card" data-celebrate="games-stars-{{ $learnerId }}" data-celebrate-value="{{ $result['total_stars'] }}">
        <div class="gw-done-stars" role="img" aria-label="{{ trans_choice($l.'stars', $result['stars'], ['count' => $result['stars']]) }}">
            @foreach ([1, 2, 3] as $i)
                <svg data-on="{{ $i <= $result['stars'] ? 'yes' : 'no' }}" style="--i: {{ $i - 1 }}; {{ $i === 2 ? 'margin-top: -14px' : '' }}" width="{{ $i === 2 ? 86 : 66 }}" height="{{ $i === 2 ? 86 : 66 }}" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.5l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.4 6.1 20.5l1.2-6.5L2.5 9.4l6.6-.9z" fill="#FFD43B" stroke="#13294B" stroke-width="1.6" stroke-linejoin="round"/></svg>
            @endforeach
        </div>
        <h2 id="gw-done-title">{{ __($l.'result.'.min(3, $result['stars'])) }}</h2>
        <p>{{ __($l.'result.score', ['correct' => $result['correct'], 'total' => $result['total']]) }}</p>
        @if ($result['homework'])
            <div class="gw-done-hw">✅ {{ __($l.'homework_done', ['title' => $result['homework']]) }}</div>
        @endif
        <div class="gw-eggbar">
            <svg width="22" height="27" viewBox="0 0 18 22" aria-hidden="true"><ellipse cx="9" cy="12" rx="8" ry="10" fill="#F3E3C3" stroke="#13294B" stroke-width="1.6"/><path d="M3 12 L6 9 L9 13 L12 9 L15 12" fill="none" stroke="#13294B" stroke-width="1.4"/></svg>
            <span>{{ __($l.'map.egg_card', ['have' => $result['egg']['have'], 'need' => $result['egg']['need']]) }}</span>
            <i role="progressbar" aria-valuemin="0" aria-valuemax="{{ $result['egg']['need'] }}" aria-valuenow="{{ $result['egg']['have'] }}"><b style="width: {{ $pct }}%"></b></i>
        </div>
        <div class="gw-done-actions">
            <button type="button" class="gw-bigbtn gw-3d" data-kind="go" wire:click="again">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#13294B" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/></svg>
                {{ __($l.'again') }}
            </button>
            <button type="button" class="gw-bigbtn gw-3d" wire:click="leave" x-on:click="LmsIslandsSound?.start()">{{ __($l.'map.to_island') }}</button>
        </div>
    </div>
</div>
