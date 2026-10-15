{{-- What the student handed in: text, a recording that plays here, or a file; a recording's check when the homework says what to recite. $homework, $at, $expected, $checks. --}}
<div style="display:flex; flex-direction:column; gap:.75rem; font-size:.875rem">
    @if ($homework->instructions)
        <p style="margin:0; color:var(--gray-600); white-space:pre-line">{{ $homework->instructions }}</p>
    @endif
    @if ($expected ?? null)
        <p style="margin:0; color:var(--gray-600)">{{ __('learning::recitation.title') }} · <strong>{{ $expected['title'] }}</strong></p>
    @endif
    @if ($homework->hasPractice())
        <p style="margin:0; padding:.45rem .7rem; border-radius:var(--radius-lg); background:var(--success-50); color:var(--success-800)">🎮 {{ $homework->practice_result ? __('learning::homework.practice.result', $homework->practice_result) : __('learning::homework.practice.waiting') }}</p>
    @endif
    @foreach ($homework->submissions as $s)
        <div style="border:1px solid var(--gray-200); border-radius:var(--radius-lg); padding:.6rem .75rem; display:flex; flex-direction:column; gap:.4rem">
            <small style="color:var(--gray-500)">{{ $at($s->submitted_at) }}</small>
            @if ($s->body) <p style="margin:0; white-space:pre-line">{{ $s->body }}</p> @endif
            @if ($s->file_path)
                @if ($s->isAudio())
                    <audio controls preload="none" src="{{ route('learning.files', ['submission' => $s->id]) }}" style="width:100%"></audio>
                    @include('learning::filament.teacher.recitation-check', ['check' => ($checks ?? [])[$s->id] ?? null])
                @else
                    <a href="{{ route('learning.files', ['submission' => $s->id]) }}" target="_blank" style="color:var(--primary-700); font-weight:600">{{ __('learning::teacher.homework.file') }} · {{ $s->file_name }}</a>
                @endif
            @endif
        </div>
    @endforeach
    @if ($homework->feedback || $homework->grade)
        <p style="margin:0"><strong>{{ $homework->grade ? $homework->grade.'/5' : '' }}</strong> {{ $homework->feedback }}</p>
    @endif
</div>
