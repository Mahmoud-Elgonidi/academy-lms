{{-- A round, full screen over the world: the game's page in a sandboxed frame; what it reports goes back through finish(). --}}
@php $hex = fn ($c, $d) => is_string($c) && preg_match('/^#[0-9a-f]{6}$/i', $c) ? $c : $d; @endphp
<div class="gw-play gw-kids" data-look="{{ $look }}" style="--gw-sky: {{ $hex($round['sky'] ?? null, '#8ED8FF') }}" role="dialog" aria-modal="true" aria-labelledby="gw-play-title" wire:key="round-{{ $round['play'] }}">
    <header class="gw-play-bar">
        <button type="button" class="gw-round-btn gw-3d" wire:click="leave" aria-label="{{ __('games::games.map.close') }}">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#13294B" stroke-width="3.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
        <div>
            <strong id="gw-play-title">{{ $round['title'] }}</strong>
            <small>{{ $round['focus'] ? __('games::games.practising', ['what' => $round['focus']]) : $round['how'] }}</small>
        </div>
    </header>
    <div class="gw-frame" wire:ignore data-game-player data-round-player
         data-src="{{ $round['src'] }}" data-items="{{ json_encode($round['items'], JSON_UNESCAPED_UNICODE) }}"
         data-stage="{{ $round['stage'] }}" data-locale="{{ $round['locale'] }}" data-dir="{{ $round['dir'] }}" data-title="{{ $round['title'] }}"></div>
</div>
