@php $count = count($names); @endphp
<a href="{{ $url }}" wire:navigate class="pc-banner" role="status">
    <x-filament::icon icon="heroicon-o-shield-check" class="pc-banner-icon" />
    <span>{{ trans_choice('people::consent.family.banner', $count, ['names' => implode('، ', $names)]) }}</span>
    <strong>{{ __('people::consent.family.banner_action') }}</strong>
</a>
<style>
    .pc-banner { display: flex; align-items: center; gap: .65rem; flex-wrap: wrap; margin-bottom: 1rem; padding: .8rem 1rem; border-radius: var(--radius-xl, .75rem); background: var(--warning-50); color: var(--warning-800); border: 1px solid var(--warning-200); font-size: .92rem; text-decoration: none; }
    .pc-banner strong { margin-inline-start: auto; color: var(--warning-900); text-decoration: underline; text-underline-offset: 3px; }
    .pc-banner-icon { width: 1.3rem; height: 1.3rem; flex: none; }
</style>
