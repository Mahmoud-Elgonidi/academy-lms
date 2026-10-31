@php
    $l = 'scheduling::family.';
    $lessons = $this->lessons;
@endphp
<x-filament-panels::page>
    @include('scheduling::filament.family.styles')
    @include('scheduling::filament.time-off.styles')

    @foreach ($this->courseNotices as $notice)
        <div class="to-banner" style="margin-bottom: .5rem"><x-filament::icon icon="heroicon-o-pause-circle" />{{ $notice }}</div>
    @endforeach
    @foreach ($this->holidays as $h)
        <div class="to-banner" style="margin-bottom: .5rem"><x-filament::icon icon="heroicon-o-sparkles" />{{ __('scheduling::timeoff.family.holiday', ['name' => $h['name'], 'period' => $h['period']]) }}</div>
    @endforeach
    @foreach ($this->clockChanges as $change)
        <div class="to-banner" style="margin-bottom: .5rem"><x-filament::icon icon="heroicon-o-clock" />{{ $change }}</div>
    @endforeach

    @if ($this->groups !== [])
        <h2 class="fl-h2">{{ __('scheduling::group_outcomes.course.heading') }}</h2>
        <div class="fl-courses lms-stagger">
            @foreach ($this->groups as $g)
                <article class="fl-course" data-group-course wire:key="flg-{{ $g['id'] }}">
                    <span class="fl-group"><x-filament::icon icon="heroicon-m-user-group" class="fl-group-ico" />{{ __('scheduling::group_outcomes.format.group') }}</span>
                    <strong>{{ $g['title'] }} · {{ $g['seat'] }}</strong>
                    <small>{{ $g['teacher'] }}</small>
                    <small>{{ $g['times'] }}</small>
                    @if ($g['waits'])<small class="fl-warn" style="color: var(--warning-700)">{{ $g['waits'] }}</small>@endif
                </article>
            @endforeach
        </div>
    @endif

    <h2 class="fl-h2">{{ __($l.'upcoming') }}</h2>
    <div class="fl-list lms-stagger">
        @forelse ($lessons['upcoming'] as $lesson)
            @include('scheduling::filament.family.lesson-card', ['lesson' => $lesson, 'cancel' => $this->cancelAction ?? null, 'refuse' => $this->refuseAction ?? null])
        @empty
            <div class="fl-empty">{{ __($l.'none_upcoming') }}</div>
        @endforelse
    </div>

    <h2 class="fl-h2">{{ __($l.'recent') }}</h2>
    <div class="fl-list lms-stagger">
        @forelse ($lessons['recent'] as $lesson)
            @include('scheduling::filament.family.lesson-card', ['lesson' => $lesson, 'after' => true, 'dispute' => $this->disputeAction])
        @empty
            <div class="fl-empty">{{ __($l.'none_recent') }}</div>
        @endforelse
    </div>
</x-filament-panels::page>
