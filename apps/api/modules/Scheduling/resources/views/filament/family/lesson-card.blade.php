{{-- One lesson card (family area). $lesson from FamilyLessonView. --}}
@php $l = 'scheduling::family.'; @endphp
<article class="fl-card" data-status="{{ $lesson['status'] }}" wire:key="fl-{{ $lesson['id'] }}">
    <div class="fl-when">
        <strong>{{ $lesson['day'] }}</strong>
        <span class="fl-time">{{ $lesson['time'] }}</span>
    </div>
    <div class="fl-what">
        <strong>{{ $lesson['subject'] }} @if ($lesson['type']) <span class="fl-tag">{{ $lesson['type'] }}</span> @endif</strong>
        <small>{{ __($l.'with', ['name' => $lesson['with']]) }}</small>
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
        @isset($cancel)
            @if ($lesson['cancellable'])
                {{ ($cancel)(['session' => $lesson['id']]) }}
            @endif
        @endisset
    </div>
</article>
