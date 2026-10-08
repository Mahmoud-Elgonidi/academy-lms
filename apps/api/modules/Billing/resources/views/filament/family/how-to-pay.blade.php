@if ($note)
    <div style="padding: .75rem .9rem; border-radius: var(--radius-lg, .5rem); background: var(--primary-50); color: var(--primary-900); font-size: .875rem">
        <strong style="display: block; margin-bottom: .2rem">{{ __('billing::proofs.send.how') }}</strong>
        <span style="white-space: pre-line; overflow-wrap: anywhere">{{ $note }}</span>
    </div>
@endif
