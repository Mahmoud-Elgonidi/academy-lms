<x-filament-panels::page>
    <style>
        .ps-version { margin-top: -.75rem; color: var(--gray-500); font-size: .8125rem; }
        .ps-box { background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl); padding: 1rem 1.25rem; display: flex; flex-direction: column; gap: .5rem; }
        .ps-box h2 { margin: 0; font-size: 1rem; font-weight: 700; }
        .ps-box p { margin: 0; color: var(--gray-500); font-size: .8125rem; }
        .ps-table { width: 100%; border-collapse: collapse; font-size: .84rem; }
        .ps-table th { text-align: start; font-size: .72rem; color: var(--gray-500); font-weight: 600; padding: .45rem .5rem; border-bottom: 1px solid var(--gray-200); }
        .ps-table td { padding: .55rem .5rem; border-bottom: 1px solid var(--gray-100); vertical-align: top; }
        .ps-num { color: var(--gray-500); font-variant-numeric: tabular-nums; }
        .ps-credit { font-weight: 600; }
        .ps-credit[data-k="refused"] { color: var(--danger-700); }
        .ps-wrap { overflow-x: auto; }
        .dark .ps-box { background: var(--gray-900); border-color: var(--gray-800); }
    </style>

    @if ($line = $this->versionLine())
        <div class="ps-version">{{ $line }}</div>
    @endif

    <form wire:submit="save" class="flex flex-col gap-6">
        {{ $this->form }}
        <div><x-filament::button type="submit">{{ __('scheduling::policy.save') }}</x-filament::button></div>
    </form>

    <section class="ps-box">
        <h2>{{ __('scheduling::policy.rules.title') }}</h2>
        <p>{{ __('scheduling::policy.rules.hint') }} ↯ = {{ __('scheduling::policy.conditions.notice_below', ['hours' => $this->data['student_notice_hours'] ?? 12]) }}</p>
        <div class="ps-wrap">
            <table class="ps-table">
                <thead>
                    <tr>
                        <th>{{ __('scheduling::policy.rules.rule') }}</th>
                        <th>{{ __('scheduling::policy.rules.event') }}</th>
                        <th>{{ __('scheduling::policy.rules.condition') }}</th>
                        <th>{{ __('scheduling::policy.rules.credit') }}</th>
                        <th>{{ __('scheduling::policy.rules.teacher_pay') }}</th>
                        <th>{{ __('scheduling::policy.rules.record') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->ruleRows() as $r)
                        <tr>
                            <td class="ps-num">{{ $r['rule'] }}</td>
                            <td>{{ $r['event'] }}</td>
                            <td>{{ $r['condition'] }}</td>
                            <td class="ps-credit" @if ($r['refused']) data-k="refused" @endif>{{ $r['credit'] }}</td>
                            <td class="ps-num">{{ $r['pay'] }}</td>
                            <td>{{ $r['record'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</x-filament-panels::page>
