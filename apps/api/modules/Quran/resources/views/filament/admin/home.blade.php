@php
    $d = $this->home;
    $links = $this->links();
    $w = 'quran::screens.home.';
    $pace = $d['pace'];
    $planned = $pace['on_track'] + $pace['behind'] + $pace['new'] + $pace['frozen'] + $pace['finished'];
    $stats = [
        ['k' => 'memorized', 'n' => $d['month']['memorized'], 'tone' => 'primary', 'icon' => 'heroicon-o-book-open', 'url' => $links['hifz']],
        ['k' => 'lessons', 'n' => $d['month']['lessons'], 'tone' => 'info', 'icon' => 'heroicon-o-check-badge', 'url' => null],
        ['k' => 'on_track', 'n' => $pace['on_track'], 'tone' => 'success', 'icon' => 'heroicon-o-arrow-trending-up', 'url' => $links['students'] ? $links['students'].'?pace=on_track' : null],
        ['k' => 'behind', 'n' => $pace['behind'], 'tone' => $pace['behind'] > 0 ? 'danger' : 'success', 'icon' => 'heroicon-o-arrow-trending-down', 'url' => $links['students'] ? $links['students'].'?pace=behind' : null],
        ['k' => 'exams_waiting', 'n' => $d['exams']['suggested'], 'tone' => $d['exams']['suggested'] > 0 ? 'warning' : 'info', 'icon' => 'heroicon-o-clipboard-document-list', 'url' => $links['exams']],
        ['k' => 'exams_booked', 'n' => $d['exams']['booked'], 'tone' => 'info', 'icon' => 'heroicon-o-calendar-days', 'url' => $links['exams'] ? $links['exams'].'?tab=booked' : null],
    ];
