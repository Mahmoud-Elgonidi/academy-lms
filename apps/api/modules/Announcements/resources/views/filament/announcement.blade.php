{{-- One announcement in full: the words as the families read them (escaped, line breaks kept), and who sent it to whom. --}}
<div style="display: flex; flex-direction: column; gap: .75rem;">
    <p style="margin: 0; white-space: pre-line; overflow-wrap: anywhere; line-height: 1.75;">{{ $a->body }}</p>
    <small style="color: var(--gray-500);">{{ $meta }}</small>
</div>
