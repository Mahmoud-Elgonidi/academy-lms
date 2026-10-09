{{-- Lessons day by day: the list display, and the calendar on a phone ($phoneOnly). --}}
@php $l = 'scheduling::calendar.'; @endphp

<div @class(['cal-agenda', 'cal-phone' => $phoneOnly])>
    @forelse ($days as $d)
        <section class="cal-card cal-ag-day">
            <header>
                <strong>{{ $d['full'] }}</strong>
                @if ($d['today'])<span class="cal-badge">{{ __($l.'today') }}</span>@endif
                <small>{{ trans_choice($l.'totals.lessons', $d['count'], ['count' => $d['count']]) }}</small>
            </header>
            <div class="cal-ag-rows">
                @foreach ($d['lessons'] as $e)
                    <button type="button" class="cal-ag-row" data-status="{{ $e['status'] }}" wire:click="mountAction('lesson', { id: '{{ $e['id'] }}' })">
                        <span class="cal-ag-time">{{ $e['time'] }}</span>
                        <span class="cal-ag-main">
                            <strong>{{ $e['subject'] }}@if ($e['typeLabel']) <i>{{ $e['typeLabel'] }}</i>@endif @if ($e['unpaid'])<i data-tone="danger">{{ __($l.'unpaid') }}</i>@endif</strong>
                            <small>
                                @if ($filter->teacherId === null){{ $e['teacher'] }}@endif
                                @if ($filter->teacherId === null && $filter->studentId === null && $e['student'] !== '') ← @endif
                                @if ($filter->studentId === null){{ $e['student'] }}@endif
                            </small>
                        </span>
                        <span class="cal-pill" data-status="{{ $e['status'] }}">{{ $e['statusLabel'] }}</span>
                    </button>
                @endforeach
            </div>
        </section>
    @empty
        <div class="cal-card cal-empty">
            <x-filament::icon icon="heroicon-o-calendar" class="cal-empty-icon" />
            <strong>{{ __($l.'empty.title') }}</strong>
            <span>{{ __($l.'empty.hint') }}</span>
        </div>
    @endforelse
</div>
