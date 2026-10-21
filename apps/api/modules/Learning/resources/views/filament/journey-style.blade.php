{{-- The journey's style for the academy's students (on the games' settings page). --}}
<div style="border-top: 1px solid var(--gray-200); padding-top: .8rem">
    @if ($allowed)
        <form wire:submit="save" style="display: flex; flex-direction: column; gap: .7rem">
            <h3 id="journey-style">{{ __('learning::map.settings.title') }}</h3>
            <small>{{ __('learning::map.settings.hint') }}</small>
            <div role="radiogroup" aria-labelledby="journey-style" style="display: flex; flex-direction: column; gap: .45rem">
                @foreach ($styles as $s)
                    <label class="gs-row" style="display: flex; flex-direction: row; align-items: flex-start; gap: .5rem; font-size: .9rem">
                        <input type="radio" name="journey_style" value="{{ $s }}" wire:model="style" style="margin-top: .2rem">
                        <span>{{ __('learning::map.styles.'.$s) }}</span>
                    </label>
                @endforeach
            </div>
            @error('journey_style')<small style="color: var(--danger-600)">{{ $message }}</small>@enderror
            <div><x-filament::button type="submit">{{ __('learning::map.settings.save') }}</x-filament::button></div>
        </form>
        @if ($hifz)
            <form wire:submit="saveHifz" style="display: flex; flex-direction: column; gap: .5rem; margin-top: 1rem">
                <h3>{{ __('learning::hifz.settings.title') }}</h3>
                <small>{{ __('learning::hifz.settings.hint') }}</small>
                <label style="display: flex; align-items: center; gap: .6rem; font-size: .9rem">
                    <span>{{ __('learning::hifz.settings.daily') }}</span>
                    <x-filament::input.wrapper style="inline-size: 6rem"><x-filament::input type="number" min="1" :max="$max" wire:model="hifzPages" /></x-filament::input.wrapper>
                </label>
                @error('hifzPages')<small style="color: var(--danger-600)">{{ $message }}</small>@enderror
                <div><x-filament::button type="submit">{{ __('learning::map.settings.save') }}</x-filament::button></div>
            </form>
        @endif
    @endif
</div>
