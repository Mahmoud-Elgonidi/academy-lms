{{-- On the back office, for whoever sees the plan: each limit close to full, or full, with the ways on (an extra pack, a bigger plan). --}}
<div class="pp-banners lms-stagger">
    @foreach ($warnings as $w)
        <div class="pp-banner" data-state="{{ $w['state'] }}" data-limit="{{ $w['limit'] }}" role="{{ $w['state'] === 'full' ? 'alert' : 'status' }}">
            <x-filament::icon :icon="$w['state'] === 'full' ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-information-circle'" class="pp-banner-icon" />
            <span class="pp-banner-text">{{ $w['text'] }}</span>
            <span class="pp-banner-actions">
                @foreach ($w['actions'] as $a)
                    @if ($a['url'])
                        <a href="{{ $a['url'] }}" wire:navigate class="pp-banner-button" @if ($loop->first) data-primary @endif>{{ $a['label'] }}</a>
                    @else
                        <span class="pp-banner-wait"><x-filament::icon icon="heroicon-m-clock" class="pp-banner-wait-icon" />{{ $a['label'] }}</span>
                    @endif
                @endforeach
            </span>
        </div>
    @endforeach
</div>
<style>
    .pp-banners { display: flex; flex-direction: column; gap: .5rem; margin-bottom: 1rem; }
    .pp-banner { display: flex; flex-wrap: wrap; gap: .5rem .75rem; align-items: center; padding: .7rem .95rem; border-radius: var(--radius-xl, .75rem); font-size: .88rem; background: var(--warning-50); color: var(--warning-900); border: 1px solid var(--warning-200); }
    .pp-banner[data-state="full"] { background: var(--danger-50); color: var(--danger-900); border-color: var(--danger-200); border-inline-start-width: 4px; font-weight: 500; }
    .pp-banner-text { flex: 1 1 16rem; }
    .pp-banner-actions { display: flex; flex-wrap: wrap; gap: .4rem; align-items: center; }
    .pp-banner-button { display: inline-flex; align-items: center; padding: .3rem .7rem; border-radius: var(--radius-lg, .5rem); font-size: .8rem; font-weight: 600; color: inherit; border: 1px solid currentColor; background: transparent; white-space: nowrap; }
    .pp-banner-button:hover { background: color-mix(in oklab, currentColor 8%, transparent); }
    .pp-banner-button[data-primary] { background: var(--warning-600); border-color: var(--warning-600); color: white; }
    .pp-banner[data-state="full"] .pp-banner-button[data-primary] { background: var(--danger-600); border-color: var(--danger-600); }
    .pp-banner-wait { display: inline-flex; gap: .3rem; align-items: center; font-size: .8rem; font-weight: 600; }
    .pp-banner-wait-icon { width: 1rem; height: 1rem; }
    .pp-banner-icon { width: 1.2rem; height: 1.2rem; flex: none; }
    .dark .pp-banner { background: color-mix(in oklab, var(--warning-500) 15%, transparent); color: var(--warning-100); border-color: var(--warning-800); }
    .dark .pp-banner[data-state="full"] { background: color-mix(in oklab, var(--danger-500) 15%, transparent); color: var(--danger-100); border-color: var(--danger-800); }
</style>
