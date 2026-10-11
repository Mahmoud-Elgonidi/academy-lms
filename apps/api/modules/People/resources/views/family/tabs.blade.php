@php
    /*
     * A student's tab bar on phones: the area's own menu, in its order (each page knows its place per stage),
     * the first ones as tabs (رحلتي · حصصي · كلّم مدرسك for children and middle school; secondary school also
     * keeps its homework and reports there) and the rest under "more", with the same badges as the top bar.
     */
    $items = collect(filament()->getNavigation())->flatMap(fn ($group) => $group->getItems())->values();
    $keep = ($stage ?? null) === 'teen' ? 4 : 3;
    $tabs = $items->count() <= $keep + 1 ? $items : $items->take($keep);
    $more = $items->slice($tabs->count())->values();
    $moreActive = $more->contains(fn ($item) => $item->isActive());
    $moreBadge = $more->contains(fn ($item) => filled($item->getBadge()));
@endphp
<nav class="lms-tabs" aria-label="{{ __('people::family.tabs') }}" x-data="{ more: false }" x-on:keydown.escape.window="more = false">
    @foreach ($tabs as $item)
        @php $active = $item->isActive(); @endphp
        <a class="lms-tab" {{ \Filament\Support\generate_href_html($item->getUrl()) }} @if ($active) aria-current="page" @endif>
            {{ \Filament\Support\generate_icon_html(($active ? $item->getActiveIcon() : null) ?? $item->getIcon()) }}
            <span>{{ $item->getLabel() }}</span>
            @if (filled($badge = $item->getBadge())) <b>{{ $badge }}</b> @endif
        </a>
    @endforeach
    @if ($more->isNotEmpty())
        <button type="button" class="lms-tab" x-on:click="more = ! more" :aria-expanded="more.toString()" aria-controls="lms-more" @if ($moreActive) aria-current="page" @endif>
            {{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::OutlinedSquares2x2) }}
            <span>{{ __('people::family.more') }}</span>
            @if ($moreBadge) <b aria-hidden="true">•</b> @endif
        </button>
        <div class="lms-more-bg" x-show="more" x-cloak x-on:click="more = false"></div>
        <div class="lms-more" id="lms-more" x-show="more" x-cloak x-transition.opacity role="menu" aria-label="{{ __('people::family.more') }}">
            @foreach ($more as $item)
                @php $active = $item->isActive(); @endphp
                <a class="lms-more-item" role="menuitem" {{ \Filament\Support\generate_href_html($item->getUrl()) }} @if ($active) aria-current="page" @endif x-on:click="more = false">
                    {{ \Filament\Support\generate_icon_html(($active ? $item->getActiveIcon() : null) ?? $item->getIcon()) }}
                    <span>{{ $item->getLabel() }}</span>
                    @if (filled($badge = $item->getBadge())) <b>{{ $badge }}</b> @endif
                </a>
            @endforeach
        </div>
    @endif
</nav>
