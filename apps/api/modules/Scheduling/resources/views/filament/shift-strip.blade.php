{{-- The week in seven cells (ShiftProposalView::days7): each day added, removed, changed, the same, working or off. --}}
<span class="sp-strip" role="list" aria-label="{{ __('scheduling::review.week_strip') }}">
    @foreach ($days as $d)
        <span class="sp-day" role="listitem" data-kind="{{ $d['kind'] }}" title="{{ $d['title'] }}" aria-label="{{ $d['title'] }}">{{ $d['short'] }}</span>
    @endforeach
</span>
