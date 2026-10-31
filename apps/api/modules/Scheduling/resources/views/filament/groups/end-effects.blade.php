{{-- What ending a group does, before it is done. Needs: $effects (list of lines). --}}
<ul style="display: flex; flex-direction: column; gap: .45rem; margin: 0; padding: 0; list-style: none; text-align: start">
    @foreach ($effects as $line)
        <li style="display: flex; gap: .5rem; align-items: flex-start; font-size: .875rem">
            <x-filament::icon icon="heroicon-m-arrow-small-left" style="width: 1rem; height: 1rem; margin-top: .15rem; flex-shrink: 0; color: var(--danger-600)" class="rtl:rotate-0 ltr:rotate-180" />
            <span>{{ $line }}</span>
        </li>
    @endforeach
</ul>
