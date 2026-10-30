{{-- One shift proposal in the slide-over: what changes, the booked students it leaves out, and the conversation. --}}
@php
    $l = 'scheduling::review.';
    $arrow = __('scheduling::shifts.arrow');
@endphp

@if ($r === null)
    <p class="sp-muted">{{ __($l.'missing') }}</p>
@else
    <div class="spo">
        <div class="spo-facts">
            <span class="sp-pill" data-status="{{ $r['status']->value }}">{{ __('scheduling::shifts.statuses.'.$r['status']->value) }}</span>
            <span><small>{{ __($l.'starts') }}</small> {{ $r['from'] }}</span>
            <span>
                <small>{{ __($l.'weekly_hours') }}</small> {{ $r['hours_before'] }} {{ $arrow }} {{ $r['hours_after'] }}
                @if ($r['delta'] !== 0)
                    <b @class(['sp-good' => $r['delta'] > 0, 'sp-bad' => $r['delta'] < 0])>({{ $r['delta'] > 0 ? '+' : '−' }}{{ \Modules\Scheduling\Domain\Shifts\ProposalRules::hours(abs($r['delta'])) }})</b>
                @endif
            </span>
            @if ($r['reduces'])
                <span><small>{{ __($l.'notice', ['days' => $r['notice_days']]) }}</small> <b @class(['sp-good' => $r['notice_ok'], 'sp-bad' => ! $r['notice_ok']])>{{ $r['notice_ok'] ? __($l.'notice_ok') : __($l.'notice_short') }}</b></span>
            @endif
            @if ($r['revision'] > 1)
                <span><small>{{ __($l.'revision', ['n' => $r['revision'], 'by' => __('scheduling::shifts.roles.'.$r['author'])]) }}</small></span>
            @endif
        </div>

        @if ($r['status']->value === 'countered')
            <div class="spo-note" data-tone="info">{{ __($l.'waiting_teacher') }}</div>
        @elseif ($r['status']->value === 'submitted' && $r['unresolved'] === 0)
            <div class="spo-note">{{ __($l.'ready', ['date' => $r['from']]) }}</div>
        @endif

        <section class="spo-step">
            <h3 class="lms-heading"><span class="spo-n">1</span> {{ __($l.'steps.compare') }} <small>{{ __($l.'teacher_clock', ['city' => $r['city']]) }}</small></h3>
            @include('scheduling::filament.shift-week', ['week' => $r['week'], 'left' => __($l.'columns.current'), 'right' => __($l.'columns.proposed')])
        </section>

        @if (count($r['conflicts']) > 0)
            <section class="spo-step">
                <h3 class="lms-heading"><span class="spo-n">2</span> {{ __($l.'steps.impact') }}
                    <small @class(['sp-bad' => $r['unresolved'] > 0, 'sp-good' => $r['unresolved'] === 0])>{{ $r['unresolved'] > 0 ? __($l.'unresolved_count', ['count' => $r['unresolved']]) : __($l.'all_resolved') }}</small>
                </h3>
                <p class="sp-muted">{{ trans_choice($l.'impact_hint', count($r['conflicts']), ['count' => count($r['conflicts'])]) }}</p>
                <div class="lms-stagger spo-conflicts">
                    @foreach ($r['conflicts'] as $c)
                        <div class="spo-conflict" data-resolved="{{ $c['resolution'] ? 'true' : 'false' }}" wire:key="c-{{ md5($c['key']) }}">
                            <div class="spo-conflict-head">
                                <div>
                                    <strong>{{ $c['student'] }}</strong> · {{ $c['subject'] }}<br>
                                    <small>{{ $c['teacher_label'] }} · {{ $c['student_label'] }} · {{ trans_choice($l.'sessions_count', $c['count'], ['count' => $c['count']]) }} ({{ $c['range'] }})</small>
                                </div>
                                <span class="spo-state {{ $c['resolution'] ? 'sp-good' : 'sp-bad' }}">{{ $c['resolution'] ? __($l.'resolved') : __($l.'needs_resolution') }}</span>
                            </div>
                            @if ($r['status']->isOpen())
                                <div class="spo-choices">
                                    @if ($c['move_url'])
                                        <a href="{{ $c['move_url'] }}" wire:navigate class="spo-move" title="{{ __($l.'resolution_hints.moved') }}">{{ __($l.'resolutions.moved') }}</a>
                                    @else
                                        <button type="button" disabled title="{{ __($l.'move_unenrolled') }}">{{ __($l.'resolutions.moved') }}</button>
                                    @endif
                                    @foreach (['exception', 'delay'] as $kind)
                                        <button type="button" wire:click="resolve(@js($r['id']), @js($c['key']), '{{ $kind }}')" aria-pressed="{{ $c['resolution'] === $kind ? 'true' : 'false' }}" title="{{ __($l.'resolution_hints.'.$kind) }}">
                                            {{ __($l.'resolutions.'.$kind) }}
                                        </button>
                                    @endforeach
                                    @if ($c['resolution'])
                                        <button type="button" wire:click="resolve(@js($r['id']), @js($c['key']), null)">{{ __($l.'undo') }}</button>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="spo-step">
            <h3 class="lms-heading"><span class="spo-n">{{ count($r['conflicts']) > 0 ? 3 : 2 }}</span> {{ __($l.'steps.thread') }}</h3>
            <ol class="spo-thread">
                @forelse ($r['timeline'] as $i => $m)
                    @if ($m['type'] === 'event')
                        <li class="spo-event" wire:key="t-{{ $i }}"><span>{{ $m['text'] }} · {{ $m['at'] }}</span></li>
                    @else
                        <li class="spo-msg" data-role="{{ $m['role'] }}" wire:key="t-{{ $i }}">
                            <header><strong>{{ $m['author'] }}</strong> <span class="spo-role">{{ $m['role_label'] }}</span> <time>{{ $m['at'] }}</time></header>
                            <p>{{ $m['body'] }}</p>
                        </li>
                    @endif
                @empty
                    <li class="sp-muted">{{ __($l.'no_messages') }}</li>
                @endforelse
            </ol>
            @if ($r['status']->isOpen())
                <form class="spo-composer" wire:submit="sendReply(@js($r['id']))">
                    <textarea wire:model="reply" rows="1" maxlength="2000" placeholder="{{ __($l.'write_message') }}" aria-label="{{ __($l.'write_message') }}"></textarea>
                    @error('reply')<p class="sp-bad spo-error">{{ $message }}</p>@enderror
                    <div class="spo-composer-foot">
                        <small class="sp-muted">{{ __($l.'reply_hint') }}</small>
                        <x-filament::button type="submit" size="sm">{{ __($l.'send') }}</x-filament::button>
                    </div>
                </form>
            @endif
        </section>
    </div>
@endif
