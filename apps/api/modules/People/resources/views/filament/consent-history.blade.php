<ol class="pc-history">
    @forelse ($rows as $row)
        <li>
            <strong>{{ $row['state'] }}</strong>
            <span>{{ collect([$row['who'], $row['when']])->filter()->implode(' · ') }}</span>
            @if ($row['note'])<p>{{ $row['note'] }}</p>@endif
        </li>
    @empty
        <li><span>{{ __('people::consent.state.waiting') }}</span></li>
    @endforelse
</ol>
<style>
    .pc-history { list-style: none; margin: 0; padding: 0; display: grid; gap: .6rem; }
    .pc-history li { border-inline-start: 3px solid var(--primary-500); padding: .2rem .8rem; }
    .pc-history strong { display: block; font-size: .9rem; }
    .pc-history span { color: var(--gray-500); font-size: .82rem; }
    .pc-history p { margin: .2rem 0 0; font-size: .85rem; }
</style>
