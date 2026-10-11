@php
    /*
     * The missions as a list (middle school, the calm view, a guardian's): the next lesson with its way in, the
     * teacher's homework (played when it asks for practice, else handed in on the homework page), reviews due.
     */
    $m = 'learning::map.';
    $icon = ['lesson' => '📅', 'homework' => '📝', 'review' => '↺'];
    $self = $this->isSelf();
@endphp
<section aria-labelledby="jm-missions" style="display: flex; flex-direction: column; gap: .5rem">
    <h2 id="jm-missions" class="jm-h">{!! $self ? e(__($m.'mission.title')) : __($m.'mission.title_of', ['name' => '<bdi>'.e($this->firstName()).'</bdi>']) !!}</h2>
    @if ($j['missions'] === [])
        <div class="jm-empty">{{ __($m.'mission.none') }}</div>
    @else
        <ul class="jm-missions lms-stagger">
            @foreach ($j['missions'] as $mission)
                <li class="jm-mission" data-kind="{{ $mission['kind'] }}" data-state="{{ $mission['state'] }}" wire:key="mm-{{ $mission['key'] }}">
                    <span aria-hidden="true">{{ $icon[$mission['kind']] }}</span>
                    <div>
                        <span class="jm-row" style="justify-content: flex-start; gap: .4rem">
                            <span class="jm-pill" data-tone="{{ $mission['state'] === 'late' ? 'late' : ($mission['state'] === 'done' ? 'done' : 'open') }}">{{ __($m.'missions.'.$mission['kind']) }}@if ($mission['kind'] === 'homework') · {{ __($m.'mission.states.'.$mission['state']) }}@endif</span>
                        </span>
                        <strong>{{ $mission['title'] ?? '' }}</strong>
                        @if ($mission['kind'] === 'lesson')
                            <small>{{ $this->at($mission['starts_at']) }}@unless ($plain) · {{ __($m.'mission.up_to', ['n' => $mission['stars']]) }}@endunless</small>
                        @elseif ($mission['kind'] === 'homework')
                            <small>{{ $mission['state'] === 'done' ? __($m.'mission.done') : __($m.($mission['state'] === 'late' ? 'mission.late' : 'mission.due'), ['time' => $this->at($mission['due_at'])]) }}@unless ($plain) · ⭐ {{ $mission['stars'] }}@endunless</small>
                            @if ($mission['result'])
                                <small>🎮 {{ __('learning::homework.practice.result', $mission['result']) }}</small>
                            @endif
                        @else
                            <small>{{ __($m.'mission.review_hint') }}</small>
                        @endif
                    </div>
                    <span class="jm-acts">
                        @if ($mission['kind'] === 'lesson')
                            @if ($join = $this->joinUrl($mission))
                                <a class="jm-btn" href="{{ $join }}">{{ __($m.'mission.join') }}</a>
                            @else
                                <small class="jm-muted">{{ __($m.'mission.opens', ['time' => $this->at($mission['join']['opens_at'])]) }}</small>
                            @endif
                        @elseif ($mission['kind'] === 'homework' && $mission['state'] !== 'done')
                            @if ($game = $this->gameFor($mission))
                                <button type="button" class="jm-btn" data-kind="success" x-on:click="$dispatch('journey-play', { game: @js($game), homework: @js($mission['homework_id']) })">▶ {{ $self ? __($m.'mission.play') : __($m.'station.together') }}</button>
                            @endif
                            @if ($mission['accepts'] !== 'practice')
                                <a class="jm-btn" data-kind="ghost" href="{{ \Modules\Learning\Filament\Family\FamilyHomework::getUrl() }}" wire:navigate>{{ __($m.'mission.hand_in') }}</a>
                            @endif
                        @elseif ($mission['kind'] === 'review' && $mission['station_id'] && $mission['enrollment_id'] === ($course['enrollment_id'] ?? null)
                            && ($self || ($games && ($this->stations[$mission['station_id']]['games'] ?? []) !== [])))
                            <button type="button" class="jm-btn" data-kind="ghost" x-on:click="station = @js($mission['station_id'])">↺ {{ $self ? __($m.'mission.review') : __($m.'station.together') }}</button>
                        @endif
                    </span>
                </li>
            @endforeach
        </ul>
    @endif
</section>
