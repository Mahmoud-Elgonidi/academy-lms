@php $l = 'identity::activity.'; $feed = $this->feed; @endphp
<x-filament-panels::page>
    <style>
        .al-filters { display: grid; grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr)); gap: .6rem; padding: .8rem; background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl, .75rem); }
        .al-filters label { display: flex; flex-direction: column; gap: .3rem; font-size: .78rem; color: var(--gray-500); font-weight: 600; }
        .al-box { padding: .6rem 1rem 1rem; background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl, .75rem); }
        .al-more { display: flex; justify-content: center; padding-top: .4rem; }
        .al-stack { display: flex; flex-direction: column; gap: 1rem; }
        .dark .al-filters, .dark .al-box { background: var(--gray-900); border-color: var(--gray-800); }
    </style>
    <div class="al-stack">
        <div class="al-filters">
            <label>
                {{ __($l.'group') }}
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="group">
                        <option value="">{{ __($l.'all_groups') }}</option>
                        @foreach (array_keys(\Modules\SharedKernel\Audit\AuditEntries::GROUPS) as $g)
                            @continue($g === 'platform')
                            <option value="{{ $g }}">{{ __('shared::audit.groups.'.$g) }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            <label>
                {{ __($l.'who') }}
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="who">
                        <option value="">{{ __($l.'who_all') }}</option>
                        @foreach (['user', 'platform', 'system'] as $w)<option value="{{ $w }}">{{ __($l.'who_'.$w) }}</option>@endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
        </div>
        <section class="al-box">
            @include('shared::audit.list', ['entries' => $feed['rows'], 'empty' => __($l.'empty')])
            @if ($feed['more'])
                <div class="al-more"><x-filament::button color="gray" size="sm" wire:click="more" wire:key="more-{{ count($feed['rows']) }}">{{ __($l.'more') }}</x-filament::button></div>
            @endif
        </section>
    </div>
</x-filament-panels::page>
