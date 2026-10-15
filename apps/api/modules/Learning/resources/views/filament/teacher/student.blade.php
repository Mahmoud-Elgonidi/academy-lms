@php $l = 'learning::teacher.students.'; $d = $this->summary; $p = 'learning::progress.'; @endphp
<x-filament-panels::page>
    @include('learning::filament.family.styles')
    <div class="lf-stack lms-stagger">
        <section class="lf-card">
            @if ($d['map'])
                <div class="lf-head">
                    <div><h3>{{ $d['map']['program']['name'] }}</h3><small>{{ __($p.'lessons', ['mastered' => $d['map']['lessons']['mastered'], 'total' => $d['map']['lessons']['total']]) }}</small></div>
                    <span class="lf-pct">{{ $d['map']['percent'] }}%</span>
                </div>
                <span class="lf-bar"><i class="lms-bar-fill" style="width: {{ $d['map']['percent'] }}%"></i></span>
                @include('learning::filament.roadmap', ['map' => $d['map']])
            @else
                <p class="lf-muted" style="margin: 0">{{ __($l.'no_program') }}</p>
            @endif
        </section>

        @if ($hifz = $this->hifz)
            <section class="lf-card">
                <div class="lf-head"><h3>{{ __('learning::hifz.title') }}</h3></div>
                @include('learning::filament.hifz.map', ['map' => $hifz['map']])
                <h4 style="font-size: .85rem; font-weight: 700; margin: .4rem 0 0">{{ __('learning::hifz.recent') }}</h4>
                @forelse ($hifz['recent'] as $r)
                    <small class="lf-muted" style="display: block">{{ __('learning::hifz.page', ['page' => $r['page']]) }} · {{ $r['grade'] }} · {{ $r['source'] }} · {{ $r['at'] }}</small>
                @empty
                    <small class="lf-muted">{{ __('learning::hifz.no_recent') }}</small>
                @endforelse
            </section>
        @endif

        <h2 style="font-size: 1rem; font-weight: 700; margin: .5rem 0 0">{{ __($l.'reports') }}</h2>
        @forelse ($d['reports'] as $r)
            <a class="lf-card lms-lift" href="{{ $r['url'] }}" style="text-decoration: none; color: inherit">
                <div class="lf-head">
                    <h3 style="font-size: .95rem">{{ $r['date'] }}</h3>
                    <span class="lf-pill" @if (in_array($r['status'], ['due', 'overdue'], true)) data-tone="{{ $r['status'] === 'overdue' ? 'bad' : 'warn' }}" @endif>
                        {{ __('learning::reports.statuses.'.$r['status']) }}@if ($r['overall']) · {{ $r['overall'] }}/5 @endif
                    </span>
                </div>
                @if ($r['note']) <p class="lf-note">{{ $r['note'] }}</p> @endif
                @if ($r['internal']) <small class="lf-muted">🔒 {{ $r['internal'] }}</small> @endif
            </a>
        @empty
            <div class="lf-empty">{{ __($l.'no_report') }}</div>
        @endforelse
    </div>
</x-filament-panels::page>
