{{-- On the back office, for whoever sees the plan: a limit close to full, or full. --}}
<a href="{{ $url }}" class="pp-banner" data-state="{{ $state }}" wire:navigate>
    <x-filament::icon :icon="$state === 'full' ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-information-circle'" class="pp-banner-icon" />
    <span>{{ $text }}</span>
    <strong>{{ __('platform::plans.banner.action') }}</strong>
</a>
<style>
    .pp-banner { display: flex; flex-wrap: wrap; gap: .5rem .75rem; align-items: center; margin-bottom: 1rem; padding: .7rem .95rem; border-radius: var(--radius-xl, .75rem); font-size: .88rem; background: var(--warning-50); color: var(--warning-900); border: 1px solid var(--warning-200); }
    .pp-banner[data-state="full"] { background: var(--danger-50); color: var(--danger-900); border-color: var(--danger-200); }
    .pp-banner span { flex: 1 1 16rem; }
    .pp-banner strong { text-decoration: underline; text-underline-offset: 3px; }
    .pp-banner-icon { width: 1.2rem; height: 1.2rem; flex: none; }
    .dark .pp-banner { background: color-mix(in oklab, var(--warning-500) 15%, transparent); color: var(--warning-100); border-color: var(--warning-800); }
    .dark .pp-banner[data-state="full"] { background: color-mix(in oklab, var(--danger-500) 15%, transparent); color: var(--danger-100); border-color: var(--danger-800); }
</style>
