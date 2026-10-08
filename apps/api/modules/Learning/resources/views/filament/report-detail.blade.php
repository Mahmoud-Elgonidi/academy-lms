{{-- One report, read-only: $report, $items (ReportFormQuery::items), $homework, $internal (show internal notes and academy-only answers). --}}
@php $answers = \Modules\Learning\Templates\TemplateAnswers::ofReport($report, $internal); @endphp
<div class="rd" style="display:flex; flex-direction:column; gap:.9rem; font-size:.875rem">
    <style>
        .rd h4 { margin: 0 0 .3rem; font-size: .8rem; color: var(--gray-500); font-weight: 600; }
        .rd-items { display: flex; flex-direction: column; gap: .35rem; }
        .rd-item { display: flex; gap: .6rem; align-items: baseline; padding: .45rem .6rem; border: 1px solid var(--gray-200); border-radius: var(--radius-lg); }
        .rd-item small { color: var(--gray-500); }
        .rd-out { white-space: nowrap; font-size: .72rem; font-weight: 700; padding: .05rem .5rem; border-radius: 999px; background: var(--gray-100); }
        .rd-out[data-o="mastered"] { background: var(--success-50); color: var(--success-700); }
        .rd-out[data-o="needs_review"] { background: var(--warning-50); color: var(--warning-800); }
        .rd-scores { display: flex; gap: 1.5rem; }
        .rd-scores strong { font-size: 1.1rem; }
        .rd p { margin: 0; white-space: pre-line; }
        .rd-answers { display: grid; grid-template-columns: repeat(auto-fill, minmax(12rem, 1fr)); gap: .4rem; }
        .rd-answer { display: flex; flex-direction: column; gap: .1rem; padding: .45rem .6rem; border: 1px solid var(--gray-200); border-radius: var(--radius-lg); }
        .rd-answer small { color: var(--gray-500); }
        .rd-answer[data-internal] { border-style: dashed; }
        .rd-stars { color: var(--warning-500); letter-spacing: .05em; }
        .rd-internal { padding: .5rem .7rem; border-radius: var(--radius-lg); background: var(--gray-50); border: 1px dashed var(--gray-300); }
        .dark .rd-item, .dark .rd-answer { border-color: var(--gray-700); }
        .dark .rd-internal { background: var(--gray-800); }
    </style>

    @if ($report->kind === 'absence')
        <div><h4>{{ __('learning::reports.detail.absence') }}</h4><p>{{ $report->absence_note }}</p></div>
    @else
        <div>
            <h4>{{ __('learning::reports.detail.covered') }}</h4>
            <div class="rd-items">
                @forelse ($items as $i)
                    <div class="rd-item">
                        <span class="rd-out" data-o="{{ $i['outcome'] }}">{{ __('learning::reports.outcomes.'.$i['outcome']) }}</span>
                        <span style="flex:1">{{ $i['title'] }} <small>· {{ $i['path'] }}</small>@if ($i['note'])<br><small>{{ $i['note'] }}</small>@endif</span>
                        @if ($i['score']) <strong>{{ __('learning::reports.detail.score', ['n' => $i['score']]) }}</strong> @endif
                    </div>
                @empty
                    <small style="color:var(--gray-500)">{{ __('learning::reports.detail.no_items') }}</small>
                @endforelse
            </div>
        </div>
        <div class="rd-scores">
            <div><h4>{{ __('learning::reports.detail.engagement') }}</h4><strong>{{ $report->engagement }}/5</strong></div>
            <div><h4>{{ __('learning::reports.detail.overall') }}</h4><strong>{{ $report->overall }}/5</strong></div>
        </div>
        @if ($answers !== [])
            <div>
                <h4>{{ __('learning::templates.form.section') }}</h4>
                <div class="rd-answers">
                    @foreach ($answers as $a)
                        <div class="rd-answer" @unless ($a['family']) data-internal @endunless>
                            <small>{{ $a['label'] }}@unless ($a['family']) · {{ __('learning::templates.internal') }}@endunless</small>
                            @if ($a['type'] === 'rating')
                                <strong><span class="rd-stars" aria-hidden="true">{{ str_repeat('★', $a['value']) }}{{ str_repeat('☆', 5 - $a['value']) }}</span> {{ $a['text'] }}</strong>
                            @else
                                <strong>{{ $a['text'] }}</strong>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
        @if ($report->notes_for_student)
            <div><h4>{{ __('learning::reports.detail.for_family') }}</h4><p>{{ $report->notes_for_student }}</p></div>
        @endif
    @endif

    @if ($internal && $report->notes_internal)
        <div class="rd-internal"><h4>{{ __('learning::reports.detail.internal') }}</h4><p>{{ $report->notes_internal }}</p></div>
    @endif
    @if ($homework)
        <div><h4>{{ __('learning::reports.detail.homework') }}</h4><p><strong>{{ $homework->title }}</strong> · {{ __('learning::homework.accepts.'.$homework->accepts) }} · {{ __('learning::homework.statuses.'.$homework->status) }}</p>@if ($homework->instructions)<p>{{ $homework->instructions }}</p>@endif</div>
    @endif
    @if ($report->review_note)
        <div><h4>{{ __('learning::reports.detail.review_note') }}</h4><p>{{ $report->review_note }}</p></div>
    @endif
</div>
