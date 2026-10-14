@php
    $l = 'learning::family.home.';
    $d = $this->data();
@endphp
<x-filament-widgets::widget>
    @include('learning::filament.family.styles')
    <div class="lf-stack lms-stagger">
        @foreach ($d['courses'] as $course)
            <a class="lf-card lms-lift" href="{{ \Modules\Learning\Filament\Family\FamilyProgress::getUrl() }}" style="text-decoration: none; color: inherit" wire:key="home-course-{{ $course['id'] }}">
                <div class="lf-head">
                    <div>
                        <h3>{{ $course['subject'] }}</h3>
                        <small>{{ $course['map'] ? __($l.'here', ['lesson' => $course['map']['finished'] ? __('learning::progress.finished') : ($course['map']['current']['title'] ?? '—')]) : __('learning::family.progress.no_program') }}</small>
                        @if ($course['state'] ?? null)
                            <span class="lf-state">{{ $course['state'] }}</span>
                        @endif
                    </div>
                    @if ($course['map'])
                        <span class="lf-pct">{{ $course['map']['percent'] }}%</span>
                    @endif
                </div>
                @if ($course['map'])
                    <span class="lf-bar"><i class="lms-bar-fill" style="width: {{ $course['map']['percent'] }}%"></i></span>
                @endif
            </a>
        @endforeach

        <div class="lf-facts">
            <a class="lf-fact lms-lift" href="{{ \Modules\Learning\Filament\Family\FamilyHomework::getUrl() }}" style="text-decoration: none; color: inherit">
                <small>{{ __($l.'homework') }}</small>
                <strong>{{ $d['homework'] > 0 ? trans_choice($l.'homework_open', $d['homework'], ['n' => $d['homework']]) : __($l.'homework_none') }}</strong>
                @if ($d['next_due']) <small>{{ __($l.'next_due', ['date' => $d['next_due']]) }}</small> @endif
            </a>
            <a class="lf-fact lms-lift" href="{{ \Modules\Learning\Filament\Family\FamilyReports::getUrl() }}" style="text-decoration: none; color: inherit">
                <small>{{ __($l.'last_report') }}</small>
                @if ($d['report'])
                    <strong>{{ $d['report']['date'] }} @if ($d['report']['overall']) <span class="lf-stars">{{ str_repeat('★', $d['report']['overall']) }}</span> @endif</strong>
                    @if ($d['report']['note']) <small style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden">{{ $d['report']['note'] }}</small> @endif
                @else
                    <strong>{{ __($l.'no_report') }}</strong>
                @endif
            </a>
        </div>
    </div>
</x-filament-widgets::widget>
