@php $l = 'scheduling::disputes.page.'; $groups = $this->groups; @endphp
<x-filament-panels::page>
    @include('scheduling::filament.time-off.styles')
    @include('scheduling::filament.disputes.styles')

    <div class="dp" data-live-channel="{{ $groups['channel'] }}">
        @foreach (['open', 'decided'] as $group)
            <section>
                <h2 class="to-h2 lms-heading">{{ __($l.'groups.'.$group) }} <small>{{ count($groups[$group]) }}</small></h2>
                <div class="to-list lms-stagger">
                    @forelse ($groups[$group] as $d)
                        <article class="dp-card" wire:key="dp-{{ $d['id'] }}" @if ($d['focused']) data-focused @endif>
                            <header class="dp-head">
                                <div>
                                    <strong>{{ $d['student'] }} · {{ $d['subject'] }}</strong>
                                    <small>{{ $d['when'] }} · {{ $d['teacher'] }}</small>
                                </div>
                                <span class="to-pill" data-s="{{ $d['status'] === 'open' ? 'pending' : ($d['status'] === 'upheld' ? 'approved' : 'rejected') }}">{{ $d['statusLabel'] }}</span>
                            </header>

                            <div class="dp-said">
                                <span class="dp-side" data-side="{{ $d['side'] }}">{{ $d['sideLabel'] }} · {{ $d['raisedBy'] }}</span>
                                <span>{{ __($l.'recorded_as', ['outcome' => $d['before']]) }} — <b>{{ $d['reason'] }}</b></span>
                                <small>{{ $d['raisedAgo'] }}</small>
                            </div>
                            <p class="to-note">{{ $d['note'] }}</p>

                            <dl class="dp-presence">
                                @foreach (['teacher' => $d['teacherPresence'], 'student' => $d['studentPresence']] as $role => $p)
                                    <div data-full="{{ $p['full'] ? '1' : '0' }}">
                                        <dt>{{ __($l.'roles.'.$role) }}</dt>
                                        <dd>{{ $p['label'] }}</dd>
                                    </div>
                                @endforeach
                            </dl>

                            @if ($d['status'] !== 'open')
                                <div class="dp-answer">
                                    <span>{{ __($l.'decided', ['after' => $d['after'], 'by' => $d['decidedBy'] ?? '—', 'at' => $d['decidedAt']]) }}</span>
                                    <p class="to-note">{{ $d['answer'] }}</p>
                                    @if ($d['short'] > 0)
                                        <small class="dp-short">{{ __('scheduling::disputes.decide.short', ['minutes' => $d['short']]) }}</small>
                                    @endif
                                </div>
                            @endif

                            <footer class="dp-foot">
                                <a href="{{ $d['calendarUrl'] }}" wire:navigate class="dp-link">{{ __($l.'calendar') }}</a>
                                @if ($d['status'] === 'open')
                                    {{ ($this->decideAction)(['id' => $d['id']]) }}
                                @endif
                            </footer>
                        </article>
                    @empty
                        <div class="to-empty">{{ __($l.'empty.'.$group) }}</div>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>
</x-filament-panels::page>

@script
<script>
    // A dispute raised or decided anywhere arrives on the board's signal (LmsLive, shared/live.blade.php).
    const el = $wire.$el.querySelector('.dp');
    if (el && window.LmsLive) {
        window.LmsLive.watch({ el, channel: el.dataset.liveChannel, event: '.changed', refresh: () => $wire.$refresh(), every: 120000 });
    }
</script>
@endscript
