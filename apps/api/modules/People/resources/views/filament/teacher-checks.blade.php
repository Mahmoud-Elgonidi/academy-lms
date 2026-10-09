@php
    $l = 'people::checks.office.';
    $b = $this->board;
@endphp
<x-filament-panels::page>
    <div data-live-channel="{{ $b['channel'] }}" hidden></div>
    <style>
        .tc-h2 { font-size: 1rem; font-weight: 700; margin: 0 0 .6rem; }
        .tc-rule { display: flex; gap: .6rem; align-items: center; padding: .7rem .9rem; border-radius: var(--radius-xl, .75rem); font-size: .9rem; }
        .tc-rule[data-on] { background: var(--primary-50); color: var(--primary-900); }
        .tc-rule:not([data-on]) { background: var(--gray-50); color: var(--gray-700); }
        .tc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 20rem), 1fr)); gap: .9rem; }
        .tc-card { background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl, .75rem); padding: .9rem 1rem; display: flex; flex-direction: column; gap: .55rem; }
        .tc-card strong { font-size: 1rem; }
        .tc-muted { color: var(--gray-500); font-size: .83rem; }
        .tc-row { display: flex; gap: .5rem; align-items: center; flex-wrap: wrap; }
        .tc-pill { font-size: .72rem; font-weight: 600; padding: .1rem .55rem; border-radius: 999px; background: var(--gray-100); color: var(--gray-700); white-space: nowrap; }
        .tc-pill[data-tone="good"] { background: var(--success-50); color: var(--success-700); }
        .tc-pill[data-tone="warn"] { background: var(--warning-50); color: var(--warning-800); }
        .tc-pill[data-tone="bad"] { background: var(--danger-50); color: var(--danger-700); }
        .tc-actions { display: flex; gap: .5rem; align-items: center; flex-wrap: wrap; border-top: 1px solid var(--gray-100); padding-top: .55rem; }
        .tc-empty { padding: 1.1rem; text-align: center; color: var(--gray-500); border: 1px dashed var(--gray-300); border-radius: var(--radius-xl, .75rem); }
        .tc-scroll { overflow-x: auto; background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl, .75rem); }
        .tc-table { width: 100%; border-collapse: collapse; font-size: .86rem; }
        .tc-table th { text-align: start; color: var(--gray-500); font-weight: 500; font-size: .75rem; padding: .45rem .7rem; border-bottom: 1px solid var(--gray-200); white-space: nowrap; }
        .tc-table td { padding: .55rem .7rem; border-bottom: 1px solid var(--gray-100); vertical-align: top; }
        .dark .tc-card, .dark .tc-scroll { background: var(--gray-900); border-color: var(--gray-800); }
    </style>

    <div class="tc-rule" @if ($b['required']) data-on @endif>
        <x-filament::icon :icon="$b['required'] ? 'heroicon-o-shield-check' : 'heroicon-o-information-circle'" class="h-5 w-5" />
        <span>{{ $b['required'] ? __($l.'rule_on') : __($l.'rule_off') }}</span>
    </div>

    <section>
        <h2 class="tc-h2">{{ __($l.'pending') }}</h2>
        @if ($b['pending'] === [])
            <div class="tc-empty">{{ __($l.'none_pending') }}</div>
        @else
            <div class="tc-grid lms-stagger">
                @foreach ($b['pending'] as $c)
                    <article class="tc-card" wire:key="p-{{ $c['id'] }}">
                        <div class="tc-row" style="justify-content: space-between">
                            <strong>{{ $c['teacher'] }}</strong>
                            <span class="tc-pill" data-tone="warn">{{ __('people::checks.statuses.pending') }}</span>
                        </div>
                        <div>{{ $c['kind']->label() }} @if ($c['reference'])<span class="tc-muted">· {{ $c['reference'] }}</span>@endif</div>
                        <div class="tc-muted">
                            @if ($c['issued_on']){{ __('people::checks.fields.issued_on') }}: {{ $this->day($c['issued_on']) }} · @endif
                            {{ __('people::checks.fields.expires_on') }}: {{ $c['expires_on'] ? $this->day($c['expires_on']) : '—' }}
                        </div>
                        <div class="tc-actions">
                            @if ($c['file_url'])<x-filament::link :href="$c['file_url']" target="_blank" icon="heroicon-o-document-magnifying-glass" size="sm">{{ __($l.'open_file') }}</x-filament::link>@endif
                            {{ ($this->verifyAction)(['id' => $c['id']]) }}
                            {{ ($this->rejectAction)(['id' => $c['id']]) }}
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    @if ($b['expiring'] !== [])
        <section>
            <h2 class="tc-h2">{{ __($l.'expiring') }}</h2>
            <div class="tc-grid">
                @foreach ($b['expiring'] as $c)
                    <article class="tc-card" wire:key="e-{{ $c['id'] }}">
                        <div class="tc-row" style="justify-content: space-between">
                            <strong>{{ $c['teacher'] }}</strong>
                            <span class="tc-pill" data-tone="warn">{{ __($l.'until', ['day' => $this->day($c['expires_on'])]) }}</span>
                        </div>
                        <div>{{ $c['kind']->label() }}</div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <section>
        <h2 class="tc-h2">{{ __($l.'teachers') }}</h2>
        <div class="tc-scroll">
            <table class="tc-table">
                <thead><tr><th>{{ __($l.'teacher') }}</th><th>{{ __($l.'standing') }}</th><th>{{ __('people::checks.fields.expires_on') }}</th><th></th></tr></thead>
                <tbody>
                    @foreach ($b['teachers'] as $t)
                        <tr wire:key="t-{{ $t['id'] }}" @if ($t['id'] === $this->teacher) style="background: var(--primary-50)" @endif>
                            <td>{{ $t['name'] }}</td>
                            <td>
                                <span class="tc-pill" data-tone="{{ \Modules\People\Queries\TeacherChecksBoard::tone($t['clearance']) }}">{{ __('people::checks.clearance.'.$t['clearance']->state) }}</span>
                                @if ($t['paused_reason'])<div class="tc-muted">{{ $t['paused_reason'] }}</div>@endif
                            </td>
                            <td>{{ $t['clearance']->expiresOn ? $this->day($t['clearance']->expiresOn) : ($t['clearance']->state === 'verified' ? __('people::checks.no_end') : '—') }}</td>
                            <td>
                                @if ($t['paused_reason'] !== null)
                                    {{ ($this->resumeAction)(['id' => $t['id']]) }}
                                @else
                                    {{ ($this->pauseAction)(['id' => $t['id']]) }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <x-filament-actions::modals />
</x-filament-panels::page>

@script
<script>
    // A teacher sends a document, a colleague checks one: the page follows the academy's checks channel (LmsLive).
    const el = $wire.$el.querySelector('[data-live-channel]');
    if (el && window.LmsLive) {
        window.LmsLive.watch({ el, channel: el.dataset.liveChannel, event: '.changed', refresh: () => $wire.$refresh(), every: 120000 });
    }
</script>
@endscript
