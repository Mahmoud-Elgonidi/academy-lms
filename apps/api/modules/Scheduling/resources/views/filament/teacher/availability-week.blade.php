{{-- A week as rows (MyAvailability::rows): each day's times written out and a small line from the morning to midnight whose spans fill in; compared with a week before, new, changed and removed days marked and unchanged ones calmer. --}}
@php $l = 'scheduling::teacher.availability.'; $compare ??= false; @endphp
<ul class="ta-week lms-stagger">
    @foreach ($rows as $i => $row)
        <li class="ta-day" data-mark="{{ $compare ? $row['mark'] : 'none' }}" wire:key="{{ $key }}-{{ $row['day'] }}">
            <span class="ta-day-name">
                {{ $row['label'] }}
                @if ($compare && $row['mark'] !== 'same')
                    <span class="ta-mark" data-mark="{{ $row['mark'] }}">{{ __($l.'marks.'.$row['mark']) }}</span>
                @endif
            </span>
            <span class="ta-bar" aria-hidden="true">
                @foreach ($row['spans'] as $span)
                    <span class="lms-bar-fill" data-kind="{{ $span['kind'] }}" style="{{ $span['style'] }}; animation-delay: {{ 150 + $i * 45 }}ms"></span>
                @endforeach
            </span>
            <span class="ta-times" dir="auto">
                @forelse ($row['times'] as $time)
                    <span>{{ $time }}</span>
                @empty
                    <span class="ta-off">{{ __($l.'day_off') }}</span>
                @endforelse
                @foreach ($row['removed'] as $time)
                    <s>{{ $time }}</s>
                @endforeach
            </span>
        </li>
    @endforeach
</ul>
@if ($compare && collect($rows)->contains(fn ($r) => collect($r['spans'])->contains('kind', 'removed') || collect($r['spans'])->contains('kind', 'added')))
    <p class="ta-legend">
        <span><i data-kind="added"></i>{{ __($l.'legend.added') }}</span>
        <span><i data-kind="removed"></i>{{ __($l.'legend.removed') }}</span>
    </p>
@endif
