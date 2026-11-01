@php
    $l = $this->lesson;
    $w = 'quran::screens.lesson.';
    $plan = $l['plan'];
    $portion = $plan['portion'] ?? null;
    $result = $l['results'][$student] ?? null;
    $mine = $sheet[$student] ?? [];
    $tones = ['new' => 'primary', 'near' => 'info', 'far' => 'warning'];
    $gradeTone = fn (string $g) => match ($g) { 'excellent', 'very_good' => 'success', 'good' => 'primary', 'acceptable' => 'warning', default => 'danger' };
@endphp
<x-filament-panels::page>
    @include('quran::filament.parts.styles')
    @include('quran::filament.parts.counter-styles')
    <style>
        .ql-parts { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .9rem; }
        .ql-part { border-block-start: 4px solid var(--gray-300); }
        .ql-part[data-part="new"] { border-block-start-color: var(--primary-500); }
        .ql-part[data-part="near"] { border-block-start-color: var(--info-500); }
        .ql-part[data-part="far"] { border-block-start-color: var(--warning-500); }
        .ql-part[data-off] { opacity: .55; }
        .ql-ranges { margin: 0; padding: 0; list-style: none; display: flex; flex-direction: column; gap: .2rem; font-weight: 700; font-size: 1rem; color: var(--gray-950); }
        .ql-students { display: flex; flex-wrap: wrap; gap: .4rem; }
        .ql-outcome { display: flex; align-items: center; gap: .75rem; padding: .85rem 1rem; border-radius: .9rem; }
        .ql-outcome[data-o="advance"] { background: var(--success-50); color: var(--success-800); }
        .ql-outcome[data-o="repeat"] { background: var(--warning-50); color: var(--warning-800); }
        .ql-outcome svg { inline-size: 1.5rem; block-size: 1.5rem; flex: none; }
        @media (max-width: 1023px) { .ql-parts { grid-template-columns: minmax(0, 1fr); } }
        .dark .ql-ranges { color: white; }
        .dark .ql-outcome[data-o="advance"] { background: color-mix(in oklab, var(--success-500) 15%, transparent); color: var(--success-200); }
        .dark .ql-outcome[data-o="repeat"] { background: color-mix(in oklab, var(--warning-500) 15%, transparent); color: var(--warning-200); }
    </style>

    <div class="qs">
        @if ($plan === null)
            <section class="qs-empty">
                <span class="qs-empty-ico"><x-filament::icon icon="heroicon-o-map" /></span>
                <strong>{{ __($w.'no_plan.title') }}</strong>
                <p>{{ __($w.'no_plan.hint') }}</p>
            </section>
        @else
            <section class="qs-panel">
                <div class="qs-between">
                    <div>
                        <h2 class="qs-h2">{{ $plan['program']['name'] }}</h2>
                        <p class="qs-muted qs-small" style="margin: .15rem 0 0">{{ $plan['program']['track_label'] }} · {{ $l['pace'] }}</p>
                    </div>
                    <div class="qs-row">
                        @if ($l['student_url'])<x-filament::button tag="a" size="sm" color="gray" :href="$l['student_url']" icon="heroicon-m-map" wire:navigate>{{ __($w.'plan_page') }}</x-filament::button>@endif
                        <x-filament::button tag="a" size="sm" color="gray" :href="$l['class_url']" target="_blank" icon="heroicon-m-video-camera">{{ __($w.'class') }}</x-filament::button>
                    </div>
                </div>
                <div class="qs-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ (int) round(100 * $plan['progress']) }}" aria-label="{{ __($w.'progress') }}">
                    <i class="lms-bar-fill" style="inline-size: {{ max(1, round(100 * $plan['progress'])) }}%"></i>
                </div>
                <p class="qs-muted qs-small" style="margin: 0">{{ __($w.'progress_line', ['n' => (int) round(100 * $plan['progress']), 'where' => $plan['position']['title'] ?? __('quran::programs.finished')]) }}</p>
                @if ($plan['frozen_label'])
                    <div class="qs-banner" role="status"><x-filament::icon icon="heroicon-o-pause-circle" /><span>{{ $plan['frozen_label'] }} {{ __($w.'frozen') }}</span></div>
                @endif
                @if ($l['cancelled'])
                    <div class="qs-banner" data-tone="danger" role="status"><x-filament::icon icon="heroicon-o-x-circle" /><span>{{ __($w.'cancelled') }}</span></div>
                @endif
            </section>

            @if (count($l['students']) > 1)
                <div class="ql-students" role="tablist" aria-label="{{ __($w.'students') }}">
                    @foreach ($l['students'] as $s)
                        <x-filament::button size="sm" :color="$s['id'] === $student ? 'primary' : 'gray'" :outlined="$s['id'] !== $student" wire:click="selectStudent('{{ $s['id'] }}')"
                            role="tab" :aria-selected="$s['id'] === $student ? 'true' : 'false'" :icon="isset($l['results'][$s['id']]) ? 'heroicon-m-check-circle' : null">{{ $s['name'] }}</x-filament::button>
                    @endforeach
                </div>
            @endif

            @if ($portion === null || $portion['finished'] && ! $portion['far'])
                <section class="qs-empty"><strong>{{ $portion === null ? __('quran::programs.errors.no_text') : __('quran::programs.finished') }}</strong></section>
            @else
                <div class="ql-parts lms-stagger">
                    @foreach (['new', 'near', 'far'] as $part)
                        @php $p = $portion[$part]; $has = $p !== null || ($part === 'new' && $portion['lesson']); $s = $mine[$part] ?? null; $g = $l['grades'][$student][$part] ?? null; @endphp
                        <section class="qs-panel ql-part" data-part="{{ $part }}" @if ($has && $s && ! $s['on'] && ! $result) data-off @endif aria-labelledby="ql-{{ $part }}">
                            <div class="qs-between">
                                <h3 class="qs-h3" id="ql-{{ $part }}">{{ __('quran::programs.parts.'.$part) }}</h3>
                                @if ($has && $p && $l['mushaf'] && ! $result)
                                    <x-filament::icon-button icon="heroicon-m-book-open" color="gray" size="sm" wire:click="openMushaf('{{ $part }}')" :label="__($w.'open_mushaf')" :tooltip="__($w.'open_mushaf')" />
                                @endif
                            </div>
                            @if (! $has)
                                <p class="qs-muted qs-small" style="margin: 0">{{ __($w.'none.'.$part) }}</p>
                            @else
                                <ul class="ql-ranges">
                                    @if ($part === 'new' && $portion['lesson'])<li>{{ $portion['lesson']['title'] }}</li>@endif
                                    @foreach ($p['ranges'] ?? [] as $r)<li>{{ $r['title'] }}</li>@endforeach
                                </ul>
                                @if ($p && $p['pages'])<span class="qs-muted qs-small">{{ trans_choice($w.'pages', count($p['pages']), ['pages' => implode('، ', $p['pages'])]) }}</span>@endif
                                @if ($part === 'new')
                                    @foreach ($portion['stops'] as $stop)<span class="qs-pill" data-tone="warning" style="align-self: flex-start">{{ __($w.'stop', ['stop' => $stop['label']]) }}</span>@endforeach
                                @endif

                                @if ($result)
                                    @php $done = $result['parts'][$part] ?? null; @endphp
                                    @if ($done)
                                        <div class="qs-row"><span class="qs-pill" data-tone="{{ $gradeTone($done['grade']) }}">{{ $done['grade_label'] }}</span>
                                            @foreach ($done['mistakes'] as $m)<span class="qs-muted qs-small">{{ $m['label'] }}: {{ $m['count'] }}</span>@endforeach</div>
                                    @else
                                        <span class="qs-muted qs-small">{{ __($w.'not_recited') }}</span>
                                    @endif
                                @elseif ($s)
                                    <label class="qs-row qs-small" style="cursor: pointer">
                                        <x-filament::input.checkbox :checked="$s['on']" wire:click="togglePart('{{ $part }}')" />
                                        <span>{{ __($w.'recited') }}</span>
                                    </label>
                                    @if ($s['on'])
                                        <div class="qc-counters" style="grid-template-columns: minmax(0, 1fr)">
                                            @foreach ($l['types'] as $t)
                                                <div class="qc-counter" wire:key="{{ $student }}-{{ $part }}-{{ $t['key'] }}">
                                                    <span class="qc-label">{{ $t['label'] }}</span>
                                                    <div class="qc-ctrl">
                                                        <button type="button" wire:click="bump('{{ $part }}', '{{ $t['key'] }}', -1)" aria-label="{{ __('quran::screens.result.less', ['kind' => $t['label']]) }}" @disabled(! $l['open'])>−</button>
                                                        <output>{{ $s['mistakes'][$t['key']] ?? 0 }}</output>
                                                        <button type="button" wire:click="bump('{{ $part }}', '{{ $t['key'] }}', 1)" aria-label="{{ __('quran::screens.result.more', ['kind' => $t['label']]) }}" @disabled(! $l['open'])>+</button>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                        @if ($g)
                                            <div class="qs-row">
                                                <span class="qs-pill" data-tone="{{ $gradeTone($g['grade']) }}" style="font-size: .85rem">{{ __('quran::programs.grades.'.$g['grade']) }}</span>
                                                @if ($s['grade'] !== '' && $s['grade'] !== $g['computed'])<span class="qs-muted qs-small">{{ __($w.'computed', ['grade' => __('quran::programs.grades.'.$g['computed'])]) }}</span>@endif
                                            </div>
                                        @endif
                                        <x-filament::input.wrapper :valid="! $errors->has('sheet.'.$student.'.'.$part.'.grade')">
                                            <x-filament::input.select wire:model.live="sheet.{{ $student }}.{{ $part }}.grade" :aria-label="__($w.'own_grade')">
                                                <option value="">{{ __($w.'as_computed') }}</option>
                                                @foreach (\Modules\Quran\Domain\Grading\Grades::ORDER as $gr)<option value="{{ $gr }}">{{ __('quran::programs.grades.'.$gr) }}</option>@endforeach
                                            </x-filament::input.select>
                                        </x-filament::input.wrapper>
                                        @if ($s['grade'] !== '' && $g && $s['grade'] !== $g['computed'])
                                            <x-filament::input.wrapper :valid="! $errors->has('sheet.'.$student.'.'.$part.'.note')">
                                                <x-filament::input type="text" wire:model.blur="sheet.{{ $student }}.{{ $part }}.note" maxlength="500" :placeholder="__($w.'note_why')" />
                                            </x-filament::input.wrapper>
                                        @endif
                                        @error('sheet.'.$student.'.'.$part.'.note')<span class="qs-small" style="color: var(--danger-600)">{{ $message }}</span>@enderror
                                    @endif
                                @endif
                            @endif
                        </section>
                    @endforeach
                </div>

                @if ($result)
                    <div class="ql-outcome" data-o="{{ $result['advanced'] ? 'advance' : 'repeat' }}" role="status">
                        <x-filament::icon :icon="$result['advanced'] ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-arrow-path'" />
                        <div><strong>{{ __($w.'recorded_'.($result['advanced'] ? 'advanced' : 'repeat')) }}</strong>
                            @if ($result['frozen'])<div class="qs-small">{{ __($w.'recorded_frozen') }}</div>@endif</div>
                    </div>
                @else
                    @if ($l['outcome'])
                        <div class="ql-outcome" data-o="{{ $l['outcome'] }}" role="status" aria-live="polite">
                            <x-filament::icon :icon="$l['outcome'] === 'advance' ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-arrow-path'" />
                            <div>
                                <strong>{{ __($w.'outcome.'.$l['outcome']) }}</strong>
                                <div class="qs-small">{{ $l['outcome'] === 'advance' ? ($l['next_title'] ? __($w.'outcome.next', ['where' => $l['next_title']]) : __('quran::programs.finished')) : __($w.'outcome.repeat_hint', ['grade' => __('quran::programs.grades.'.$l['pass_grade'])]) }}</div>
                            </div>
                        </div>
                    @endif
                    @error('sheet')<div class="qs-banner" data-tone="danger">{{ $message }}</div>@enderror
                    <div style="display: flex; justify-content: flex-end; gap: .5rem; align-items: center">
                        @unless ($l['open'])<span class="qs-muted qs-small">{{ __($w.'opens', ['at' => $l['opens_at']]) }}</span>@endunless
                        <x-filament::button size="lg" icon="heroicon-m-check" wire:click="record" wire:loading.attr="disabled" wire:target="record" :disabled="! $l['open'] || $l['cancelled']"
                            wire:confirm="{{ __($w.'confirm') }}">{{ count($l['students']) > 1 ? __($w.'record_all') : __($w.'record') }}</x-filament::button>
                    </div>
                @endif
            @endif
        @endif
    </div>
</x-filament-panels::page>
