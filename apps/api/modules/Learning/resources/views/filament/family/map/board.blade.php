@php
    /*
     * The learner's journey (JourneyMap), in the viewer's look: kids (the islands map), middle (levels and the
     * race), calm (older students, adults, the calm and plain styles) or guardian. The games put their rounds,
     * results and eggs in the play hook; this page tells them what to start with browser events.
     */
    $j = $this->journey;
    $look = $this->look();
@endphp
<div class="jm-board">
    @include('shared::games.art-styles')
    @include('learning::filament.family.map.styles')
    @if ($j === null)
        <div class="jm-empty">{{ __('learning::map.none') }}</div>
    @else
        <div class="jm" data-look="{{ $look }}" data-live-channel="{{ $this->channel() }}" data-live-now="{{ now()->getTimestampMs() }}" data-live-next="{{ $this->liveNext() }}">
            <i hidden data-live-signal="practice" data-live-channel="{{ $this->channel() }}"></i>
            <i hidden data-live-signal="homework" data-live-channel="{{ $this->channel() }}"></i>
            @include('learning::filament.family.map.'.$look, ['j' => $j, 'course' => $this->currentCourse(), 'games' => $j['games'], 'plain' => $this->plain()])
            {{ \Filament\Support\Facades\FilamentView::renderHook(\Modules\Learning\Filament\Family\JourneyBoard::PLAY_HOOK, data: ['start' => $this->start, 'look' => $look]) }}
        </div>
    @endif
</div>

@script
<script>
    // Live: a round played elsewhere, homework handed in or marked, a lesson that changed (each a signal on the
    // viewer's own channel), and the moment a classroom opens or closes (data-live-next); no timer of its own.
    const root = $wire.$el.querySelector('.jm[data-live-channel]');
    if (root && window.LmsLive) {
        window.LmsLive.watch({ el: root, channel: root.dataset.liveChannel, event: '.lessons.changed', refresh: () => $wire.$refresh(), every: 120000 });
        for (const el of $wire.$el.querySelectorAll('[data-live-signal]')) {
            window.LmsLive.watch({ el, channel: el.dataset.liveChannel, event: '.' + el.dataset.liveSignal + '.changed', refresh: () => $wire.$refresh(), every: 120000 });
        }
    }
</script>
@endscript
