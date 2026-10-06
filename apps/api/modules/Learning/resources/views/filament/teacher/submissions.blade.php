{{-- What the student handed in: text, a recording that plays here, or a file. $homework, $at. --}}
<div style="display:flex; flex-direction:column; gap:.75rem; font-size:.875rem">
    @if ($homework->instructions)
        <p style="margin:0; color:var(--gray-600); white-space:pre-line">{{ $homework->instructions }}</p>
    @endif
    @foreach ($homework->submissions as $s)
        <div style="border:1px solid var(--gray-200); border-radius:var(--radius-lg); padding:.6rem .75rem; display:flex; flex-direction:column; gap:.4rem">
            <small style="color:var(--gray-500)">{{ $at($s->submitted_at) }}</small>
            @if ($s->body) <p style="margin:0; white-space:pre-line">{{ $s->body }}</p> @endif
            @if ($s->file_path)
                @if ($s->isAudio())
                    <audio controls preload="none" src="{{ route('learning.files', ['submission' => $s->id]) }}" style="width:100%"></audio>
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
