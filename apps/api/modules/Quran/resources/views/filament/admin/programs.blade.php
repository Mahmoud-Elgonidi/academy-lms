@php
    $c = $this->catalog;
    $w = 'quran::screens.programs.';
    $parts = app(\Modules\Quran\Programs\JuzStrip::class)->count() ?: 30;
    $icons = ['hifz' => 'heroicon-o-book-open', 'talqin' => 'heroicon-o-speaker-wave', 'tilawa' => 'heroicon-o-musical-note', 'noorani' => 'heroicon-o-language',
        'review' => 'heroicon-o-arrow-path', 'ijaza' => 'heroicon-o-trophy'];
@endphp
<x-filament-panels::page>
    @include('quran::filament.parts.styles')
    <div class="qs">
        @if (! $c['text'])
            <div class="qs-banner" role="status"><x-filament::icon icon="heroicon-o-exclamation-triangle" /><span>{{ __($w.'no_text') }}</span></div>
        @endif

        <div class="qs-between">
            <div class="qs-seg" role="group" aria-label="{{ __($w.'show') }}">
                <button type="button" aria-pressed="{{ $archived ? 'false' : 'true' }}" wire:click="$set('archived', false)">{{ __($w.'active') }}</button>
                <button type="button" aria-pressed="{{ $archived ? 'true' : 'false' }}" wire:click="$set('archived', true)">{{ __($w.'archived') }} · {{ $c['archived'] }}</button>
            </div>
        </div>

        @if ($c['programs'] === [])
            <section class="qs-empty">
                <span class="qs-empty-ico"><x-filament::icon icon="heroicon-o-rectangle-stack" /></span>
                <strong>{{ __($archived ? $w.'archived_none' : $w.'empty.title') }}</strong>
                @unless ($archived)<p>{{ __($w.'empty.hint') }}</p>@endunless
            </section>
        @else
            <div class="qs-grid lms-stagger">
                @foreach ($c['programs'] as $p)
                    <div class="qs-card lms-lift" wire:key="pr-{{ $p['id'] }}" @if ($p['archived']) data-muted @endif>
                        <a href="{{ $p['url'] }}" wire:navigate style="display: flex; flex-direction: column; gap: .7rem; color: inherit; text-decoration: none">
                            <div class="qs-between">
                                <div style="display: flex; gap: .65rem; min-width: 0">
                                    <span class="qs-stat-ico" style="inline-size: 2.3rem; block-size: 2.3rem"><x-filament::icon :icon="$icons[$p['track']] ?? 'heroicon-o-book-open'" /></span>
                                    <div style="min-width: 0">
                                        <h3 class="qs-card-title">{{ $p['name'] }}</h3>
                                        <p class="qs-card-sub">{{ $p['track_label'] }} · {{ $p['lessons'] ?? $p['direction'] }}</p>
                                    </div>
                                </div>
                                <span class="qs-pill" data-tone="{{ $p['students'] > 0 ? 'primary' : 'gray' }}">{{ trans_choice($w.'students', $p['students'], ['n' => $p['students']]) }}</span>
                            </div>
                            @if ($p['strip'])
                                @include('quran::filament.parts.strip', ['parts' => $parts, 'scope' => $p['strip'], 'height' => '.9rem', 'label' => __($w.'scope_label', ['from' => $p['strip']['from'], 'to' => $p['strip']['to']])])
                            @endif
                            <div class="qs-meta">
                                <span><x-filament::icon icon="heroicon-m-clock" />{{ $p['pace'] }}</span>
                                <span><x-filament::icon icon="heroicon-m-clipboard-document-check" />{{ $p['exams'] }}</span>
                                @if ($p['certificate'])<span><x-filament::icon icon="heroicon-m-trophy" />{{ __($w.'certificate') }}</span>@endif
                            </div>
                        </a>
                        @if ($p['archived'])
                            <div>{{ ($this->restoreAction)(['program' => $p['id']]) }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        <section aria-labelledby="pr-ready" class="qs" style="gap: .75rem">
            <div>
                <h2 class="qs-h2" id="pr-ready">{{ __($w.'ready.title') }}</h2>
                <p class="qs-muted qs-small" style="margin: .15rem 0 0">{{ __($w.'ready.hint') }}</p>
            </div>
            <div class="qs-grid lms-stagger">
                @foreach ($c['presets'] as $p)
                    @php $off = $p['needs_text'] && ! $c['text']; @endphp
                    <a class="qs-card {{ $off ? '' : 'lms-lift' }}" href="{{ $off ? '#' : $p['url'] }}" @unless ($off) wire:navigate @endunless @if ($off) aria-disabled="true" data-muted @endif wire:key="ps-{{ $p['key'] }}">
                        <div style="display: flex; gap: .65rem">
                            <span class="qs-stat-ico" style="inline-size: 2.3rem; block-size: 2.3rem"><x-filament::icon :icon="$icons[$p['track']] ?? 'heroicon-o-book-open'" /></span>
                            <div>
                                <h3 class="qs-card-title">{{ $p['name'] }}</h3>
                                <p class="qs-card-sub">{{ $p['track_label'] }}</p>
                            </div>
                        </div>
                        <p class="qs-muted qs-small" style="margin: 0">{{ $p['hint'] }}</p>
                        @if ($p['strip'])
                            @include('quran::filament.parts.strip', ['parts' => $parts, 'scope' => $p['strip'], 'height' => '.7rem'])
                        @endif
                        <span class="qs-small" style="color: var(--primary-600); font-weight: 600">{{ __($w.'ready.use') }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    </div>
</x-filament-panels::page>
