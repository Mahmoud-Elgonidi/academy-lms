{{--
    A course's Quran plan for the office and the teacher (Queries\Board\QuranCourses::plan): $q = ['plan' => ?array, 'shares' => array,
    'parts' => int, 'recent' => list (ProgramPresenter::result), 'memorized' => int]. $none: what to say without a plan.
--}}
@php
    $w = 'quran::screens.plan_card.';
    $plan = $q['plan'];
    $tones = ['on_track' => 'success', 'behind' => 'danger', 'new' => 'info', 'frozen' => 'warning', 'finished' => 'primary'];
    $gradeTone = fn (string $g) => match ($g) { 'excellent', 'very_good' => 'success', 'good' => 'primary', 'acceptable' => 'warning', default => 'danger' };
@endphp
<section class="qs-panel" aria-labelledby="qp-title">
    <div class="qs-between">
        <div>
            <h2 class="qs-h2" id="qp-title">{{ $plan['program'] ?? __($w.'title') }}</h2>
            <p class="qs-muted qs-small" style="margin: .15rem 0 0">{{ $plan ? $plan['track_label'].' · '.$plan['pace'] : ($none ?? __($w.'none')) }}</p>
        </div>
        @if ($plan)
            <span class="qs-pill" data-tone="{{ $tones[$plan['pace_status']] }}"><span class="qs-dot"></span>{{ __('quran::screens.pace_status.'.$plan['pace_status']) }}</span>
        @endif
    </div>
    @include('quran::filament.parts.strip', ['parts' => $q['parts'], 'shares' => $q['shares'], 'at' => $plan['juz'] ?? null, 'legend' => true,
        'label' => __('quran::screens.students.strip_label', ['name' => '', 'pages' => $q['memorized']])])
    @if ($plan)
        <div class="qs-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $plan['progress'] }}" aria-label="{{ __($w.'progress') }}">
            <i class="lms-bar-fill" style="inline-size: {{ max(1, $plan['progress']) }}%"></i>
        </div>
        <p class="qs-small" style="margin: 0">{{ $plan['finished'] ? __('quran::programs.finished') : __('quran::screens.students.at', ['where' => $plan['position'] ?? '—', 'progress' => $plan['progress']]) }}</p>
        @if ($plan['frozen_label'])<div class="qs-banner" role="status"><x-filament::icon icon="heroicon-o-pause-circle" /><span>{{ $plan['frozen_label'] }}</span></div>@endif
        @if ($plan['portion'] && ! $plan['finished'])
            <div>
                <h3 class="qs-h3" style="margin-bottom: .35rem">{{ __($w.'next') }}</h3>
                <div class="qs-list">
                    @if ($plan['portion']['lesson'])
                        <div><span class="qs-muted qs-small">{{ __('quran::programs.parts.new') }}</span><strong class="qs-small">{{ $plan['portion']['lesson']['title'] }}</strong></div>
                    @endif
                    @foreach (['new', 'near', 'far'] as $part)
                        @if ($plan['portion'][$part])
                            <div><span class="qs-muted qs-small">{{ $plan['portion'][$part]['label'] }}</span><strong class="qs-small" style="text-align: end">{{ collect($plan['portion'][$part]['ranges'])->pluck('title')->implode('، ') }}</strong></div>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif
        @if (($q['recent'] ?? []) !== [])
            <div>
                <h3 class="qs-h3" style="margin-bottom: .35rem">{{ __($w.'recent') }}</h3>
                <div class="qs-row">
                    @foreach ($q['recent'] as $r)
                        @php $g = $r['parts']['new']['grade'] ?? ($r['parts']['far']['grade'] ?? ($r['parts']['near']['grade'] ?? null)); @endphp
                        @if ($g)
                            <span class="qs-pill" data-tone="{{ $gradeTone($g) }}" title="{{ $r['recorded_at']['label'] }}">{{ __('quran::programs.grades.'.$g) }}@if ($r['advanced']) ↑@endif</span>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</section>
