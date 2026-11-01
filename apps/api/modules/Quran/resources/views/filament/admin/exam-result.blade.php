@php
    $v = $this->result;
    $e = $v['exam'];
    $w = 'quran::screens.result.';
@endphp
<x-filament-panels::page>
    @include('quran::filament.parts.styles')
    @include('quran::filament.parts.counter-styles')
    <div class="qs">
        <section class="qs-panel">
            <div class="qs-between">
                <div>
                    <h2 class="qs-h2">{{ $e['student'] }}</h2>
                    <p class="qs-muted qs-small" style="margin: .15rem 0 0">{{ $e['program']['name'] }}</p>
                </div>
                <span class="qs-tag">{{ $e['stop']['label'] }}</span>
            </div>
            <div class="qs-meta">
                @if ($e['lesson'])<span><x-filament::icon icon="heroicon-m-calendar-days" />{{ $e['lesson']['starts_at']['label'] }}</span>@endif
                <span><x-filament::icon icon="heroicon-m-document-text" />{{ __($w.'pages', ['n' => \Modules\Quran\Support\Amounts::number(round($v['pages'], 1))]) }}</span>
                <span><x-filament::icon icon="heroicon-m-flag" />{{ __($w.'pass_at', ['grade' => __('quran::programs.grades.'.$v['pass_grade'])]) }}</span>
            </div>
            @if (! $v['recorded'] && $v['class_url'])
                <div><x-filament::button tag="a" :href="$v['class_url']" target="_blank" color="gray" icon="heroicon-m-video-camera" size="sm">{{ __($w.'open_class') }}</x-filament::button></div>
            @endif
        </section>

        @if ($v['recorded'])
            <section class="qs-panel" aria-live="polite">
                <div class="qs-row">
                    <span class="qs-pill" data-tone="{{ $e['result']['passed'] ? 'success' : 'danger' }}" style="font-size: .95rem; padding: .3rem .8rem">{{ $e['status_label'] }} · {{ $e['result']['grade_label'] }}</span>
                    @foreach ($e['result']['mistakes'] as $m)<span class="qs-muted qs-small">{{ $m['label'] }}: {{ $m['count'] }}</span>@endforeach
                </div>
                @if ($e['result']['note'])<p style="margin: 0">{{ $e['result']['note'] }}</p>@endif
                @if ($e['result']['retake_on'])<p class="qs-muted qs-small" style="margin: 0">{{ __($w.'retake_on', ['day' => \Carbon\CarbonImmutable::parse($e['result']['retake_on'])->locale(app()->getLocale())->translatedFormat('l j F')]) }}</p>@endif
            </section>
        @else
            <form wire:submit="record" class="qs">
                <section class="qs-panel" aria-labelledby="qr-mistakes">
                    <div>
                        <h2 class="qs-h2" id="qr-mistakes">{{ __($w.'mistakes') }}</h2>
                        <p class="qs-muted qs-small" style="margin: .15rem 0 0">{{ __($w.'mistakes_hint') }}</p>
                    </div>
                    <div class="qc-counters">
                        @foreach ($v['types'] as $t)
                            <div class="qc-counter" wire:key="c-{{ $t['key'] }}">
                                <span class="qc-label">{{ $t['label'] }} <small>×{{ \Modules\Quran\Support\Amounts::number($t['weight']) }}</small></span>
                                <div class="qc-ctrl">
                                    <button type="button" wire:click="bump('{{ $t['key'] }}', -1)" aria-label="{{ __($w.'less', ['kind' => $t['label']]) }}">−</button>
                                    <output aria-live="polite">{{ $counts[$t['key']] ?? 0 }}</output>
                                    <button type="button" wire:click="bump('{{ $t['key'] }}', 1)" aria-label="{{ __($w.'more', ['kind' => $t['label']]) }}">+</button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="qs-panel" aria-labelledby="qr-grade">
                    <div class="qs-between">
                        <h2 class="qs-h2" id="qr-grade">{{ __($w.'grade') }}</h2>
                        <span class="qs-pill" data-tone="{{ $v['passed'] ? 'success' : 'danger' }}" style="font-size: .9rem; padding: .3rem .8rem">
                            {{ __('quran::programs.grades.'.$v['grade']) }} · {{ $v['passed'] ? __($w.'will_pass') : __($w.'will_fail') }}
                        </span>
                    </div>
                    <p class="qs-muted qs-small" style="margin: 0">{{ __($w.'computed', ['grade' => __('quran::programs.grades.'.$v['computed']['grade']), 'penalty' => \Modules\Quran\Support\Amounts::number(round($v['computed']['penalty'], 2))]) }}</p>
                    <div class="qb-two" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr)); gap: .75rem">
                        <label class="qs" style="gap: .35rem">
                            <span class="qs-small" style="font-weight: 600">{{ __($w.'own_grade') }}</span>
                            <x-filament::input.wrapper :valid="! $errors->has('grade')">
                                <x-filament::input.select wire:model.live="grade">
                                    <option value="">{{ __($w.'as_computed') }}</option>
                                    @foreach ($v['grades'] as $g)<option value="{{ $g }}">{{ __('quran::programs.grades.'.$g) }}</option>@endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                            @error('grade')<span class="qs-small" style="color: var(--danger-600)">{{ $message }}</span>@enderror
                        </label>
                        @unless ($v['passed'])
                            <label class="qs" style="gap: .35rem">
                                <span class="qs-small" style="font-weight: 600">{{ __('quran::exams.attributes.retake_on') }}</span>
                                <x-filament::input.wrapper :valid="! $errors->has('retakeOn')">
                                    <x-filament::input type="date" wire:model.live="retakeOn" :min="now()->addDay()->toDateString()" :max="$v['retake_max']" />
                                </x-filament::input.wrapper>
                                @error('retakeOn')<span class="qs-small" style="color: var(--danger-600)">{{ $message }}</span>@enderror
                            </label>
                        @endunless
                    </div>
                    <label class="qs" style="gap: .35rem">
                        <span class="qs-small" style="font-weight: 600">{{ $v['passed'] ? __($w.'note') : __($w.'note_fail') }}</span>
                        <textarea wire:model.blur="note" rows="3" maxlength="1000" class="qr-note" placeholder="{{ $v['overridden'] ? __($w.'note_override') : '' }}"></textarea>
                        @error('note')<span class="qs-small" style="color: var(--danger-600)">{{ $message }}</span>@enderror
                    </label>
                </section>

                <div style="display: flex; justify-content: flex-end">
                    <x-filament::button type="submit" size="lg" icon="heroicon-m-check" :color="$v['passed'] ? 'success' : 'danger'" wire:loading.attr="disabled" wire:target="record"
                        wire:confirm="{{ __($w.'confirm') }}">{{ $v['passed'] ? __($w.'submit_pass') : __($w.'submit_fail') }}</x-filament::button>
                </div>
            </form>
        @endif
    </div>
</x-filament-panels::page>
