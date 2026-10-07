@php $l = 'platform::console.audit.'; $feed = $this->feed; @endphp
<x-filament-panels::page>
    @include('platform::console.styles')
    <div class="pc-stack">
        <div class="pc-base pc-filters">
            <label>
                {{ __($l.'filters.academy') }}
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="academy">
                        <option value="">{{ __($l.'filters.all_academies') }}</option>
                        <option value="platform">{{ __($l.'filters.platform_only') }}</option>
                        @foreach ($this->academies as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            <label>
                {{ __($l.'filters.group') }}
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="group">
                        <option value="">{{ __($l.'filters.all_groups') }}</option>
                        @foreach (array_keys(\Modules\SharedKernel\Audit\AuditEntries::GROUPS) as $g)<option value="{{ $g }}">{{ __('shared::audit.groups.'.$g) }}</option>@endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            <label>
                {{ __($l.'filters.who') }}
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="who">
                        <option value="">{{ __($l.'filters.who_all') }}</option>
                        @foreach (['platform', 'user', 'system'] as $w)<option value="{{ $w }}">{{ __($l.'filters.who_'.$w) }}</option>@endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
        </div>

        <section class="pc-base pc-box">
            @include('shared::audit.list', ['entries' => $feed['rows'], 'empty' => __($l.'empty'), 'platformLabel' => __($l.'platform')])
            @if ($feed['more'])
                <div class="pc-more"><x-filament::button color="gray" size="sm" wire:click="more" wire:key="more-{{ count($feed['rows']) }}">{{ __($l.'more') }}</x-filament::button></div>
            @endif
        </section>
    </div>
</x-filament-panels::page>
