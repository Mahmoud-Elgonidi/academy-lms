@php $b = $this->board; $h = 'learning::hifz.board.'; @endphp
<x-filament-panels::page>
    <style>
        .lh-tools { display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; }
        .lh-tools > * { min-width: 14rem; }
        .lh-wrap { overflow-x: auto; border: 1px solid var(--gray-200); border-radius: .75rem; background: white; }
        .lh-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
        .lh-table th { text-align: start; font-weight: 600; color: var(--gray-500); padding: .6rem .75rem; border-bottom: 1px solid var(--gray-200); white-space: nowrap; }
        .lh-table td { padding: .6rem .75rem; border-bottom: 1px solid var(--gray-100); white-space: nowrap; }
        .lh-table tr:last-child td { border-bottom: 0; }
        .lh-table a { color: var(--primary-600); font-weight: 600; }
        .lh-num { text-align: center; font-variant-numeric: tabular-nums; }
        .lh-pill { display: inline-block; min-width: 1.75rem; padding: 0 .45rem; border-radius: 999px; font-weight: 600; }
        .lh-pill[data-tone="bad"] { background: var(--danger-50); color: var(--danger-700); }
        .lh-pill[data-tone="warn"] { background: var(--warning-50); color: var(--warning-700); }
        .lh-muted { color: var(--gray-400); }
        .dark .lh-wrap { background: var(--gray-900); border-color: var(--gray-700); }
        .dark .lh-table th, .dark .lh-table td { border-color: var(--gray-800); }
    </style>
    @if (! $b['available'])
        <x-filament::section>{{ __($h.'no_text') }}</x-filament::section>
    @else
        <div class="lh-tools">
            <x-filament::input.wrapper>
                <x-filament::input type="search" wire:model.live.debounce.400ms="search" :placeholder="__($h.'search')" />
            </x-filament::input.wrapper>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="teacher">
                    <option value="">{{ __($h.'all_teachers') }}</option>
                    @foreach ($b['teachers'] as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
        @if ($b['rows'] === [])
            <x-filament::section>{{ __($h.'empty') }}</x-filament::section>
        @else
            <div class="lh-wrap">
                <table class="lh-table">
                    <thead>
                        <tr>
                            <th>{{ __($h.'columns.student') }}</th>
                            <th>{{ __($h.'columns.teacher') }}</th>
                            <th>{{ __($h.'columns.subject') }}</th>
                            <th class="lh-num">{{ __($h.'columns.memorized') }}</th>
                            <th class="lh-num">{{ __($h.'columns.learning') }}</th>
                            <th class="lh-num">{{ __($h.'columns.weak') }}</th>
                            <th class="lh-num">{{ __($h.'columns.due') }}</th>
                            <th>{{ __($h.'columns.last') }}</th>
                            <th class="lh-num">{{ __($h.'columns.words') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($b['rows'] as $r)
                            <tr wire:key="hifz-{{ $r['enrollment'] }}">
                                <td><a href="{{ $r['url'] }}">{{ $r['student'] }}</a></td>
                                <td>{{ $r['teacher'] }}</td>
                                <td>{{ $r['subject'] }}</td>
                                <td class="lh-num">{{ $r['memorized'] }}</td>
                                <td class="lh-num">{{ $r['learning'] }}</td>
                                <td class="lh-num"><span class="lh-pill" @if ($r['weak'] > 0) data-tone="bad" @endif>{{ $r['weak'] }}</span></td>
                                <td class="lh-num"><span class="lh-pill" @if ($r['due'] > 0) data-tone="warn" @endif>{{ $r['due'] }}</span></td>
                                <td>@if ($r['last'] !== null){{ $r['last'] }}@else<span class="lh-muted">{{ __($h.'never') }}</span>@endif</td>
                                <td class="lh-num">{{ $r['words'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
</x-filament-panels::page>
