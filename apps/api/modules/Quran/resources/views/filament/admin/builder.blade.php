@php
    $w = 'quran::screens.builder.';
    $d = $data;
    $locked = $this->locked();
    $preview = $this->preview;
    $strip = $this->strip;
    $surahs = $this->surahs;
    $parts = $this->parts() ?: 30;
    [$min, $max, $step] = $this->amountRange();
    $noorani = ($d['track'] ?? null) === 'noorani';
    $review = ($d['track'] ?? null) === 'review';
    $icons = ['hifz' => 'heroicon-o-book-open', 'talqin' => 'heroicon-o-speaker-wave', 'tilawa' => 'heroicon-o-musical-note', 'noorani' => 'heroicon-o-language',
        'review' => 'heroicon-o-arrow-path', 'ijaza' => 'heroicon-o-trophy'];
    $err = fn (string $f) => $errors->first('data.'.$f);
@endphp
<x-filament-panels::page>
    @include('quran::filament.parts.styles')
    <style>
        .qb { display: grid; grid-template-columns: minmax(0, 1.25fr) minmax(0, 1fr); gap: 1.25rem; align-items: start; }
        .qb-side { position: sticky; top: 5rem; }
        .qb-tracks { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .5rem; }
        .qb-track { display: flex; flex-direction: column; gap: .3rem; padding: .7rem .75rem; border-radius: .75rem; border: 1px solid var(--gray-200); background: white; cursor: pointer; text-align: start; }
        .qb-track svg { inline-size: 1.2rem; block-size: 1.2rem; color: var(--gray-400); }
        .qb-track strong { font-size: .85rem; color: var(--gray-900); }
        .qb-track[aria-pressed="true"] { border-color: var(--primary-500); box-shadow: 0 0 0 1px var(--primary-500); background: var(--primary-50); }
        .qb-track[aria-pressed="true"] svg { color: var(--primary-600); }
        .qb-track:disabled { cursor: not-allowed; opacity: .6; }
        .qb-field { display: flex; flex-direction: column; gap: .35rem; }
        .qb-field > span { font-size: .8rem; font-weight: 600; color: var(--gray-700); }
        .qb-field small { color: var(--gray-500); font-size: .75rem; }
        .qb-err { color: var(--danger-600); font-size: .78rem; }
        .qb-two { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: .75rem; }
        .qb-scope { display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); gap: .5rem; }
        .qb-slider { display: flex; align-items: center; gap: .75rem; }
        .qb-slider input[type=range] { flex: 1; accent-color: var(--primary-600); }
        .qb-slider output { min-inline-size: 6.5rem; text-align: center; font-weight: 700; font-size: .85rem; padding: .25rem .5rem; border-radius: .5rem; background: var(--primary-50); color: var(--primary-700); }
        .qb-lesson { display: grid; grid-template-columns: 2rem minmax(0, 1fr); gap: .65rem; padding: .7rem 0; border-bottom: 1px solid var(--gray-100); }
        .qb-lesson:last-child { border-bottom: 0; }
        .qb-lesson > b { inline-size: 2rem; block-size: 2rem; border-radius: 999px; display: grid; place-items: center; background: var(--gray-100); font-size: .8rem; color: var(--gray-600); }
        .qb-line { display: flex; gap: .45rem; font-size: .82rem; line-height: 1.5; }
        .qb-line i { flex: none; inline-size: .55rem; block-size: .55rem; border-radius: 999px; margin-top: .45rem; }
        .qb-line[data-part="new"] i { background: var(--primary-500); }
        .qb-line[data-part="near"] i { background: var(--info-500); }
        .qb-line[data-part="far"] i { background: var(--warning-500); }
        .qb-line span { color: var(--gray-500); flex: none; min-inline-size: 6.5rem; }
        .qb-save { position: sticky; bottom: 1rem; z-index: 5; display: flex; justify-content: flex-end; gap: .5rem; }
        @media (max-width: 1023px) { .qb { grid-template-columns: minmax(0, 1fr); } .qb-side { position: static; } }
        @media (max-width: 639px) { .qb-tracks { grid-template-columns: repeat(2, minmax(0, 1fr)); } .qb-two { grid-template-columns: minmax(0, 1fr); } .qb-line span { min-inline-size: 5rem; } }
        .dark .qb-track { background: var(--gray-900); border-color: var(--gray-700); }
        .dark .qb-track strong, .dark .qb-field > span { color: var(--gray-100); }
        .dark .qb-track[aria-pressed="true"] { background: color-mix(in oklab, var(--primary-500) 14%, var(--gray-900)); }
        .dark .qb-slider output { background: color-mix(in oklab, var(--primary-500) 16%, transparent); color: var(--primary-300); }
        .dark .qb-lesson { border-color: var(--gray-800); }
        .dark .qb-lesson > b { background: var(--gray-800); color: var(--gray-300); }
    </style>

    <form wire:submit="save" class="qs">
        @if ($locked)
            <div class="qs-banner" data-tone="info" role="status">
                <x-filament::icon icon="heroicon-o-lock-closed" />
                <span>{{ trans_choice($w.'locked', $this->followers, ['n' => $this->followers]) }}</span>
            </div>
        @endif
        @if (! $this->textAvailable())
            <div class="qs-banner" role="status"><x-filament::icon icon="heroicon-o-exclamation-triangle" /><span>{{ __('quran::screens.programs.no_text') }}</span></div>
        @endif

        <div class="qb">
            <div class="qs lms-stagger">
                {{-- Name and track. --}}
                <section class="qs-panel" aria-labelledby="qb-what">
                    <h2 class="qs-h2" id="qb-what">{{ __($w.'what') }}</h2>
                    <label class="qb-field">
                        <span>{{ __('quran::programs.attributes.name') }}</span>
                        <x-filament::input.wrapper :valid="! $err('name')">
                            <x-filament::input type="text" wire:model.blur="data.name" maxlength="120" :placeholder="__($w.'name_hint')" required />
                        </x-filament::input.wrapper>
                        @if ($err('name'))<span class="qb-err">{{ $err('name') }}</span>@endif
                    </label>
                    <div class="qb-field">
                        <span id="qb-track-label">{{ __('quran::programs.attributes.track') }}</span>
                        <div class="qb-tracks" role="group" aria-labelledby="qb-track-label">
                            @foreach ($this->tracks() as $t)
                                <button type="button" class="qb-track" aria-pressed="{{ ($d['track'] ?? null) === $t ? 'true' : 'false' }}" wire:click="setTrack('{{ $t }}')" @disabled($locked)>
                                    <x-filament::icon :icon="$icons[$t]" />
                                    <strong>{{ __('quran::tracks.names.'.$t) }}</strong>
                                    <small class="qs-muted" style="font-size: .72rem">{{ __($w.'tracks.'.$t) }}</small>
                                </button>
                            @endforeach
                        </div>
                        @if ($err('track'))<span class="qb-err">{{ $err('track') }}</span>@endif
                    </div>
                </section>

                {{-- The scope on the mushaf, and which way it goes. --}}
                <section class="qs-panel" aria-labelledby="qb-scope">
                    <div>
                        <h2 class="qs-h2" id="qb-scope">{{ __($w.'scope') }}</h2>
                        <p class="qs-muted qs-small" style="margin: .15rem 0 0">{{ $noorani ? __($w.'scope_noorani') : __($w.'scope_hint') }}</p>
                    </div>
                    @if ($noorani)
                        <div class="qb-two">
                            <label class="qb-field"><span>{{ __($w.'from_lesson') }}</span>
                                <x-filament::input.wrapper :disabled="$locked"><x-filament::input type="number" min="1" :max="\Modules\Quran\Domain\Programs\Presets::NOORANI_LESSONS" wire:model.live.debounce.400ms="data.scope_from_verse" :disabled="$locked" /></x-filament::input.wrapper>
                            </label>
                            <label class="qb-field"><span>{{ __($w.'to_lesson') }}</span>
                                <x-filament::input.wrapper :disabled="$locked"><x-filament::input type="number" min="1" :max="\Modules\Quran\Domain\Programs\Presets::NOORANI_LESSONS" wire:model.live.debounce.400ms="data.scope_to_verse" :disabled="$locked" /></x-filament::input.wrapper>
                            </label>
                        </div>
                        @if ($err('scope_from_verse'))<span class="qb-err">{{ $err('scope_from_verse') }}</span>@endif
                    @else
                        @include('quran::filament.parts.strip', ['parts' => $parts, 'scope' => $strip, 'legend' => true, 'height' => '1.6rem',
                            'label' => $strip ? __('quran::screens.programs.scope_label', ['from' => $strip['from'], 'to' => $strip['to']]) : null])
                        @unless ($locked)
                            <div class="qs-row" x-data="{ a: {{ $strip['from'] ?? 1 }}, b: {{ $strip['to'] ?? $parts }} }">
                                <span class="qs-small" style="font-weight: 600">{{ __($w.'by_parts') }}</span>
                                <x-filament::input.wrapper style="inline-size: 7.5rem">
                                    <x-filament::input.select x-model.number="a" :aria-label="__($w.'from_part')">
                                        @for ($j = 1; $j <= $parts; $j++)<option value="{{ $j }}">{{ __('quran::hifz.juz', ['juz' => $j]) }}</option>@endfor
                                    </x-filament::input.select>
                                </x-filament::input.wrapper>
                                <span class="qs-muted">{{ __($w.'to') }}</span>
                                <x-filament::input.wrapper style="inline-size: 7.5rem">
                                    <x-filament::input.select x-model.number="b" :aria-label="__($w.'to_part')">
                                        @for ($j = 1; $j <= $parts; $j++)<option value="{{ $j }}">{{ __('quran::hifz.juz', ['juz' => $j]) }}</option>@endfor
                                    </x-filament::input.select>
                                </x-filament::input.wrapper>
                                <x-filament::button type="button" size="sm" color="gray" x-on:click="$wire.scopeParts(a, b)">{{ __($w.'apply') }}</x-filament::button>
                                <x-filament::button type="button" size="sm" color="gray" outlined wire:click="scopeParts(1, {{ $parts }})">{{ __($w.'whole') }}</x-filament::button>
                                <x-filament::button type="button" size="sm" color="gray" outlined wire:click="scopeParts({{ $parts }}, {{ $parts }})">{{ __($w.'last_part') }}</x-filament::button>
                            </div>
                        @endunless
                        <div class="qb-two">
                            @foreach (['from', 'to'] as $edge)
                                <div class="qb-field">
                                    <span>{{ __($w.'edge_'.$edge) }}</span>
                                    <div class="qb-scope">
                                        <x-filament::input.wrapper :disabled="$locked">
                                            <x-filament::input.select wire:model.live="data.scope_{{ $edge }}_surah" :disabled="$locked" :aria-label="__($w.'surah')">
                                                @foreach ($surahs as $s)<option value="{{ $s['number'] }}">{{ $s['number'] }}. {{ $s['name'] }}</option>@endforeach
                                            </x-filament::input.select>
                                        </x-filament::input.wrapper>
                                        <x-filament::input.wrapper :disabled="$locked">
                                            <x-filament::input type="number" min="1" :max="$surahs[(int) ($d['scope_'.$edge.'_surah'] ?? 1)]['verses'] ?? 300" wire:model.live.debounce.400ms="data.scope_{{ $edge }}_verse" :disabled="$locked" :aria-label="__($w.'verse')" />
                                        </x-filament::input.wrapper>
                                    </div>
                                    @if ($err('scope_'.$edge.'_surah'))<span class="qb-err">{{ $err('scope_'.$edge.'_surah') }}</span>@endif
                                </div>
                            @endforeach
                        </div>
                        <div class="qb-field">
                            <span id="qb-dir">{{ __($w.'direction') }}</span>
                            <div class="qs-seg" role="group" aria-labelledby="qb-dir">
                                @foreach (['backward', 'forward'] as $dir)
                                    <button type="button" aria-pressed="{{ ($d['direction'] ?? null) === $dir ? 'true' : 'false' }}" wire:click="$set('data.direction', '{{ $dir }}')" @disabled($locked)>{{ __('quran::programs.directions.'.$dir) }}</button>
                                @endforeach
                            </div>
                            <small>{{ __($w.'direction_hint.'.($d['direction'] ?? 'forward')) }}</small>
                        </div>
                    @endif
                </section>

                {{-- Each lesson's amount and reviews. --}}
                <section class="qs-panel" aria-labelledby="qb-pace">
                    <div>
                        <h2 class="qs-h2" id="qb-pace">{{ __($w.'pace') }}</h2>
                        <p class="qs-muted qs-small" style="margin: .15rem 0 0">{{ __($w.'pace_hint') }}</p>
                    </div>
                    @unless ($noorani)
                        <label class="qb-field">
                            <span>{{ __($w.'unit') }}</span>
                            <x-filament::input.wrapper :disabled="$locked">
                                <x-filament::input.select wire:model.live="data.unit" :disabled="$locked">
                                    @foreach (\Modules\Quran\Domain\Programs\ProgramRules::UNITS as $u)<option value="{{ $u }}">{{ __('quran::programs.units.'.$u) }}</option>@endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                            @if ($err('unit'))<span class="qb-err">{{ $err('unit') }}</span>@endif
                        </label>
                    @endunless
                    @unless ($review || $noorani)
                        <div class="qb-field">
                            <span>{{ __('quran::programs.attributes.new_amount') }}</span>
                            <div class="qb-slider">
                                <input type="range" min="{{ $min }}" max="{{ $max }}" step="{{ $step }}" wire:model.live.debounce.250ms="data.new_amount" aria-label="{{ __('quran::programs.attributes.new_amount') }}">
                                <output>{{ $this->amountLabel() }}</output>
                            </div>
                            @if ($err('new_amount'))<span class="qb-err">{{ $err('new_amount') }}</span>@endif
                        </div>
                    @endunless
                    @unless ($noorani)
                        @unless ($review)
                            <div class="qb-field">
                                <span>{{ __('quran::programs.attributes.near_portions') }}</span>
                                <div class="qb-slider">
                                    <input type="range" min="0" max="{{ \Modules\Quran\Domain\Programs\ProgramRules::NEAR_MAX }}" step="1" wire:model.live.debounce.250ms="data.near_portions" aria-label="{{ __('quran::programs.attributes.near_portions') }}">
                                    <output>{{ trans_choice('quran::screens.pace.near_short', (int) $d['near_portions'], ['n' => (int) $d['near_portions']]) }}</output>
                                </div>
                                <small>{{ __($w.'near_hint') }}</small>
                                @if ($err('near_portions'))<span class="qb-err">{{ $err('near_portions') }}</span>@endif
                            </div>
                        @endunless
                        <div class="qb-field">
                            <span>{{ __('quran::programs.attributes.far_pages') }}</span>
                            <div class="qb-slider">
                                <input type="range" min="{{ $review ? 1 : 0 }}" max="{{ \Modules\Quran\Domain\Programs\ProgramRules::FAR_MAX }}" step="1" wire:model.live.debounce.250ms="data.far_pages" aria-label="{{ __('quran::programs.attributes.far_pages') }}">
                                <output>{{ trans_choice('quran::screens.pace.far_short', (int) $d['far_pages'], ['n' => (int) $d['far_pages']]) }}</output>
                            </div>
                            <small>{{ __($w.'far_hint') }}</small>
                            @if ($err('far_pages'))<span class="qb-err">{{ $err('far_pages') }}</span>@endif
                        </div>
                    @endunless
                </section>

                {{-- Passing, exams and the certificate. --}}
                <section class="qs-panel" aria-labelledby="qb-pass">
                    <h2 class="qs-h2" id="qb-pass">{{ __($w.'passing') }}</h2>
                    <div class="qb-field">
                        <span id="qb-grade">{{ __($w.'pass_grade') }}</span>
                        <div class="qs-seg" role="group" aria-labelledby="qb-grade">
                            @foreach ($this->grades() as $g)
                                <button type="button" aria-pressed="{{ ($d['pass_grade'] ?? null) === $g ? 'true' : 'false' }}" wire:click="$set('data.pass_grade', '{{ $g }}')">{{ __('quran::programs.grades.'.$g) }}</button>
                            @endforeach
                        </div>
                        <small>{{ __($w.'pass_grade_hint') }}</small>
                    </div>
                    @unless ($noorani || $review)
                        <div class="qb-field">
                            <span id="qb-stops">{{ __($w.'exam_stops') }}</span>
                            <div class="qs-seg" role="group" aria-labelledby="qb-stops">
                                @foreach (\Modules\Quran\Domain\Programs\ProgramRules::EXAM_STOPS as $st)
                                    <button type="button" aria-pressed="{{ ($d['exam_stops'] ?? 'none') === $st ? 'true' : 'false' }}" wire:click="$set('data.exam_stops', '{{ $st }}')">{{ __('quran::programs.exam_stops.'.$st) }}</button>
                                @endforeach
                            </div>
                            @if ($err('exam_stops'))<span class="qb-err">{{ $err('exam_stops') }}</span>@endif
                        </div>
                    @endunless
                    <label class="qs-row" style="cursor: pointer">
                        <x-filament::input.checkbox wire:model.live="data.counts_for_certificate" />
                        <span class="qs-small" style="font-weight: 600">{{ __($w.'certificate') }}</span>
                    </label>
                </section>
            </div>

            {{-- A typical week, worked out on the server as the fields change. --}}
            <aside class="qb-side" aria-labelledby="qb-week">
                <section class="qs-panel">
                    <div class="qs-between">
                        <div>
                            <h2 class="qs-h2" id="qb-week">{{ __($w.'week') }}</h2>
                            <p class="qs-muted qs-small" style="margin: .15rem 0 0">{{ __($w.'week_hint') }}</p>
                        </div>
                        <div wire:loading.delay class="qs-muted qs-small"><x-filament::loading-indicator style="inline-size: 1.1rem; block-size: 1.1rem" /></div>
                    </div>
                    <div class="qs-row">
                        <div class="qs-seg" role="group" aria-label="{{ __($w.'per_week') }}">
                            @foreach ([2, 3, 4, 5] as $n)
                                <button type="button" aria-pressed="{{ $perWeek === $n ? 'true' : 'false' }}" wire:click="$set('perWeek', {{ $n }})">{{ trans_choice($w.'lessons_a_week', $n, ['n' => $n]) }}</button>
                            @endforeach
                        </div>
                        @unless ($noorani)
                            <x-filament::input.wrapper style="min-inline-size: 11rem">
                                <x-filament::input.select wire:model.live="known" :aria-label="__($w.'known')">
                                    <option value="none">{{ __($w.'known_none') }}</option>
                                    <option value="juz30">{{ __($w.'known_last') }}</option>
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                        @endunless
                    </div>
                    @if ($preview['errors'] !== [])
                        <div class="qs-banner" data-tone="danger" role="status">
                            <x-filament::icon icon="heroicon-o-exclamation-circle" />
                            <span>{{ __($w.'preview_blocked') }} {{ __('quran::programs.errors.'.array_values($preview['errors'])[0]) }}</span>
                        </div>
                    @else
                        @if ($preview['total'])
                            <div class="qs-row qs-small">
                                <span class="qs-pill" data-tone="primary">{{ trans_choice($w.'total', $preview['total'], ['n' => number_format($preview['total'])]) }}</span>
                                <span class="qs-pill">{{ trans_choice($w.'weeks', (int) ceil($preview['total'] / max(1, $perWeek)), ['n' => number_format((int) ceil($preview['total'] / max(1, $perWeek)))]) }}</span>
                            </div>
                        @endif
                        <div class="lms-stagger">
                            @foreach ($preview['lessons'] as $lesson)
                                <div class="qb-lesson" wire:key="pv-{{ $lesson['number'] }}">
                                    <b>{{ $lesson['number'] }}</b>
                                    <div style="display: flex; flex-direction: column; gap: .2rem; min-width: 0">
                                        @if ($lesson['lesson'])
                                            <div class="qb-line" data-part="new"><i></i><span>{{ __('quran::programs.parts.new') }}</span><strong>{{ $lesson['lesson']['title'] }}</strong></div>
                                        @endif
                                        @foreach (['new', 'near', 'far'] as $part)
                                            @if ($lesson[$part])
                                                <div class="qb-line" data-part="{{ $part }}">
                                                    <i></i><span>{{ $lesson[$part]['label'] }}</span>
                                                    <strong style="font-weight: {{ $part === 'new' ? 700 : 500 }}">{{ collect($lesson[$part]['ranges'])->pluck('title')->implode('، ') }}</strong>
                                                </div>
                                            @endif
                                        @endforeach
                                        @foreach ($lesson['stops'] as $stop)
                                            <span class="qs-pill" data-tone="warning" style="align-self: flex-start">{{ __($w.'exam_after', ['stop' => $stop['label']]) }}</span>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                            @if (count($preview['lessons']) < $perWeek && $preview['lessons'] !== [])
                                <p class="qs-muted qs-small" style="margin: .4rem 0 0">{{ __('quran::programs.finished') }}</p>
                            @endif
                        </div>
                    @endif
                </section>
            </aside>
        </div>

        <div class="qb-save">
            <x-filament::button tag="a" color="gray" :href="\Modules\Quran\Filament\Pages\QuranPrograms::getUrl()" wire:navigate>{{ __($w.'back') }}</x-filament::button>
            <x-filament::button type="submit" icon="heroicon-m-check" wire:loading.attr="disabled" wire:target="save">{{ $program ? __($w.'save') : __($w.'create') }}</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
