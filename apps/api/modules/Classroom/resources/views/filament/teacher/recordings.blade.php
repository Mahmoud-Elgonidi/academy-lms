@php $l = 'classroom::recordings.'; $s = $this->screen; @endphp
<x-filament-panels::page>
    <div data-live-channel="{{ $s['channel'] }}" hidden></div>
    <div class="rc-stack">
        @include('classroom::filament.parts.consent-card', ['consent' => $s['consent'], 'records' => $s['records']])
        @if ($s['shared'])
            <h2 class="rc-section">{{ __($l.'teacher.list') }}</h2>
            @include('classroom::filament.parts.recording-list', ['recordings' => $s['recordings'], 'playing' => $playing, 'withStudent' => true, 'empty' => __($l.'teacher.empty')])
        @else
            <p class="rc-muted">{{ __($l.'teacher.not_shared') }}</p>
        @endif
    </div>
    @include('classroom::filament.parts.styles')
</x-filament-panels::page>

@script
<script>
    const el = $wire.$el.querySelector('[data-live-channel]');
    if (el && window.LmsLive) {
        window.LmsLive.watch({ el, channel: el.dataset.liveChannel, event: '.lessons.changed', refresh: () => $wire.$refresh(), every: 120000 });
    }
</script>
@endscript
