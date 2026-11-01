@php
    $x = $this->lessons;
    $w = 'quran::screens.lessons.';
@endphp
<x-filament-panels::page>
    @include('quran::filament.parts.styles')
    <div class="qs">
        @foreach (['record' => 'warning', 'today' => 'primary', 'later' => 'gray'] as $group => $tone)
            @if ($x[$group] !== [] || $group === 'today')
                <section class="qs" style="gap: .6rem" aria-labelledby="qls-{{ $group }}">
                    <h2 class="qs-h2" id="qls-{{ $group }}">{{ __($w.'groups.'.$group) }}</h2>
                    @if ($x[$group] === [])
                        <p class="qs-muted qs-small" style="margin: 0">{{ __($w.'none_today') }}</p>
                    @else
                        <div class="qs-grid lms-stagger">
                            @foreach ($x[$group] as $r)
                                <a class="qs-card lms-lift" href="{{ $r['url'] }}" wire:navigate wire:key="ql-{{ $r['id'] }}">
                                    <div class="qs-between">
                                        <div style="min-width: 0">
                                            <h3 class="qs-card-title">{{ $r['students'] }}</h3>
                                            <p class="qs-card-sub">{{ $r['subject'] }}@if ($r['program']) · {{ $r['program'] }}@endif</p>
                                        </div>
                                        <span class="qs-pill" data-tone="{{ $tone }}">{{ $group === 'today' ? $r['time'] : $r['when'] }}</span>
                                    </div>
                                    <div class="qs-row">
                                        @if ($r['group'])<span class="qs-pill" data-tone="info"><x-filament::icon icon="heroicon-m-user-group" style="inline-size: .9rem" />{{ __($w.'group') }}</span>@endif
                                        @if (! $r['has_plan'])<span class="qs-pill" data-tone="warning">{{ __($w.'no_plan') }}</span>@endif
                                        @if ($r['recorded'])<span class="qs-pill" data-tone="success">{{ __($w.'recorded') }}</span>@endif
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endif
        @endforeach

        @if ($x['exams'] !== [])
            <section class="qs" style="gap: .6rem" aria-labelledby="qls-exams">
                <h2 class="qs-h2" id="qls-exams">{{ __($w.'groups.exams') }}</h2>
                <div class="qs-grid lms-stagger">
                    @foreach ($x['exams'] as $e)
                        <a class="qs-card lms-lift" href="{{ $e['url'] }}" wire:navigate wire:key="qe-{{ $e['id'] }}">
                            <div class="qs-between">
                                <div><h3 class="qs-card-title">{{ $e['student'] }}</h3><p class="qs-card-sub">{{ $e['stop']['label'] }}</p></div>
                                <span class="qs-pill" data-tone="info">{{ $e['lesson']['starts_at']['label'] ?? $e['status_label'] }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
