@php $m = 'platform::modules.preview.'; @endphp
<div style="display: grid; gap: .75rem; font-size: .875rem; text-align: start; color: var(--gray-700)">
    @if ($preview['refusals'] !== [])
        <div style="display: grid; gap: .25rem; padding: .75rem; border-radius: var(--radius-lg); background: var(--danger-50); color: var(--danger-700)" data-refusals>
            <strong>{{ __($m.'refused') }}</strong>
            @foreach ($preview['refusals'] as $refusal)
                <p style="margin: 0">{{ $refusal }}</p>
            @endforeach
        </div>
    @endif
    <div style="display: grid; gap: .25rem">
        <strong>{{ __($preview['on'] ? $m.'where_on' : $m.'where_off') }}</strong>
        <ul style="margin: 0; padding-inline-start: 1.25rem; list-style: disc">
            @foreach ($preview['where'] as $place)
                <li>{{ $place }}</li>
            @endforeach
        </ul>
    </div>
    @if ($preview['also'] !== [])
        <p style="margin: 0; color: var(--warning-700)">{{ __($m.'also', ['modules' => implode(app()->getLocale() === 'ar' ? '، ' : ', ', $preview['also'])]) }}</p>
    @endif
    <p style="margin: 0; color: var(--gray-500)">{{ $preview['stays'] }}</p>
</div>
