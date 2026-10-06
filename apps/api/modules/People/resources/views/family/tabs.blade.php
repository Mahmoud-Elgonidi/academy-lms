{{-- A student's tab bar on phones: the area's own menu (the same items and badges as the top bar), always within reach. --}}
<nav class="lms-tabs" aria-label="{{ __('people::family.tabs') }}">
    @foreach (filament()->getNavigation() as $group)
        @foreach ($group->getItems() as $item)
            @php $active = $item->isActive(); @endphp
            <a class="lms-tab" {{ \Filament\Support\generate_href_html($item->getUrl()) }} @if ($active) aria-current="page" @endif>
                {{ \Filament\Support\generate_icon_html(($active ? $item->getActiveIcon() : null) ?? $item->getIcon()) }}
                <span>{{ $item->getLabel() }}</span>
                @if (filled($badge = $item->getBadge())) <b>{{ $badge }}</b> @endif
            </a>
        @endforeach
    @endforeach
</nav>
