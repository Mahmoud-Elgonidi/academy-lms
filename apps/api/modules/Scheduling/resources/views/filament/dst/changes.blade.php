@php
    $l = 'scheduling::dst.';
    $groups = $this->changes;
    $selectable = collect($groups)->flatMap(fn (array $g) => $g['rows'])->filter(fn (array $r) => $r['canKeepStudent'] || $r['canKeepTeacher'])->count();
@endphp
<x-filament-panels::page>
    @include('scheduling::filament.dst.styles')

    @if ($groups === [])
        <div class="dst-empty">
            <x-filament::icon icon="heroicon-o-sun" />
            <span>{{ __($l.'empty', ['days' => \Modules\Scheduling\Actions\Dst\PlanDstChanges::DAYS_AHEAD]) }}</span>
        </div>
    @else
        @if ($selectable > 1)
            <div class="dst-bar" data-on="{{ $selected === [] ? 0 : 1 }}">
                <span class="dst-count">
                    @if ($selected !== []) {{ trans_choice($l.'selected', count($selected), ['count' => count($selected)]) }} · @endif
                    <button type="button" class="dst-link" wire:click="selectAll">{{ __($l.'select_all') }}</button>
                </span>
                {{ $this->keepTeacherSelectedAction }}
                {{ $this->keepStudentSelectedAction }}
            </div>
        @endif

        @foreach ($groups as $g)
            <section>
                <h2 class="dst-h2 lms-heading">{{ $g['title'] }} <small>{{ $g['when'] }}</small></h2>
                <div class="dst-list lms-stagger">
                    @foreach ($g['rows'] as $r)
                        <article class="dst-card" data-decision="{{ $r['decision'] }}" wire:key="dst-{{ $r['id'] }}">
                            <label class="dst-pick">
                                @if ($r['canKeepStudent'] || $r['canKeepTeacher'])
                                    <input type="checkbox" value="{{ $r['id'] }}" wire:model.live="selected" aria-label="{{ $r['student'] }}">
                                @endif
                            </label>
                            <div class="dst-who">
                                <strong>{{ $r['student'] }}</strong>
                                <small>{{ __($l.'with', ['name' => $r['teacher']]) }}</small>
                                <small>{{ $r['from'] }} · {{ $r['until'] }}</small>
                            </div>
                            <div class="dst-side dst-student">
                                <small>{{ __($l.'student_side', ['zone' => $r['studentZone']]) }}</small>
                                <span class="dst-times">
                                    @if ($r['kept'])
                                        {{ $r['before'] }} <em class="dst-kept">{{ __($l.'kept') }}</em>
                                    @else
                                        <span class="dst-old">{{ $r['before'] }}</span>
                                        <x-filament::icon icon="heroicon-m-arrow-long-right" class="dst-arrow" />
                                        <span class="dst-new">{{ $r['after'] }}</span>
                                    @endif
                                </span>
                            </div>
                            <div class="dst-side dst-teacher">
                                <small>{{ __($l.'teacher_side', ['zone' => $r['teacherZone']]) }}</small>
                                <span class="dst-times">
                                    @if ($r['teacherAfter'])
                                        <span class="dst-old">{{ $r['teacherBefore'] }}</span>
                                        <x-filament::icon icon="heroicon-m-arrow-long-right" class="dst-arrow" />
                                        <span class="dst-new">{{ $r['teacherAfter'] }}</span>
                                    @else
                                        {{ $r['teacherBefore'] }}
                                    @endif
                                </span>
                            </div>
                            <div class="dst-end">
                                <span class="dst-pill" data-d="{{ $r['decision'] }}">{{ $r['decisionLabel'] }}</span>
                                <small>{{ $r['lessons'] }} · {{ $r['told'] ? __($l.'told') : $r['tellOn'] }}</small>
                                @if (! $r['kept'] && $r['problems'] !== null)
                                    <small @class(['dst-ok' => $r['problems'] === 0, 'dst-warn' => $r['problems'] > 0])>
                                        {{ $r['problems'] === 0 ? __($l.'possible') : __($l.'impossible', ['count' => $r['problems']]) }}
                                    </small>
                                @endif
                                <div class="dst-actions">
                                    @if ($r['canKeepTeacher']) {{ ($this->keepTeacherAction)(['notice' => $r['id']]) }} @endif
                                    @if ($r['canKeepStudent']) {{ ($this->keepStudentAction)(['notice' => $r['id']]) }} @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endforeach
    @endif
</x-filament-panels::page>
