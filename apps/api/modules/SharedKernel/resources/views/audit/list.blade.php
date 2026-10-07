{{--
    Audit entries as a list (AuditEntries::describe rows), shared by the
    platform console and an academy's activity log: what happened, who did it
    (the platform team stands out), when, and optionally the academy and IP.
--}}
@php
    $icons = [
        'billing' => \Filament\Support\Icons\Heroicon::OutlinedBanknotes,
        'scheduling' => \Filament\Support\Icons\Heroicon::OutlinedCalendarDays,
        'payroll' => \Filament\Support\Icons\Heroicon::OutlinedWallet,
        'import' => \Filament\Support\Icons\Heroicon::OutlinedArrowUpTray,
        'identity' => \Filament\Support\Icons\Heroicon::OutlinedKey,
        'impersonation' => \Filament\Support\Icons\Heroicon::OutlinedLifebuoy,
        'platform' => \Filament\Support\Icons\Heroicon::OutlinedBuildingLibrary,
    ];
@endphp
<style>
    .lms-audit { display: flex; flex-direction: column; }
    .lms-audit-row { display: flex; gap: .75rem; padding: .7rem .2rem; border-top: 1px solid var(--gray-100); align-items: flex-start; }
    .lms-audit-row:first-child { border-top: 0; }
    .lms-audit-icon { flex-shrink: 0; width: 2rem; height: 2rem; border-radius: .65rem; display: grid; place-items: center; background: var(--gray-100); color: var(--gray-600); }
    .lms-audit-icon svg { width: 1.05rem; height: 1.05rem; }
    .lms-audit-row[data-group="billing"] .lms-audit-icon { background: var(--success-50); color: var(--success-700); }
    .lms-audit-row[data-group="scheduling"] .lms-audit-icon { background: var(--primary-50); color: var(--primary-700); }
    .lms-audit-row[data-group="payroll"] .lms-audit-icon { background: var(--info-50, var(--primary-50)); color: var(--info-700, var(--primary-700)); }
    .lms-audit-row[data-group="impersonation"] .lms-audit-icon, .lms-audit-row[data-group="platform"] .lms-audit-icon { background: var(--warning-50); color: var(--warning-700); }
    .lms-audit-body { min-width: 0; display: flex; flex-direction: column; gap: .2rem; }
    .lms-audit-body strong { font-size: .9rem; font-weight: 600; line-height: 1.45; overflow-wrap: anywhere; }
    .lms-audit-meta { display: flex; flex-wrap: wrap; gap: .25rem .6rem; font-size: .78rem; color: var(--gray-500); align-items: center; }
    .lms-audit-meta a { color: var(--primary-600); font-weight: 600; }
    .lms-audit-meta a:hover { text-decoration: underline; }
    .lms-audit-who[data-platform] { font-weight: 600; padding: .05rem .5rem; border-radius: 999px; background: var(--warning-100); color: var(--warning-800); }
    .lms-audit-ip { font-variant-numeric: tabular-nums; direction: ltr; unicode-bidi: isolate; }
    .lms-audit-empty { color: var(--gray-500); font-size: .88rem; padding: 1.4rem .2rem; text-align: center; }
    .dark .lms-audit-row { border-color: var(--gray-800); }
    .dark .lms-audit-icon { background: rgb(255 255 255 / .06); color: var(--gray-300); }
    .dark .lms-audit-row[data-group="billing"] .lms-audit-icon { background: color-mix(in oklab, var(--success-500) 16%, transparent); color: var(--success-300); }
    .dark .lms-audit-row[data-group="scheduling"] .lms-audit-icon, .dark .lms-audit-row[data-group="payroll"] .lms-audit-icon { background: color-mix(in oklab, var(--primary-500) 16%, transparent); color: var(--primary-300); }
    .dark .lms-audit-row[data-group="impersonation"] .lms-audit-icon, .dark .lms-audit-row[data-group="platform"] .lms-audit-icon { background: color-mix(in oklab, var(--warning-500) 16%, transparent); color: var(--warning-300); }
    .dark .lms-audit-who[data-platform] { background: color-mix(in oklab, var(--warning-500) 18%, transparent); color: var(--warning-300); }
</style>
<div class="lms-audit lms-stagger">
    @forelse ($entries as $entry)
        <div class="lms-audit-row" data-group="{{ $entry['group'] }}" wire:key="audit-{{ $entry['id'] }}">
            <span class="lms-audit-icon" aria-hidden="true">{{ \Filament\Support\generate_icon_html($icons[$entry['group']] ?? \Filament\Support\Icons\Heroicon::OutlinedDocumentText) }}</span>
            <div class="lms-audit-body">
                <strong>{{ $entry['title'] }}</strong>
                <span class="lms-audit-meta">
                    <span class="lms-audit-who" @if ($entry['platform']) data-platform @endif>{{ $entry['actor'] }}</span>
                    <span>{{ $entry['when'] }}</span>
                    @if (array_key_exists('academy', $entry))
                        @if ($entry['academy'] !== null)
                            <a href="{{ $entry['academy_url'] }}" wire:navigate>{{ $entry['academy'] }}</a>
                        @else
                            <span>{{ $platformLabel ?? '' }}</span>
                        @endif
                    @endif
                    @if (! empty($entry['ip']))<span class="lms-audit-ip">{{ $entry['ip'] }}</span>@endif
                </span>
            </div>
        </div>
    @empty
        <p class="lms-audit-empty">{{ $empty }}</p>
    @endforelse
</div>
