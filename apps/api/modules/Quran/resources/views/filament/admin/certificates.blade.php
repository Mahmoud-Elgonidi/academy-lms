@php
    $x = $this->certificates;
    $w = 'quran::screens.certificates.';
@endphp
<x-filament-panels::page>
    @include('quran::filament.parts.styles')
    <div class="qs">
        @unless ($x['enabled'])
            <div class="qs-banner" data-tone="info" role="status">
                <x-filament::icon icon="heroicon-o-information-circle" />
                <span>{{ __($w.'off') }}@if (\Modules\Quran\Filament\Pages\QuranSettingsPage::canAccess()) <a class="qs-link" href="{{ \Modules\Quran\Filament\Pages\QuranSettingsPage::getUrl() }}" wire:navigate>{{ __('quran::screens.exams.off_settings') }}</a>@endif</span>
            </div>
        @endunless
        <div class="qs-filters" role="search" aria-label="{{ __($w.'filters.label') }}">
            <label>
                <span>{{ __($w.'filters.search') }}</span>
                <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                    <x-filament::input type="search" wire:model.live.debounce.400ms="search" :placeholder="__($w.'filters.search_hint')" />
                </x-filament::input.wrapper>
            </label>
            <div class="qs-seg" role="group" aria-label="{{ __($w.'filters.status') }}">
                @foreach (['' => 'all', 'valid' => 'valid', 'revoked' => 'revoked'] as $v => $k)
                    <button type="button" aria-pressed="{{ $status === $v ? 'true' : 'false' }}" wire:click="$set('status', '{{ $v }}')">{{ __($w.'filters.'.$k) }}</button>
                @endforeach
            </div>
        </div>

        @if ($x['rows'] === [])
            <section class="qs-empty">
                <span class="qs-empty-ico"><x-filament::icon icon="heroicon-o-trophy" /></span>
                <strong>{{ __($x['counts']['all'] === 0 ? $w.'empty.title' : $w.'none') }}</strong>
                @if ($x['counts']['all'] === 0)<p>{{ __($w.'empty.hint') }}</p>@endif
            </section>
        @else
            <div class="qs-table-wrap">
                <table class="qs-table">
                    <thead>
                        <tr>
                            <th>{{ __($w.'columns.number') }}</th>
                            <th>{{ __($w.'columns.student') }}</th>
                            <th>{{ __($w.'columns.portion') }}</th>
                            <th>{{ __($w.'columns.grade') }}</th>
                            <th>{{ __($w.'columns.examiner') }}</th>
                            <th>{{ __($w.'columns.issued') }}</th>
                            <th><span class="sr-only">{{ __($w.'columns.actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($x['rows'] as $c)
                            <tr wire:key="qc-{{ $c['id'] }}" @if ($c['revoked']) style="opacity: .7" @endif>
                                <td><span class="qs-tag" dir="ltr">{{ $c['number'] }}</span></td>
                                <td><span class="qs-name">{{ $c['student'] }}</span><div class="qs-muted qs-small">{{ $c['program'] }}</div></td>
                                <td>{{ $c['portion'] }}</td>
                                <td><span class="qs-pill" data-tone="success">{{ $c['grade_label'] }}</span></td>
                                <td>{{ $c['examiner'] }}</td>
                                <td class="qs-num">
                                    {{ $c['day'] }}
                                    @if ($c['revoked'])<div><span class="qs-pill" data-tone="danger">{{ __($w.'revoked_on', ['day' => $c['revoked_day']]) }}</span></div>@endif
                                </td>
                                <td style="text-align: end; white-space: nowrap">
                                    {{ ($this->openAction)(['certificate' => $c['id']]) }}
                                    {{ ($this->revokeAction)(['certificate' => $c['id']]) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-filament-panels::page>
