{{-- The new academy's review step: what the form holds now, one line each. --}}
<div>
    <style>
        .na-review { display: grid; grid-template-columns: minmax(8rem, max-content) 1fr; gap: .45rem 1.25rem; margin: 0; font-size: .9rem; }
        .na-review dt { color: var(--gray-500); }
        .na-review dd { margin: 0; font-weight: 600; overflow-wrap: anywhere; }
        .dark .na-review dt { color: var(--gray-400); }
    </style>
    <dl class="na-review">
        @foreach ($rows as $row)
            <dt>{{ $row['label'] }}</dt>
            <dd>{{ $row['value'] !== '' ? $row['value'] : '—' }}</dd>
        @endforeach
    </dl>
</div>
