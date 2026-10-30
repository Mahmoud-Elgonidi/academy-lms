{{-- The publish confirmation's warnings ($warnings, in the reader's words): they do not stop it. --}}
<div style="display: flex; flex-direction: column; gap: .4rem; text-align: start">
    <p style="font-size: .85rem; font-weight: 600; color: var(--warning-700)">{{ __('curriculum::curriculum.readiness.warnings_title') }}</p>
    <ul style="margin: 0; padding-inline-start: 1.1rem; list-style: disc; font-size: .82rem; color: var(--gray-600); max-height: 12rem; overflow-y: auto">
        @foreach (array_slice($warnings, 0, 30) as $warning)
            <li>{{ $warning }}</li>
        @endforeach
    </ul>
    @if (count($warnings) > 30)
        <small style="color: var(--gray-500)">{{ __('curriculum::curriculum.readiness.more', ['count' => count($warnings) - 30]) }}</small>
    @endif
</div>
