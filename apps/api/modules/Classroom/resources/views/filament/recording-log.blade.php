@php $l = 'classroom::recordings.office.'; @endphp
<div class="rec-log">
    @if ($rows === [])
        <p>{{ __($l.'log_empty') }}</p>
    @else
        <table>
            <thead><tr><th>{{ __($l.'columns.person') }}</th><th>{{ __($l.'columns.as') }}</th><th>{{ __($l.'columns.opened') }}</th></tr></thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr><td>{{ $row['who'] }}</td><td>{{ $row['as'] }}</td><td dir="auto">{{ $row['when'] }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif
    <style>
        .rec-log table { width: 100%; font-size: .875rem; border-collapse: collapse; }
        .rec-log th { text-align: start; font-weight: 600; color: var(--gray-500); padding: .4rem .5rem; border-bottom: 1px solid var(--gray-200); }
        .rec-log td { padding: .45rem .5rem; border-bottom: 1px solid var(--gray-100); }
        .dark .rec-log th { border-color: var(--gray-700); } .dark .rec-log td { border-color: var(--gray-800); }
    </style>
</div>
