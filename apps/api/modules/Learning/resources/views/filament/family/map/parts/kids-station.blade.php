@php
    /* A station's sheet over its island (children): the lesson, how far they are with it, its missions, its games, the race. */
    $m = 'learning::map.';
    $state = $st['due_review'] && $st['state'] === 'done' ? 'review' : $st['state'];
    $missions = $this->missionsOn($st['id']);
    $hw = 'homework';
@endphp
<section class="jk-ssheet" data-station-sheet="{{ $st['id'] }}" x-show="station === @js($st['id'])" x-cloak role="dialog" aria-labelledby="st-{{ $st['id'] }}" wire:key="ss-{{ $st['id'] }}">
    <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 10px">
        <div style="min-width: 0; display: flex; flex-direction: column; gap: 4px">
            <h2 id="st-{{ $st['id'] }}">{{ $st['title'] }}</h2>
            @if ($st['unit'])<div class="gw-sheet-sub">{{ $st['unit'] }}</div>@endif
            <span class="jk-state">{{ $st['due_review'] ? __($m.'station.due_review') : __($m.'station.states.'.$st['state']) }}</span>
        </div>
        <button type="button" class="gw-sheet-close gw-3d" x-on:click="closeStation()" aria-label="{{ __($m.'station.close') }}">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#13294B" stroke-width="3.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
    </div>

    @foreach ($missions as $mission)
        @if ($mission['kind'] === 'homework')
            @php $game = $this->gameFor($mission); @endphp
            <div class="jk-line" wire:key="sm-{{ $mission['key'] }}">
                <span>🚩 {{ $mission['title'] }}<br><small style="font-weight: 700; color: #3B4F72">{{ __($m.($mission['state'] === 'late' ? 'mission.late' : 'mission.due'), ['time' => $this->at($mission['due_at'])]) }}</small></span>
                @if ($game)
                    <button type="button" class="jk-go" x-on:click="play({ game: @js($game), homework: @js($mission['homework_id']) })">{{ __($m.'mission.play') }}</button>
                @elseif ($mission['accepts'] !== 'practice')
                    <a class="jk-go" href="{{ \Modules\Learning\Filament\Family\FamilyHomework::getUrl() }}" wire:navigate>{{ __($m.'mission.hand_in') }}</a>
                @endif
            </div>
        @elseif ($mission['kind'] === 'lesson')
            @php $join = $this->joinUrl($mission); @endphp
            <div class="jk-line" style="background: #D0EBFF" wire:key="sm-{{ $mission['key'] }}">
                <span>⛵ {{ $mission['title'] }}<br><small style="font-weight: 700; color: #3B4F72">{{ $this->at($mission['starts_at']) }}</small></span>
                @if ($join)<a class="jk-go" href="{{ $join }}">{{ __($m.'mission.join') }}</a>@endif
            </div>
        @endif
    @endforeach

    @if (! $st['playable'])
        <div class="gw-sheet-note">🔒 {{ __($m.'station.locked') }}</div>
    @elseif ($games)
        @if ($st['race'])
            <div class="jk-race">🏁 {{ trans_choice($m.'station.rounds', $st['race']['rounds'], ['count' => $st['race']['rounds']]) }}@if ($st['race']['best_seconds'] !== null) · {{ __($m.'station.best', ['s' => $st['race']['best_seconds']]) }}@endif · {{ __($m.'station.best_stars', ['n' => $st['race']['best_stars']]) }}</div>
        @endif
        @if ($st['games'] === [])
            <div class="gw-sheet-sub">{{ __($m.'station.no_games') }}</div>
        @else
            <nav aria-label="{{ __($m.'station.games') }}" style="display: flex; flex-direction: column; gap: 10px">
                @foreach ($st['games'] as $i => $g)
                    <button type="button" class="gw-gamecard gw-card3d gw-rise" style="--i: {{ $i }}; background: #FFFFFF; box-shadow: 0 5px 0 #13294B" wire:key="sg-{{ $st['id'] }}-{{ $g['id'] }}"
                            x-on:click="play({ game: @js($g['key']), station: @js($st['id']) })">
                        @include('learning::filament.family.map.parts.game-tile', ['g' => $g])
                        <span style="flex: 1; min-width: 0">
                            <span style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap"><strong>{{ $g['title'] }}</strong>@if ($g['chosen'])<span class="gw-badge" data-kind="homework">{{ __($m.'station.chosen') }}</span>@endif</span>
                            <span class="gw-what">{{ $g['summary'] }} · {{ trans_choice($m.'station.items', $g['items'], ['count' => $g['items']]) }}</span>
                        </span>
                        <span class="gw-playdot" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24"><path d="M7 4v16l13-8z" fill="#13294B"/></svg></span>
                    </button>
                @endforeach
            </nav>
        @endif
    @endif
</section>
