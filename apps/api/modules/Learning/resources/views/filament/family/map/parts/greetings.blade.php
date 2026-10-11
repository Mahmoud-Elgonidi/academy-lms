@php
    /*
     * What waits for the student when they come: the cheers their family sent (shown once, then marked seen) and
     * the last lesson's stars ("⭐⭐⭐⭐ on time · stayed to the end"), once per lesson on this device.
     */
    $m = 'learning::map.';
    $cheers = $this->cheers;
    $last = $j['last_lesson'];
    $emoji = ['clap' => '👏', 'star' => '⭐', 'heart' => '💛', 'fire' => '🔥'];
    $labels = [];
    if ($last) {
        if ($last['status'] === 'student_absent') {
            $labels[] = __($m.'lesson.missed');
        } elseif ($last['late_min'] === null) {
            $labels[] = __($m.'lesson.marked');
        } else {
            $labels[] = $last['on_time'] ? __($m.'lesson.on_time') : trans_choice($m.'lesson.late', $last['late_min'], ['n' => $last['late_min']]);
            if ($last['stayed']) {
                $labels[] = __($m.'lesson.stayed');
            }
        }
    }
    $kids = $kids ?? false;
@endphp
@if ($cheers !== [])
    <div class="{{ $kids ? 'jk-card' : 'jm-cheer' }}" role="status" x-data x-init="$wire.cheersSeen()" wire:key="cheers-{{ count($cheers) }}">
        @foreach ($cheers as $c)
            <div>{{ $emoji[$c['kind']] ?? '💛' }} {{ __($m.'cheers.from', ['name' => $c['from'] ?: __($m.'cheers.someone'), 'kind' => __('learning::challenges.cheers.'.$c['kind'])]) }}</div>
        @endforeach
    </div>
@endif
@if ($last && $this->isSelf())
    <div class="{{ $kids ? 'jk-card' : 'jm-lastl' }}" x-data="{ seen: true, key: 'lms-journey-last:' + @js($last['id']), init() { const mem = (window.LmsJourneySeen ??= {}); if (this.key in mem) { this.seen = mem[this.key]; return; } try { this.seen = window.localStorage.getItem(this.key) === '1'; if (! this.seen) window.localStorage.setItem(this.key, '1'); } catch (e) { this.seen = false; } mem[this.key] = this.seen; }, close() { this.seen = true; window.LmsJourneySeen[this.key] = true; } }"
         x-show="! seen" x-cloak role="status" wire:key="last-{{ $last['id'] }}">
        <div class="jm-row" style="gap: .4rem">
            <div>
                <small style="display: block; font-weight: 700; opacity: .8">{{ __($m.'last_lesson.title') }}</small>
                @unless ($plain)<b aria-label="{{ trans_choice($m.'last_lesson.stars', $last['stars'], ['count' => $last['stars']]) }}">{{ str_repeat('⭐', $last['stars']) }}</b>@endunless
                <span style="font-weight: 700">{{ implode(' · ', $labels) }}</span>
            </div>
            <button type="button" class="{{ $kids ? 'jk-go' : 'jm-btn' }}" data-kind="{{ $kids ? 'plain' : 'ghost' }}" style="{{ $kids ? 'min-height: 2.2rem; padding: 0 .9rem' : '' }}" x-on:click="close()">{{ __($m.'last_lesson.close') }}</button>
        </div>
    </div>
@endif
