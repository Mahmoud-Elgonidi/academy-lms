{{-- One lesson card (family area). $lesson from FamilyLessonView. With $after, other modules add a row under it (the "scheduling::family.lesson-card" hook: the rating). --}}
@php $l = 'scheduling::family.'; @endphp
<article class="fl-card" data-status="{{ $lesson['status'] }}" wire:key="fl-{{ $lesson['id'] }}">
    <div class="fl-when">
        <strong>{{ $lesson['day'] }}</strong>
        <span class="fl-time">{{ $lesson['time'] }}</span>
    </div>
    <div class="fl-what">
        <strong>{{ $lesson['subject'] }} @if ($lesson['type']) <span class="fl-tag">{{ $lesson['type'] }}</span> @endif</strong>
        <small>{{ __($l.'with', ['name' => $lesson['with']]) }}@if ($lesson['substitute'] ?? null) <span class="fl-tag" data-kind="substitute">{{ $lesson['substitute'] }}</span>@endif</small>
        @if ($lesson['why'] ?? null)<small class="fl-muted">{{ $lesson['why'] }}</small>@endif
    </div>
    <div class="fl-side">
        @if ($lesson['joinUrl'])
            <x-filament::button tag="a" :href="$lesson['joinUrl']" target="_blank" icon="heroicon-o-video-camera">{{ __($l.'join') }}</x-filament::button>
        @else
            <span class="fl-status" data-status="{{ $lesson['status'] }}">{{ $lesson['statusLabel'] }}</span>
            @if ($lesson['unfunded'])
                <small class="fl-warn">{{ __($l.'unfunded') }}</small>
            @elseif ($lesson['opensAt'] && ($lesson['day'] === __($l.'today')))
                <small class="fl-muted">{{ __($l.'opens_at', ['time' => $lesson['opensAt']]) }}</small>
            @endif
        @endif
        @isset($refuse)
            @if ($lesson['refusable'] ?? false)
                {{ ($refuse)(['session' => $lesson['id']]) }}
            @endif
        @endisset
        @isset($cancel)
            @if ($lesson['cancellable'])
                {{ ($cancel)(['session' => $lesson['id']]) }}
            @endif
        @endisset
    </div>
    @if ($after ?? false)
        {{ \Filament\Support\Facades\FilamentView::renderHook('scheduling::family.lesson-card', data: ['lesson' => $lesson]) }}
    @endif
</article>
