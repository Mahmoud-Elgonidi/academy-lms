@php
    /*
     * A guardian's view of their child's journey (the classic look): each course's level and how far, the last
     * lesson's punctuality and stars, the missions and how they stand, the stars and the week's chest (only the
     * child opens it), cheering them on, the games' limits and their week (the games' own part, in the guardian
     * hook) and playing a round together on one of their lessons. Never pay or KPIs.
     */
    $m = 'learning::map.';
    $name = $this->firstName();
    $stars = (int) $j['stars']['total'];
    $rank = $j['stars']['rank'];
    $left = $this->cheersLeft();
    $emoji = ['clap' => '👏', 'star' => '⭐', 'heart' => '💛', 'fire' => '🔥'];
    $playable = collect($course['islands'] ?? [])->flatMap(fn ($i) => $i['stations'])
        ->filter(fn ($s) => $s['playable'] && $s['games'] !== [] && ($s['state'] === 'here' || $s['state'] === 'review' || $s['due_review']))->values();
    $last = $j['last_lesson'];
@endphp
<div class="jm-cols" x-data="{ station: null }" wire:key="jg-{{ $this->learner()?->id }}">
    <div class="jm-side">
        <div class="jm-row">
            <h2 class="jm-h" style="font-size: 1.2rem; margin: 0">{!! __($m.'title.guardian', ['name' => '<bdi>'.e($name).'</bdi>']) !!}</h2>
            @unless ($plain)
                <span class="jm-pill" data-tone="star">⭐ <span data-count="{{ $stars }}">{{ $stars }}</span> · {{ __('learning::journey.ranks.'.$rank['key']) }}</span>
            @endunless
        </div>

        @if (count($j['courses']) > 1)
            <div class="jm-chips" role="group" aria-label="{{ __($m.'courses') }}">
                @foreach ($j['courses'] as $c)
                    <button type="button" aria-pressed="{{ $c['enrollment_id'] === ($course['enrollment_id'] ?? null) ? 'true' : 'false' }}" wire:click="chooseCourse('{{ $c['enrollment_id'] }}')" wire:key="gc-{{ $c['enrollment_id'] }}">{{ $c['subject'] }}</button>
                @endforeach
            </div>
        @endif

        @if ($course)
            @php $here = collect($course['islands'])->firstWhere('state', 'here'); @endphp
            <section class="jm-card" aria-labelledby="jg-level">
                <div class="jm-row" style="align-items: flex-start">
                    <div>
                        <small class="jm-muted">{{ __($m.'guardian.level') }} · {{ $course['subject'] }}@if ($course['teacher']) · {{ $course['teacher'] }}@endif</small>
                        <h3 id="jg-level">
                            @if (! $course['has_curriculum']){{ __('learning::journey.no_program') }}
                            @elseif ($course['finished']){{ __($m.'finished') }}
                            @elseif ($here){{ __($m.'calm.level', ['n' => $here['index'], 'of' => count($course['islands'])]) }} · {{ $here['title'] }}
                            @endif
                        </h3>
                    </div>
                    @if ($course['percent'] !== null)
                        <span class="jm-big" style="color: var(--primary-700)">{{ $course['percent'] }}%</span>
                    @endif
                </div>
                @if ($course['percent'] !== null)
                    <span class="jm-bar"><i class="lms-bar-fill" style="width: {{ $course['percent'] }}%"></i></span>
                    <div class="jm-row">
                        @if ($course['current'])<small>{{ __($m.'calm.here', ['lesson' => $course['current']['title']]) }}</small>@endif
                        @unless ($course['finished'])<small class="jm-muted">{{ $course['finish_on'] ? __($m.'calm.finish_on', ['date' => $this->day($course['finish_on'])]) : __($m.'calm.finish_unknown') }}</small>@endunless
                    </div>
                @endif
            </section>
        @endif

        @if ($last)
            <section class="jm-lastl" aria-label="{{ __($m.'guardian.punctual') }}">
                <span style="font-size: 1.4rem" aria-hidden="true">🕒</span>
                <div>
                    <small style="display: block; font-weight: 700; opacity: .8">{{ __($m.'guardian.punctual') }}</small>
                    @unless ($plain)<b>{{ str_repeat('⭐', $last['stars']) }}</b>@endunless
                    <span style="font-weight: 600">
                        @if ($last['status'] === 'student_absent'){{ __($m.'lesson.missed') }}@elseif ($last['late_min'] === null){{ __($m.'lesson.marked') }}@else{{ $last['on_time'] ? __($m.'lesson.on_time') : trans_choice($m.'lesson.late', $last['late_min'], ['n' => $last['late_min']]) }}@if ($last['stayed']) · {{ __($m.'lesson.stayed') }}@endif @endif
                    </span>
                </div>
            </section>
        @endif

        @include('learning::filament.family.map.parts.missions')

        @if ($games && $playable->isNotEmpty())
            <section class="jm-card" aria-labelledby="jg-together">
                <h3 id="jg-together">🎮 {{ __($m.'guardian.together') }}</h3>
                <small class="jm-muted">{{ __($m.'guardian.together_hint') }}</small>
                <ul class="jm-missions">
                    @foreach ($playable as $st)
                        <li class="jm-mission" wire:key="gt-{{ $st['id'] }}">
                            <span aria-hidden="true">{{ $st['state'] === 'here' ? '📍' : '↺' }}</span>
                            <div><strong>{{ $st['title'] }}</strong><small>{{ $st['games'][0]['title'] }}</small></div>
                            <span class="jm-acts">
                                <button type="button" class="jm-btn" data-kind="success" x-on:click="$dispatch('journey-play', { game: @js($st['games'][0]['key']), station: @js($st['id']) })">▶ {{ __($m.'station.together') }}</button>
                                @if (count($st['games']) > 1)
                                    <button type="button" class="jm-btn" data-kind="ghost" x-on:click="station = @js($st['id'])">+{{ count($st['games']) - 1 }}</button>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>

    <div class="jm-side">
        <section class="jm-card" aria-labelledby="jg-cheer">
            <h3 id="jg-cheer">{{ __($m.'cheers.title', ['name' => $name]) }}</h3>
            <div class="jm-cheers" role="group" aria-label="{{ __($m.'cheers.title', ['name' => $name]) }}">
                @foreach ($emoji as $kind => $e)
                    <button type="button" wire:click="cheer('{{ $kind }}')" @disabled($left === 0) aria-label="{{ __('learning::challenges.cheers.'.$kind) }}" title="{{ __('learning::challenges.cheers.'.$kind) }}">{{ $e }}</button>
                @endforeach
            </div>
            <small class="jm-muted">{{ trans_choice($m.'cheers.left', $left, ['count' => $left]) }}</small>
        </section>

        @include('learning::filament.family.map.parts.chest')

        @if ($games)
            {{ \Filament\Support\Facades\FilamentView::renderHook(\Modules\Learning\Filament\Family\JourneyBoard::GUARDIAN_HOOK, data: ['learner' => $this->learner()?->id]) }}
        @endif

        <div class="jm-links">
            <a href="{{ \Modules\Learning\Filament\Family\FamilyProgress::getUrl() }}" wire:navigate>{{ __($m.'calm.roadmap') }}</a>
            <a href="{{ \Modules\Learning\Filament\Family\FamilyReports::getUrl() }}" wire:navigate>{{ __($m.'calm.reports') }}</a>
        </div>
    </div>

    @foreach ($playable as $st)
        @include('learning::filament.family.map.parts.station', ['st' => $st, 'race' => false])
    @endforeach
    @foreach ($j['missions'] as $mission)
        @if ($mission['kind'] === 'review' && $mission['station_id'] && $mission['enrollment_id'] === ($course['enrollment_id'] ?? null) && ! $playable->contains('id', $mission['station_id']) && isset($this->stations[$mission['station_id']]))
            @include('learning::filament.family.map.parts.station', ['st' => $this->stations[$mission['station_id']], 'race' => false])
        @endif
    @endforeach
</div>
