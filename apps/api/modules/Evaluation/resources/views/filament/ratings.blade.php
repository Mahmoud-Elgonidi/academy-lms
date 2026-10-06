{{-- Recent ratings. $ratings from TeacherPerformance::ratingsOf(). --}}
@php $l = 'evaluation::kpis.'; @endphp
<section class="kp-base kp-box">
    <h3 class="lms-heading">{{ $title ?? __($l.'ratings_title') }}</h3>
    @if ($ratings['count'] > 0)
        <span class="kp-summary">{{ __($l.'ratings_summary', ['avg' => $ratings['avg'], 'count' => $ratings['count']]) }}</span>
    @endif
    @forelse ($ratings['list'] as $r)
        <div class="kp-event">
            <div>
                <span class="kp-stars" aria-label="{{ $r['stars'] }}/5">{{ str_repeat('★', $r['stars']) }}<span>{{ str_repeat('★', 5 - $r['stars']) }}</span></span>
                @if ($r['tags'])<span class="kp-tags">@foreach ($r['tags'] as $t)<span>{{ $t }}</span>@endforeach</span>@endif
                @if ($r['comment'])<p>{{ $r['comment'] }}</p>@endif
                <small>{{ collect([$r['who'], $r['lesson']])->filter()->implode(' · ') }}</small>
            </div>
        </div>
    @empty
        <span class="kp-empty">{{ __($l.'no_ratings') }}</span>
    @endforelse
</section>
