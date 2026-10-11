@php
    /*
     * A round's result for middle school, secondary school, adults and a guardian playing together: the stars,
     * how many right first time and in how long (against the best time on that lesson), homework handed in, a
     * creature hatched, the egg filling.
     */
    $l = 'games::games.';
    $h = $l.'hub.';
    $before = $result['best_before'] ?? null;
    $kicker = ! ($result['raced'] ?? false) ? null : ($before === null ? __($h.'first') : (($result['seconds'] ?? PHP_INT_MAX) < $before ? __($h.'beat') : __($h.'close')));
    $secs = fn (?int $s) => $s === null ? '—' : __($h.'seconds', ['s' => $s]);
@endphp
<div class="gw-res" role="dialog" aria-modal="true" aria-labelledby="gw-res-title" wire:key="res-{{ $result['play'] }}" x-data x-init="LmsIslands.stars({{ (int) $result['stars'] }})" x-on:keydown.escape.window="$wire.leave()">
    <section class="gw-res-card" data-celebrate="games-stars-{{ $this->learnerId() }}" data-celebrate-value="{{ $result['total_stars'] }}">
        @if ($kicker)<div class="gw-res-kicker">{{ $kicker }}</div>@endif
        <div class="gw-res-stars" role="img" aria-label="{{ trans_choice($l.'stars', $result['stars'], ['count' => $result['stars']]) }}">
            @for ($i = 1; $i <= 3; $i++)<span data-on="{{ $i <= $result['stars'] ? 'yes' : 'no' }}" style="--i: {{ $i - 1 }}">★</span>@endfor
        </div>
        <h2 id="gw-res-title">{{ __($l.'result.'.min(3, $result['stars'])) }}</h2>
        <p>{{ $result['seconds'] !== null ? __($h.'over', ['correct' => $result['correct'], 'total' => $result['total'], 's' => $secs($result['seconds'])]) : __($l.'result.score', ['correct' => $result['correct'], 'total' => $result['total']]) }}</p>
        @if ($before !== null)
            <p>{{ __($h.'best', ['s' => $secs(min($before, $result['seconds'] ?? $before))]) }}</p>
        @endif
        @if ($result['creature'])
            <p class="gw-res-hatch">@include('shared::games.creature', ['look' => $result['creature']['look'], 'size' => 40, 'motion' => true]) {{ __($l.'hatched', ['name' => $result['creature']['name']]) }}</p>
        @endif
        @if ($result['homework'])
            <p>✅ {{ __($l.'homework_done', ['title' => $result['homework']]) }}</p>
        @endif
        <p>{{ __($l.'egg', ['have' => $result['egg']['have'], 'need' => $result['egg']['need']]) }}</p>
        <div class="gw-res-acts">
            <button type="button" data-kind="go" wire:click="again">{{ __($l.'again') }}</button>
            <button type="button" wire:click="leave">{{ __($l.'map.close') }}</button>
        </div>
    </section>
</div>
