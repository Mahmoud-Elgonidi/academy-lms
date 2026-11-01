@php
    $b = $this->board;
    $w = 'quran::screens.students.';
    $tones = ['on_track' => 'success', 'behind' => 'danger', 'new' => 'info', 'frozen' => 'warning', 'finished' => 'primary', 'no_plan' => 'gray'];
    $filtered = $search !== '' || $teacher !== '' || $pace !== '';
@endphp
<x-filament-panels::page>
    @include('quran::filament.parts.styles')
    <div class="qs">
        <div class="qs-filters" role="search" aria-label="{{ __($w.'filters.label') }}">
            <label>
                <span>{{ __($w.'filters.search') }}</span>
                <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                    <x-filament::input type="search" wire:model.live.debounce.400ms="search" :placeholder="__($w.'filters.search_hint')" />
                </x-filament::input.wrapper>
            </label>
            <label>
                <span>{{ __($w.'filters.teacher') }}</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="teacher">
                        <option value="">{{ __($w.'filters.all_teachers') }}</option>
                        @foreach ($b['teachers'] as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            <label>
                <span>{{ __($w.'filters.pace') }}</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="pace">
                        <option value="">{{ __($w.'filters.all') }}</option>
                        @foreach ($tones as $k => $tone)<option value="{{ $k }}">{{ __('quran::screens.pace_status.'.$k) }}</option>@endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            @if ($filtered)
                <x-filament::button color="gray" outlined size="sm" wire:click="clearFilters" icon="heroicon-m-x-mark">{{ __($w.'filters.clear') }}</x-filament::button>
            @endif
        </div>

        @if ($b['rows'] === [])
            <section class="qs-empty">
                <span class="qs-empty-ico"><x-filament::icon icon="heroicon-o-user-group" /></span>
                <strong>{{ __($filtered ? $w.'none.title' : $w.'empty.title') }}</strong>
                <p>{{ __($filtered ? $w.'none.hint' : $w.'empty.hint') }}</p>
            </section>
        @else
            <div class="qs-table-wrap">
                <table class="qs-table">
                    <thead>
                        <tr>
                            <th>{{ __($w.'columns.student') }}</th>
                            <th style="min-inline-size: 14rem">{{ __($w.'columns.where') }}</th>
                            <th>{{ __($w.'columns.pace') }}</th>
                            <th>{{ __($w.'columns.exam') }}</th>
                            <th><span class="sr-only">{{ __($w.'columns.actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($b['rows'] as $r)
                            @php $plan = $r['plan']; $status = $plan['pace_status'] ?? 'no_plan'; @endphp
                            <tr wire:key="qs-{{ $r['enrollment'] }}">
                                <td>
                                    @if ($r['url'])<a class="qs-name" href="{{ $r['url'] }}" wire:navigate>{{ $r['student'] }}</a>@else<span class="qs-name">{{ $r['student'] }}</span>@endif
                                    <div class="qs-muted qs-small">{{ $r['teacher'] }} · {{ $r['subject'] }}</div>
                                    @if ($plan)<div class="qs-small" style="margin-top: .2rem"><span class="qs-tag">{{ $plan['program'] }}</span>@if ($plan['group']) <span class="qs-pill" data-tone="info">{{ __($w.'group') }}</span>@endif</div>@endif
                                </td>
                                <td>
                                    @include('quran::filament.parts.strip', ['parts' => $b['parts'], 'shares' => $r['shares'], 'at' => $plan['juz'] ?? null, 'height' => '1.15rem',
                                        'label' => __($w.'strip_label', ['name' => $r['student'], 'pages' => $r['memorized']])])
                                    <div class="qs-muted qs-small" style="margin-top: .3rem">
                                        @if ($plan === null)
                                            {{ trans_choice($w.'memorized', $r['memorized'], ['n' => $r['memorized']]) }}
                                        @elseif ($plan['finished'])
                                            {{ __('quran::programs.finished') }}
                                        @else
                                            {{ __($w.'at', ['where' => $plan['position'] ?? '—', 'progress' => $plan['progress']]) }}
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <span class="qs-pill" data-tone="{{ $tones[$status] }}"><span class="qs-dot"></span>{{ __('quran::screens.pace_status.'.$status) }}</span>
                                    @if ($plan)
                                        <div class="qs-muted qs-small" style="margin-top: .3rem">{{ $plan['pace'] }}@if ($plan['own_pace']) · {{ __($w.'own_pace') }}@endif</div>
                                        @if ($plan['recorded'] > 0)<div class="qs-muted qs-small">{{ __($w.'passed', ['passed' => $plan['advanced'], 'of' => $plan['recorded']]) }}</div>@endif
                                        @if ($plan['frozen_label'])<div class="qs-small" style="color: var(--warning-700)">{{ $plan['frozen_label'] }}</div>@endif
                                    @endif
                                </td>
                                <td>
                                    @if ($r['exam'])
                                        <div class="qs-small" style="font-weight: 600">{{ $r['exam']['label'] }}</div>
                                        <span class="qs-pill" data-tone="{{ $r['exam']['status'] === 'booked' ? 'info' : 'warning' }}">{{ $r['exam']['when'] ?? $r['exam']['status_label'] }}</span>
                                    @else
                                        <span class="qs-muted qs-small">—</span>
                                    @endif
                                </td>
                                <td style="text-align: end; white-space: nowrap">
                                    @if ($b['manage'] && $plan === null && ! $r['in_group'])
                                        {{ ($this->startPlanAction)(['enrollment' => $r['enrollment']]) }}
                                    @elseif ($b['manage'] && $plan !== null)
                                        {{ ($this->adjustPlanAction)(['enrollment' => $r['enrollment']]) }}
                                    @elseif ($r['in_group'])
                                        <span class="qs-muted qs-small">{{ __($w.'in_group') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-filament-panels::page>
