{{-- One shift proposal in the slide-over: a summary on top, then the comparison, the booked students it leaves out and the conversation as tabs. --}}
@php
    $l = 'scheduling::review.';
    $arrow = __('scheduling::shifts.arrow');
@endphp

@if ($r === null)
    <p class="sp-muted">{{ __($l.'missing') }}</p>
@else
    @php
        $max = max($r['minutes_before'], $r['minutes_after'], 1);
        $panes = array_values(array_filter([
            ['key' => 'compare', 'count' => null],
            count($r['conflicts']) > 0 ? ['key' => 'conflicts', 'count' => count($r['conflicts'])] : null,
            ['key' => 'thread', 'count' => $r['messages'] ?: null],
        ]));
    @endphp
    <div class="spo" x-data="{ pane: @js($r['pane']) }" data-pane="{{ $r['pane'] }}">
        <section class="spo-hero">
            <div class="spo-hero-top">
                <span class="sp-pill" data-status="{{ $r['status']->value }}">{{ __('scheduling::shifts.statuses.'.$r['status']->value) }}</span>
                <span class="spo-from">{{ __($l.'starts_on', ['date' => $r['from']]) }}</span>
                @if ($r['revision'] > 1)
                    <span class="sp-muted">{{ __($l.'revision', ['n' => $r['revision'], 'by' => __('scheduling::shifts.roles.'.$r['author'])]) }}</span>
                @endif
            </div>

            <div class="spo-hero-hours">
                <div class="spo-big">
                    <span class="sp-h-before">{{ $r['hours_before'] }}</span>
                    <span class="sp-h-arrow" aria-hidden="true">{{ $arrow }}</span>
                    <span class="sp-h-after">{{ $r['hours_after'] }}</span>
                    <small>{{ __($l.'hours_a_week') }}</small>
                    @if ($r['delta'] !== 0)
                        <span class="sp-delta" dir="ltr" data-sign="{{ $r['delta'] > 0 ? 'up' : 'down' }}">{{ $r['delta'] > 0 ? '+' : '−' }}{{ $r['delta_hours'] }}</span>
                    @endif
                </div>
                <div class="spo-bars">
                    @foreach ([['before', $r['minutes_before'], $r['hours_before']], ['after', $r['minutes_after'], $r['hours_after']]] as [$side, $minutes, $hours])
                        <div class="spo-bar" data-side="{{ $side }}">
                            <span class="spo-bar-label">{{ __($l.'bar_'.$side) }}</span>
                            <span class="spo-bar-track"><span class="lms-bar-fill" style="width: {{ round(100 * $minutes / $max) }}%"></span></span>
                            <span class="spo-bar-value">{{ $hours }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            @if ($r['reduces'])
                <p class="spo-notice">
                    {{ __($l.'notice', ['days' => $r['notice_days']]) }}:
                    <b @class(['sp-good' => $r['notice_ok'], 'sp-bad' => ! $r['notice_ok']])>{{ $r['notice_ok'] ? __($l.'notice_ok') : __($l.'notice_short') }}</b>
                </p>
            @endif

            @if ($r['status']->value === 'countered')
                <div class="spo-note" data-tone="info">{{ __($l.'waiting_teacher') }}</div>
            @elseif ($r['status']->value === 'submitted' && $r['unresolved'] === 0)
                <div class="spo-note">{{ __($l.'ready', ['date' => $r['from']]) }}</div>
            @elseif ($r['status']->value === 'submitted')
                <div class="spo-note" data-tone="danger">{{ __($l.'resolve_first', ['left' => $r['unresolved'], 'total' => count($r['conflicts'])]) }}</div>
            @endif
        </section>

        <x-filament::tabs class="spo-tabs">
            @foreach ($panes as $p)
                <x-filament::tabs.item :alpine-active="'pane === \''.$p['key'].'\''" x-on:click="pane = '{{ $p['key'] }}'" data-pane-tab="{{ $p['key'] }}"
                    :badge="$p['count']" :badge-color="$p['key'] === 'conflicts' && $r['unresolved'] > 0 ? 'danger' : 'gray'">
                    {{ __($l.'panes.'.$p['key']) }}
                </x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>

        <section class="spo-pane" x-show="pane === 'compare'" @if ($r['pane'] !== 'compare') x-cloak @endif>
            @include('scheduling::filament.shift-week', ['week' => $r['week'], 'left' => __($l.'columns.current'), 'right' => __($l.'columns.proposed')])
        </section>

        @if (count($r['conflicts']) > 0)
            <section class="spo-pane" x-show="pane === 'conflicts'" @if ($r['pane'] !== 'conflicts') x-cloak @endif>
                <p class="sp-muted">
                    {{ trans_choice($l.'impact_hint', count($r['conflicts']), ['count' => count($r['conflicts'])]) }}
                    <b @class(['sp-bad' => $r['unresolved'] > 0, 'sp-good' => $r['unresolved'] === 0])>{{ $r['unresolved'] > 0 ? __($l.'unresolved_count', ['count' => $r['unresolved']]) : __($l.'all_resolved') }}</b>
                </p>
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

        <section class="spo-pane" x-show="pane === 'thread'" x-cloak>
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
                {{-- Not a <form>: the slide-over is one already, and a form inside it would end it early. --}}
                <div class="spo-composer">
                    <textarea wire:model="reply" rows="1" maxlength="2000" placeholder="{{ __($l.'write_message') }}" aria-label="{{ __($l.'write_message') }}"></textarea>
                    @error('reply')<p class="sp-bad spo-error">{{ $message }}</p>@enderror
                    <div class="spo-composer-foot">
                        <small class="sp-muted">{{ __($l.'reply_hint') }}</small>
                        {{-- A component's attributes take echoes, not @js: the id is a UUID. --}}
                        <x-filament::button type="button" size="sm" wire:click="sendReply('{{ $r['id'] }}')">{{ __($l.'send') }}</x-filament::button>
                    </div>
                </div>
            @endif
        </section>
    </div>
@endif