@endphp
<x-filament-panels::page>
    @include('quran::filament.parts.styles')
    <div class="qs">
        @if ($d['courses'] === 0 && $d['programs'] === 0)
            <section class="qs-empty" aria-labelledby="qh-empty">
                <span class="qs-empty-ico"><x-filament::icon icon="heroicon-o-book-open" /></span>
                <strong id="qh-empty">{{ __($w.'empty.title') }}</strong>
                <p>{{ __($w.'empty.hint') }}</p>
                @if ($links['programs'])
                    <x-filament::button tag="a" :href="$links['programs']" icon="heroicon-m-plus" wire:navigate>{{ __($w.'empty.action') }}</x-filament::button>
                @endif
            </section>
        @endif

        <div class="qs-stats lms-stagger">
            @foreach ($stats as $s)
                @php $tag = $s['url'] ? 'a' : 'div'; @endphp
                <{{ $tag }} @if ($s['url']) href="{{ $s['url'] }}" wire:navigate @endif class="qs-stat {{ $s['url'] ? 'lms-lift' : '' }}" data-tone="{{ $s['tone'] }}">
                    <span class="qs-stat-ico"><x-filament::icon :icon="$s['icon']" /></span>
                    <div>
                        <strong data-count="{{ $s['n'] }}">{{ $s['n'] }}</strong>
                        <small>{{ __($w.'stats.'.$s['k']) }}</small>
                    </div>
                </{{ $tag }}>
            @endforeach
        </div>

        <section class="qs-panel" aria-labelledby="qh-pace">
            <div class="qs-between">
                <div>
                    <h2 class="qs-h2" id="qh-pace">{{ __($w.'pace.title') }}</h2>
                    <p class="qs-muted qs-small" style="margin: .15rem 0 0">{{ __($w.'pace.hint', ['days' => \Modules\Quran\Domain\Programs\Pace::DAYS]) }}</p>
                </div>
                @if ($links['students'])
                    <x-filament::link :href="$links['students']" wire:navigate>{{ __($w.'pace.all') }}</x-filament::link>
                @endif
            </div>
            @if ($planned === 0)
                <p class="qs-muted qs-small" style="margin: 0">{{ __($w.'pace.none') }}</p>
            @else
                <div class="qs-progress" style="display: flex; block-size: .7rem" role="img" aria-label="{{ __($w.'pace.label', ['on' => $pace['on_track'], 'behind' => $pace['behind']]) }}">
                    @foreach (['on_track' => 'success', 'new' => 'info', 'behind' => 'danger', 'frozen' => 'warning', 'finished' => 'primary'] as $k => $tone)
                        @if ($pace[$k] > 0)<i class="lms-bar-fill" style="inline-size: {{ round(100 * $pace[$k] / $planned, 2) }}%; background: var(--{{ $tone }}-500); border-radius: 0"></i>@endif
                    @endforeach
                </div>
                <div class="qs-row qs-small">
                    @foreach (['on_track' => 'success', 'new' => 'info', 'behind' => 'danger', 'frozen' => 'warning', 'finished' => 'primary'] as $k => $tone)
                        @continue($pace[$k] === 0 && $k !== 'on_track' && $k !== 'behind')
                        <span class="qs-pill" data-tone="{{ $tone }}"><span class="qs-dot"></span>{{ __('quran::screens.pace_status.'.$k) }} · {{ $pace[$k] }}</span>
                    @endforeach
                    @if ($pace['no_plan'] > 0)
                        <span class="qs-pill"><span class="qs-dot"></span>{{ __('quran::screens.pace_status.no_plan') }} · {{ $pace['no_plan'] }}</span>
                    @endif
                </div>
            @endif
        </section>

        <div class="qs-cols lms-stagger">
            <section class="qs-panel" aria-labelledby="qh-behind">
                <h2 class="qs-h2" id="qh-behind">{{ __($w.'behind.title') }}</h2>
                @forelse ($d['behind'] as $r)
                    <div class="qs-list">
                        <div>
                            <div style="min-width: 0">
                                <div class="qs-name">{{ $r['student'] }}</div>
                                <div class="qs-muted qs-small">{{ $r['teacher'] }} · {{ $r['plan']['program'] }}</div>
                            </div>
                            <span class="qs-pill" data-tone="danger">{{ __($w.'behind.passed', ['passed' => $r['plan']['advanced'], 'of' => $r['plan']['recorded']]) }}</span>
                        </div>
                    </div>
                @empty
                    <p class="qs-muted qs-small" style="margin: 0">{{ __($w.'behind.none') }}</p>
                @endforelse
                @if ($d['no_plan'] !== [])
                    <h3 class="qs-h3" style="margin-top: .4rem">{{ __($w.'no_plan.title') }}</h3>
                    <div class="qs-list">
                        @foreach ($d['no_plan'] as $r)
                            <div>
                                <div><div class="qs-name">{{ $r['student'] }}</div><div class="qs-muted qs-small">{{ $r['teacher'] }} · {{ $r['subject'] }}</div></div>
                                @if ($links['students'])<x-filament::link :href="$links['students'].'?pace=no_plan'" size="sm" wire:navigate>{{ __($w.'no_plan.start') }}</x-filament::link>@endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="qs-panel" aria-labelledby="qh-exams">
                <div class="qs-between">
                    <h2 class="qs-h2" id="qh-exams">{{ __($w.'exams.title') }}</h2>
                    <span class="qs-pill" data-tone="success">{{ __($w.'exams.month', ['passed' => $d['month']['passed'], 'failed' => $d['month']['failed']]) }}</span>
                </div>
                @forelse ($d['booked'] as $e)
                    <div class="qs-list">
                        <div>
                            <div><div class="qs-name">{{ $e['student'] }}</div><div class="qs-muted qs-small">{{ $e['label'] }}</div></div>
                            <span class="qs-pill" data-tone="info">{{ $e['when'] ?? $e['status_label'] }}</span>
                        </div>
                    </div>
                @empty
                    <p class="qs-muted qs-small" style="margin: 0">{{ $d['exams']['suggested'] > 0 ? trans_choice($w.'exams.waiting', $d['exams']['suggested'], ['n' => $d['exams']['suggested']]) : __($w.'exams.none') }}</p>
                @endforelse
                @if ($links['exams'] && $d['exams']['suggested'] > 0)
                    <div><x-filament::button tag="a" :href="$links['exams']" size="sm" color="warning" icon="heroicon-m-calendar-days" wire:navigate>{{ __($w.'exams.book') }}</x-filament::button></div>
                @endif
            </section>
        </div>

        <section class="qs-panel" aria-labelledby="qh-certs">
            <div class="qs-between">
                <h2 class="qs-h2" id="qh-certs">{{ __($w.'certificates.title') }}</h2>
                @if ($links['certificates'])
                    <x-filament::link :href="$links['certificates']" wire:navigate>{{ __($w.'certificates.all') }}</x-filament::link>
                @endif
            </div>
            @if ($d['certificates'] === [])
                <p class="qs-muted qs-small" style="margin: 0">{{ __($w.'certificates.none') }}</p>
            @else
                <div class="qs-grid lms-stagger">
                    @foreach ($d['certificates'] as $c)
                        <div class="qs-card" @if ($c['revoked']) data-muted @endif>
                            <div class="qs-between">
                                <span class="qs-tag" dir="ltr">{{ $c['number'] }}</span>
                                <span class="qs-pill" data-tone="{{ $c['revoked'] ? 'danger' : 'success' }}">{{ $c['revoked'] ? __('quran::screens.certificates.revoked_badge') : $c['grade'] }}</span>
                            </div>
                            <div><div class="qs-name">{{ $c['student'] }}</div><div class="qs-muted qs-small">{{ $c['portion'] }} · {{ $c['day'] }}</div></div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</x-filament-panels::page>
