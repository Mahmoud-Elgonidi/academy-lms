@php
    /* A station's sheet (middle school, the calm view, a guardian's): the lesson, its state, its missions, the race against yourself and its games. */
    $m = 'learning::map.';
    $self = $this->isSelf();
    $missions = $this->missionsOn($st['id']);
    $tone = ['done' => 'done', 'review' => 'review', 'here' => 'here', 'todo' => 'open'][$st['state']];
@endphp
<template x-if="station === @js($st['id'])">
    <div>
        <div class="jm-sheet-bg" x-on:click="station = null"></div>
        <section class="jm-sheet" role="dialog" aria-modal="true" aria-labelledby="jst-{{ $st['id'] }}" x-on:keydown.escape.window="station = null" x-init="$nextTick(() => $el.querySelector('.jm-x')?.focus())">
            <div class="jm-row" style="align-items: flex-start; flex-wrap: nowrap">
                <div style="display: flex; flex-direction: column; gap: .3rem">
                    <h2 id="jst-{{ $st['id'] }}">{{ $st['title'] }}</h2>
                    @if ($st['unit'])<small class="jm-muted">{{ $st['unit'] }}</small>@endif
                    <span><span class="jm-pill" data-tone="{{ $st['due_review'] ? 'review' : $tone }}">{{ $st['due_review'] ? __($m.'station.due_review') : __($m.'station.states.'.$st['state']) }}</span></span>
                </div>
                <button type="button" class="jm-x" x-on:click="station = null" aria-label="{{ __($m.'station.close') }}">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
            </div>

            @foreach ($missions as $mission)
                <div class="jm-mission" wire:key="jsm-{{ $mission['key'] }}">
                    <span aria-hidden="true">{{ $mission['kind'] === 'lesson' ? '📅' : ($mission['kind'] === 'homework' ? '📝' : '↺') }}</span>
                    <div>
                        <strong>{{ $mission['title'] }}</strong>
                        @if ($mission['kind'] === 'lesson')
                            <small>{{ $this->at($mission['starts_at']) }}</small>
                        @elseif ($mission['kind'] === 'homework')
                            <small>{{ __($m.($mission['state'] === 'late' ? 'mission.late' : 'mission.due'), ['time' => $this->at($mission['due_at'])]) }}</small>
                        @endif
                    </div>
                    <span class="jm-acts">
                        @if ($mission['kind'] === 'lesson' && ($join = $this->joinUrl($mission)))
                            <a class="jm-btn" href="{{ $join }}">{{ __($m.'mission.join') }}</a>
                        @elseif ($mission['kind'] === 'homework' && ($game = $this->gameFor($mission)))
                            <button type="button" class="jm-btn" data-kind="success" x-on:click="station = null; $dispatch('journey-play', { game: @js($game), homework: @js($mission['homework_id']) })">▶ {{ $self ? __($m.'mission.play') : __($m.'station.together') }}</button>
                        @elseif ($mission['kind'] === 'homework' && $mission['accepts'] !== 'practice')
                            <a class="jm-btn" data-kind="ghost" href="{{ \Modules\Learning\Filament\Family\FamilyHomework::getUrl() }}" wire:navigate>{{ __($m.'mission.hand_in') }}</a>
                        @endif
                    </span>
                </div>
            @endforeach

            @if (! $st['playable'])
                <p class="jm-muted" style="margin: 0">🔒 {{ __($m.'station.locked') }}</p>
            @elseif ($games)
                @if ($race ?? false)
                    <div class="jm-card" style="padding: .7rem .8rem; background: var(--jm-soft); border: 0">
                        <strong>🏁 {{ __($m.'station.race') }}</strong>
                        <div class="jm-race">
                            @if ($st['race'])
                                <span class="jm-pill">{{ trans_choice($m.'station.rounds', $st['race']['rounds'], ['count' => $st['race']['rounds']]) }}</span>
                                @if ($st['race']['best_seconds'] !== null)<span class="jm-pill" data-tone="here">⏱ {{ __($m.'station.best', ['s' => $st['race']['best_seconds']]) }}</span>@endif
                                <span class="jm-pill" data-tone="star">{{ __($m.'station.best_stars', ['n' => $st['race']['best_stars']]) }}</span>
                            @else
                                <span class="jm-muted">{{ __($m.'station.first') }}</span>
                            @endif
                        </div>
                    </div>
                @elseif ($st['race'])
                    <small class="jm-muted">{{ trans_choice($m.'station.rounds', $st['race']['rounds'], ['count' => $st['race']['rounds']]) }} · {{ __($m.'station.best_stars', ['n' => $st['race']['best_stars']]) }}</small>
                @endif
                <h3 class="jm-h" style="font-size: .95rem">{{ $self ? __($m.'station.games') : __($m.'station.together') }}</h3>
                @if ($st['games'] === [])
                    <p class="jm-muted" style="margin: 0">{{ __($m.'station.no_games') }}</p>
                @else
                    <div class="jm-games">
                        @foreach ($st['games'] as $g)
                            <button type="button" class="jm-game" x-on:click="station = null; $dispatch('journey-play', { game: @js($g['key']), station: @js($st['id']) })" wire:key="jsg-{{ $st['id'] }}-{{ $g['id'] }}">
                                <strong>▶ {{ $g['title'] }}</strong>
                                <small>{{ $g['summary'] }}</small>
                                <small>{{ trans_choice($m.'station.items', $g['items'], ['count' => $g['items']]) }}@if ($g['chosen']) · {{ __($m.'station.chosen') }}@endif</small>
                            </button>
                        @endforeach
                    </div>
                @endif
            @endif
        </section>
    </div>
</template>
