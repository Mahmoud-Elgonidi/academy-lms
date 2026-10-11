@php
    /* A mission card under the children's map: the next lesson (its boat), the teacher's homework (its flag), a review due. */
    $m = 'learning::map.';
    $icon = ['lesson' => '⛵', 'homework' => '🚩', 'review' => '↺'][$mission['kind']];
@endphp
<article class="jk-mission" data-kind="{{ $mission['kind'] }}" data-state="{{ $mission['state'] }}" style="--i: {{ $i }}" wire:key="km-{{ $mission['key'] }}">
    <span class="jk-kind">{{ $icon }} {{ __($m.'missions.'.$mission['kind']) }}</span>
    <strong>{{ $mission['title'] ?? '' }}</strong>
    @if ($mission['kind'] === 'lesson')
        @php $join = $this->joinUrl($mission); @endphp
        <small>{{ $this->at($mission['starts_at']) }}@unless ($plain) · {{ __($m.'mission.up_to', ['n' => $mission['stars']]) }}@endunless</small>
        @if ($join)
            <a class="jk-go" href="{{ $join }}">{{ __($m.'mission.join') }}</a>
        @else
            <small>{{ __($m.'mission.opens', ['time' => $this->at($mission['join']['opens_at'])]) }}</small>
        @endif
    @elseif ($mission['kind'] === 'homework')
        @php $game = $mission['state'] === 'done' ? null : $this->gameFor($mission); @endphp
        <small>{{ $mission['state'] === 'done' ? __($m.'mission.done') : __($m.($mission['state'] === 'late' ? 'mission.late' : 'mission.due'), ['time' => $this->at($mission['due_at'])]) }}@unless ($plain) · ⭐ {{ $mission['stars'] }}@endunless</small>
        @if ($mission['result'])
            <small>🎮 {{ __('learning::homework.practice.result', $mission['result']) }}</small>
        @endif
        @if ($game)
            <button type="button" class="jk-go" x-on:click="play({ game: @js($game), homework: @js($mission['homework_id']) })">▶ {{ __($m.'mission.play') }}</button>
        @elseif ($mission['state'] !== 'done' && $mission['accepts'] !== 'practice')
            <a class="jk-go" data-kind="plain" href="{{ \Modules\Learning\Filament\Family\FamilyHomework::getUrl() }}" wire:navigate>{{ __($m.'mission.hand_in') }}</a>
        @endif
    @else
        <small>{{ __($m.'mission.review_hint') }}</small>
        @if ($mission['island_id'])
            <button type="button" class="jk-go" data-kind="plain" x-on:click="visit(@js($mission['island_id']), @js($mission['station_id']))">↺ {{ __($m.'mission.review') }}</button>
        @endif
    @endif
</article>
