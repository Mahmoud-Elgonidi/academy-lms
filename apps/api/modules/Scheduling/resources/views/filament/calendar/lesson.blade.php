{{-- A lesson's details, in the calendar's side panel. --}}
@php $l = 'scheduling::calendar.'; @endphp

@if ($lesson === null)
    <p class="cal-muted">{{ __($l.'details.missing') }}</p>
@else
    <div class="cal-dt">
        <div class="cal-dt-tags">
            <span class="cal-pill" data-status="{{ $lesson['status'] }}">{{ $lesson['statusLabel'] }}</span>
            @if ($lesson['typeLabel'])<span class="cal-tag">{{ $lesson['typeLabel'] }}</span>@endif
            @if ($lesson['unpaid'])<span class="cal-tag" data-tone="danger">{{ __($l.'unpaid') }}</span>@endif
        </div>
        <dl>
            <div>
                <dt>{{ __($l.'details.when') }}</dt>
                <dd>{{ $lesson['when'] }}<br><b class="cal-ltr">{{ $lesson['time'] }}</b> <small>· {{ __($l.'details.minutes', ['count' => $lesson['minutes']]) }}</small></dd>
            </div>
            <div>
                <dt>{{ __($l.'details.teacher') }}</dt>
                <dd>
                    {{ $lesson['teacher'] }}
                    @if ($lesson['originalTeacher'])<small class="cal-block">{{ __($l.'details.instead_of', ['name' => $lesson['originalTeacher']]) }}</small>@endif
                    @if ($lesson['teacherTime'])<small class="cal-block">{{ $lesson['teacherTime'] }}</small>@endif
                </dd>
            </div>
            @if ($lesson['student'] !== '')
                <div>
                    <dt>{{ __($l.'details.student') }}</dt>
                    <dd>{{ $lesson['student'] }} @if ($lesson['studentTime'])<small class="cal-block">{{ $lesson['studentTime'] }}</small>@endif</dd>
                </div>
            @endif
            <div><dt>{{ __($l.'details.subject') }}</dt><dd>{{ $lesson['subject'] }}</dd></div>
            <div><dt>{{ __($l.'details.funding') }}</dt><dd>{{ $lesson['funding'] }}</dd></div>
            @if ($lesson['reason'])
                <div><dt>{{ __($l.'details.reason') }}</dt><dd>{{ $lesson['reason'] }}</dd></div>
            @endif
            @if ($lesson['note'])
                <div><dt>{{ __($l.'details.note') }}</dt><dd>{{ $lesson['note'] }}</dd></div>
            @endif
            @if ($lesson['review'])
                <div><dt>{{ __($l.'details.review') }}</dt><dd>{{ $lesson['review'] }}</dd></div>
            @endif
            @if ($lesson['dispute'])
                <div><dt>{{ __($l.'details.dispute') }}</dt><dd>{{ $lesson['dispute']['label'] }}</dd></div>
            @endif
        </dl>
        <div class="cal-dt-links">
            @if ($lesson['boardUrl'])
                <x-filament::button tag="a" :href="$lesson['boardUrl']" icon="heroicon-o-bolt" size="sm">{{ __($l.'details.board') }}</x-filament::button>
            @endif
            @if ($lesson['dispute'])
                <x-filament::button tag="a" :href="$lesson['dispute']['url']" icon="heroicon-o-scale" size="sm" color="warning">{{ __($l.'details.open_dispute') }}</x-filament::button>
            @endif
            @if ($lesson['accountUrl'])
                <x-filament::button tag="a" :href="$lesson['accountUrl']" icon="heroicon-o-user" size="sm" color="gray">{{ __($l.'details.account') }}</x-filament::button>
            @endif
            <x-filament::button tag="a" :href="$lesson['teacherUrl']" icon="heroicon-o-calendar-days" size="sm" color="gray">{{ __($l.'details.teacher_calendar') }}</x-filament::button>
            @if ($lesson['studentUrl'])
                <x-filament::button tag="a" :href="$lesson['studentUrl']" icon="heroicon-o-calendar-days" size="sm" color="gray">{{ __($l.'details.student_calendar') }}</x-filament::button>
            @endif
        </div>
    </div>
@endif
