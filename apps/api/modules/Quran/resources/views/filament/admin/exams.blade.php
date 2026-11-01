@php
    $x = $this->exams;
    $w = 'quran::screens.exams.';
    $tones = ['suggested' => 'warning', 'booked' => 'info', 'passed' => 'success', 'failed' => 'danger', 'cancelled' => 'gray'];
    $book = \Modules\Quran\Filament\Pages\QuranExams::mayBook();
@endphp
<x-filament-panels::page>
    @include('quran::filament.parts.styles')
    <div class="qs">
        @unless ($x['enabled'])
            <div class="qs-banner" data-tone="info" role="status">
                <x-filament::icon icon="heroicon-o-information-circle" />
                <span>{{ __($w.'off') }}@if (\Modules\Quran\Filament\Pages\QuranSettingsPage::canAccess()) <a class="qs-link" href="{{ \Modules\Quran\Filament\Pages\QuranSettingsPage::getUrl() }}" wire:navigate>{{ __($w.'off_settings') }}</a>@endif</span>
            </div>
        @endunless

        <x-filament::tabs>
            @foreach (\Modules\Quran\Filament\Pages\QuranExams::TABS as $t)
                <x-filament::tabs.item :active="$tab === $t" wire:click="$set('tab', '{{ $t }}')" :badge="($x['counts'][$t] ?? 0) ?: null" :badge-color="$t === 'suggested' ? 'warning' : 'gray'">
                    {{ __($w.'tabs.'.$t) }}
                </x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>

        @if ($x['rows'] === [])
            <section class="qs-empty">
                <span class="qs-empty-ico"><x-filament::icon icon="heroicon-o-clipboard-document-check" /></span>
                <strong>{{ __($w.'empty.'.$tab) }}</strong>
                <p>{{ __($w.'empty.'.$tab.'_hint') }}</p>
            </section>
        @else
            <div class="qs-grid lms-stagger">
                @foreach ($x['rows'] as $e)
                    <article class="qs-card" wire:key="ex-{{ $e['id'] }}">
                        <div class="qs-between">
                            <div style="min-width: 0">
                                <h3 class="qs-card-title">{{ $e['student'] }}</h3>
                                <p class="qs-card-sub">{{ $e['program']['name'] }}</p>
                            </div>
                            <span class="qs-pill" data-tone="{{ $tones[$e['status']] }}"><span class="qs-dot"></span>{{ $e['status_label'] }}</span>
                        </div>
                        <div class="qs-row">
                            <span class="qs-tag">{{ $e['stop']['label'] }}</span>
                            @if ($e['retake'])<span class="qs-pill" data-tone="warning">{{ __($w.'retake') }}</span>@endif
                        </div>
                        <div class="qs-meta">
                            @if ($e['examiner'])<span><x-filament::icon icon="heroicon-m-user" />{{ $e['examiner']['name'] }}</span>@endif
                            @if ($e['lesson'])<span><x-filament::icon icon="heroicon-m-calendar-days" />{{ $e['lesson']['starts_at']['label'] }}</span>@endif
                            @if ($e['status'] === 'suggested' && $e['not_before'])<span><x-filament::icon icon="heroicon-m-clock" />{{ __($w.'not_before', ['day' => \Carbon\CarbonImmutable::parse($e['not_before'])->locale(app()->getLocale())->translatedFormat('j F')]) }}</span>@endif
                        </div>
                        @if ($e['result'])
                            <div class="qs-row">
                                <span class="qs-pill" data-tone="{{ $e['result']['passed'] ? 'success' : 'danger' }}">{{ $e['result']['grade_label'] }}</span>
                                @foreach ($e['result']['mistakes'] as $m)<span class="qs-muted qs-small">{{ $m['label'] }}: {{ $m['count'] }}</span>@endforeach
                            </div>
                            @if ($e['result']['note'])<p class="qs-small" style="margin: 0; color: var(--gray-600)">{{ $e['result']['note'] }}</p>@endif
                            @if ($e['certificate'])<span class="qs-pill" data-tone="primary" style="align-self: flex-start"><x-filament::icon icon="heroicon-m-trophy" style="inline-size: .9rem" />{{ $e['certificate'] }}</span>@endif
                        @endif
                        <div class="qs-row" style="margin-top: auto; justify-content: flex-end">
                            @if ($e['status'] === 'suggested' && $book && $x['enabled'])
                                {{ ($this->bookAction)(['exam' => $e['id']]) }}
                            @endif
                            @if ($e['result_url'])
                                <x-filament::button tag="a" size="sm" :href="$e['result_url']" icon="heroicon-m-pencil-square" wire:navigate>{{ __($w.'record') }}</x-filament::button>
                            @endif
                            @if (in_array($e['status'], ['suggested', 'booked'], true) && $book)
                                {{ ($this->cancelAction)(['exam' => $e['id']]) }}
                            @endif
                            @if ($e['status'] === 'passed')
                                {{ ($this->issueAction)(['exam' => $e['id']]) }}
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
