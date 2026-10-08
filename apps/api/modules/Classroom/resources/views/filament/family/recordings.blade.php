@php $l = 'classroom::recordings.'; $s = $this->screen; @endphp
<x-filament-panels::page>
    <div data-live-channel="{{ $s['channel'] }}" hidden></div>
    <div class="rc-stack">
        @unless ($s['records'])<p class="rc-muted">{{ __($l.'family.off') }}</p>@endunless
        @if ($s['consent'])
            @include('classroom::filament.parts.consent-card', ['consent' => $s['consent'], 'records' => $s['records']])
        @endif
        @if ($s['shared'])
            <h2 class="rc-section">{{ __($l.'family.list', ['name' => $s['learner']]) }}</h2>
            @include('classroom::filament.parts.recording-list', ['recordings' => $s['recordings'], 'playing' => $playing, 'withStudent' => false, 'empty' => __($l.'family.empty')])
        @endif
    </div>
    @include('classroom::filament.parts.styles')
</x-filament-panels::page>

@script
<script>
    // Consent given elsewhere, or a recording becoming ready, refreshes the page (the person's own live channel).
    const el = $wire.$el.querySelector('[data-live-channel]');
    if (el && window.LmsLive) {
        window.LmsLive.watch({ el, channel: el.dataset.liveChannel, event: '.consents.changed', refresh: () => $wire.$refresh(), every: 120000 });
        window.LmsLive.watch({ el, channel: el.dataset.liveChannel, event: '.lessons.changed', refresh: () => $wire.$refresh(), every: 120000 });
    }
</script>
@endscript
