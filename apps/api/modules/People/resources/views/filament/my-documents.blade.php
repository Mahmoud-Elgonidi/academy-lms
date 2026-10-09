@php
    $l = 'people::checks.mine.';
    $m = $this->mine;
@endphp
<x-filament-panels::page>
    <div data-live-channel="{{ $m['channel'] }}" hidden></div>
    <style>
        .mdoc-state { display: flex; gap: .6rem; align-items: center; padding: .75rem .95rem; border-radius: var(--radius-xl, .75rem); font-size: .92rem; }
        .mdoc-state[data-tone="good"] { background: var(--success-50); color: var(--success-800); }
        .mdoc-state[data-tone="warn"] { background: var(--warning-50); color: var(--warning-900); }
        .mdoc-state[data-tone="bad"] { background: var(--danger-50); color: var(--danger-800); }
        .mdoc-list { list-style: none; margin: 0; padding: 0; background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl, .75rem); }
        .mdoc-list li { padding: .75rem .95rem; border-bottom: 1px solid var(--gray-100); display: flex; justify-content: space-between; gap: .75rem; flex-wrap: wrap; }
        .mdoc-list li:last-child { border-bottom: 0; }
        .mdoc-muted { color: var(--gray-500); font-size: .83rem; }
        .mdoc-note { margin-top: .3rem; padding: .45rem .6rem; border-radius: var(--radius-lg, .5rem); background: var(--danger-50); color: var(--danger-800); font-size: .85rem; }
        .mdoc-pill { font-size: .72rem; font-weight: 600; padding: .1rem .55rem; border-radius: 999px; background: var(--gray-100); color: var(--gray-700); white-space: nowrap; align-self: flex-start; }
        .mdoc-pill[data-status="verified"] { background: var(--success-50); color: var(--success-700); }
        .mdoc-pill[data-status="pending"] { background: var(--warning-50); color: var(--warning-800); }
        .mdoc-pill[data-status="rejected"] { background: var(--danger-50); color: var(--danger-700); }
        .mdoc-empty { padding: 1.2rem; text-align: center; color: var(--gray-500); border: 1px dashed var(--gray-300); border-radius: var(--radius-xl, .75rem); }
        .dark .mdoc-list { background: var(--gray-900); border-color: var(--gray-800); }
    </style>

    @if ($m['required'] && $m['state'] !== null)
        <div class="mdoc-state" data-tone="{{ match ($m['state']) { 'verified' => 'good', 'pending' => 'warn', default => 'bad' } }}">
            <x-filament::icon :icon="$m['state'] === 'verified' ? 'heroicon-o-shield-check' : 'heroicon-o-exclamation-triangle'" class="h-5 w-5" />
            <span>{{ __($l.'state.'.$m['state'], ['day' => $this->day($m['until']) ?? __('people::checks.no_end')]) }}</span>
        </div>
    @endif

    @if ($m['checks'] === [])
        <div class="mdoc-empty">{{ __($l.'none') }}</div>
    @else
        <ul class="mdoc-list lms-stagger">
            @foreach ($m['checks'] as $c)
                <li wire:key="c-{{ $c['id'] }}">
                    <div>
                        <strong>{{ $c['kind']->label() }}</strong>
                        <div class="mdoc-muted">
                            {{ __($l.'sent_on', ['day' => $this->day($c['created_at'])]) }}
                            @if ($c['expires_on']) · {{ __('people::checks.fields.expires_on') }}: {{ $this->day($c['expires_on']) }}@endif
                            @if ($c['file_url']) · <x-filament::link :href="$c['file_url']" target="_blank" size="sm">{{ __($l.'open') }}</x-filament::link>@endif
                        </div>
                        @if ($c['status'] === 'rejected' && $c['note'])<div class="mdoc-note">{{ $c['note'] }}</div>@endif
                    </div>
                    <span class="mdoc-pill" data-status="{{ $c['status'] }}">{{ __('people::checks.statuses.'.$c['status']) }}</span>
                </li>
            @endforeach
        </ul>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>

@script
<script>
    // The academy checks a document: the page follows the teacher's own channel (LmsLive).
    const el = $wire.$el.querySelector('[data-live-channel]');
    if (el && window.LmsLive) {
        window.LmsLive.watch({ el, channel: el.dataset.liveChannel, event: '.checks.changed', refresh: () => $wire.$refresh(), every: 300000 });
    }
</script>
@endscript
