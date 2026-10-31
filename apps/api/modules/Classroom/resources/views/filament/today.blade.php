@php
    $l = 'classroom::classroom.';
    $today = $this->today;
@endphp
<x-filament-panels::page data-live-now="{{ $today['now'] }}" data-live-next="{{ $today['next'] }}" data-live-channel="{{ $today['channel'] }}">
    <style>
        .td { display: flex; flex-direction: column; gap: .6rem; }
        .td-row { display: grid; grid-template-columns: 8.5rem minmax(0, 1fr) auto; gap: .75rem; align-items: center; padding: .85rem 1rem; background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl); }
        .td-row[data-open] { border-color: var(--primary-400); box-shadow: 0 0 0 3px color-mix(in oklab, var(--primary-500) 15%, transparent); }
        .td-row[data-past] { opacity: .6; }
        .td-time { font-variant-numeric: tabular-nums; font-weight: 700; }
        .td-who { display: flex; flex-direction: column; min-width: 0; }
        .td-who small { color: var(--gray-500); }
        .td-wait { color: var(--gray-500); font-size: .85rem; white-space: nowrap; }
        .td-group { align-self: flex-start; display: inline-flex; align-items: center; gap: .3rem; margin-bottom: .15rem; padding: .1rem .55rem; border-radius: 999px; font-size: .72rem; font-weight: 700; background: var(--info-50); color: var(--info-700); border: 1px solid var(--info-200); }
        .td-group-ico { width: .85rem; height: .85rem; }
        .dark .td-group { background: color-mix(in oklab, var(--info-500) 16%, var(--gray-900)); color: var(--info-300); border-color: color-mix(in oklab, var(--info-500) 35%, var(--gray-900)); }
        .td-empty { padding: 2rem; text-align: center; color: var(--gray-500); border: 1px dashed var(--gray-300); border-radius: var(--radius-xl); }
        @media (max-width: 640px) { .td-row { grid-template-columns: minmax(0, 1fr) auto; } .td-time { grid-column: 1 / -1; } }
        .dark .td-row { background: var(--gray-900); border-color: var(--gray-800); }
    </style>
    <div class="td">
        @forelse ($this->lessons as $lesson)
            <div class="td-row" @if ($lesson['open']) data-open @endif @if ($lesson['past'] && ! $lesson['open']) data-past @endif wire:key="td-{{ $lesson['id'] }}">
                <span class="td-time">{{ $lesson['time'] }}</span>
                @if ($lesson['group'])
                    <span class="td-who" data-group>
                        <span class="td-group"><x-filament::icon icon="heroicon-m-user-group" class="td-group-ico" />{{ $lesson['group']['badge'] }}</span>
                        <strong>{{ $lesson['group']['names'] }}</strong><small>{{ $lesson['subject'] }}</small>
                    </span>
                @else
                    <span class="td-who"><strong>{{ $lesson['student'] }}</strong><small>{{ $lesson['subject'] }}</small></span>
                @endif
                @if ($lesson['open'])
                    <x-filament::button tag="a" :href="$lesson['url']" target="_blank" icon="heroicon-o-video-camera">{{ __($l.'today.join') }}</x-filament::button>
                @elseif ($lesson['status'] === 'cancelled')
                    <span class="td-wait">{{ __($l.'today.cancelled') }}</span>
                @elseif ($lesson['past'])
                    <span class="td-wait">{{ __($l.'today.done') }}</span>
                @else
                    <span class="td-wait">{{ __($l.'today.opens', ['time' => $lesson['opens']]) }}</span>
                @endif
            </div>
        @empty
            <div class="td-empty">{{ __($l.'today.none') }}</div>
        @endforelse
    </div>
</x-filament-panels::page>

@script
<script>
    // Refreshes when one of the teacher's lessons changes, and when a classroom opens or closes (data-live-next);
    // every 30 seconds only while there is no live connection (LmsLive, shared/live.blade.php).
    const el = $wire.$el;
    if (window.LmsLive) {
        window.LmsLive.watch({ el, channel: el.dataset.liveChannel, event: '.lessons.changed', refresh: () => $wire.$refresh(), every: 30000 });
    } else {
        setInterval(() => $wire.$refresh(), 30000);
    }
</script>
@endscript
