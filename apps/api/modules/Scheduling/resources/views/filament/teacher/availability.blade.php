{{-- The teacher's hours, one thing at a time (as the portal and the app): the one thing that matters now on top, one card with the agreed week, "propose a change" while nothing is open, and the history folded away. --}}
@php
    $l = 'scheduling::teacher.availability.';
    $proposal = $this->proposal;
    $hero = $this->hero;
    $weeks = $this->weeks();
    $history = $this->history();
    $steps = $this->steps();
    $stage = $hero['stage'] ?? $hero['kind'] ?? null;
    $icon = match ($stage) {
        'draft' => 'heroicon-o-document-text',
        'submitted' => 'heroicon-o-clock',
        'changes_requested' => 'heroicon-o-exclamation-circle',
        'countered' => 'heroicon-o-chat-bubble-left-right',
        'upcoming' => 'heroicon-o-calendar-days',
        'rejected' => 'heroicon-o-x-circle',
        default => null,
    };
    $whole = fn (?string $h) => $h !== null && ctype_digit($h);
@endphp
<x-filament-panels::page>
    @include('scheduling::filament.shift-styles')
    <style>
        .ta { display: flex; flex-direction: column; gap: 1.25rem; }
        .ta-hero { display: flex; flex-direction: column; gap: 1.1rem; padding: 1.25rem; border-radius: var(--radius-xl); border: 1px solid var(--gray-200); background: white; }
        .ta-hero[data-stage="submitted"] { border-color: var(--info-300); }
        .ta-hero[data-stage="changes_requested"], .ta-hero[data-stage="countered"] { border-color: var(--warning-300); }
        .ta-hero[data-stage="upcoming"] { border-color: var(--primary-300); background: var(--primary-50); }
        .ta-hero[data-stage="rejected"] { border-color: var(--danger-300); }
        .ta-head { display: flex; align-items: flex-start; gap: .85rem; }
        .ta-icon { position: relative; flex-shrink: 0; display: grid; place-items: center; width: 2.75rem; height: 2.75rem; border-radius: 50%; background: var(--gray-100); color: var(--gray-600); }
        .ta-icon svg { width: 1.4rem; height: 1.4rem; }
        .ta-hero[data-stage="submitted"] .ta-icon { background: var(--info-50); color: var(--info-600); }
        .ta-hero[data-stage="changes_requested"] .ta-icon, .ta-hero[data-stage="countered"] .ta-icon { background: var(--warning-50); color: var(--warning-600); }
        .ta-hero[data-stage="upcoming"] .ta-icon { background: white; color: var(--primary-700); }
        .ta-hero[data-stage="rejected"] .ta-icon { background: var(--danger-50); color: var(--danger-600); }
        .ta-pulse { position: absolute; top: -.1rem; inset-inline-end: -.1rem; width: .7rem; height: .7rem; }
        .ta-pulse::before, .ta-pulse::after { content: ""; position: absolute; inset: 0; border-radius: 50%; background: var(--info-500); }
        .ta-pulse::after { inset: .1rem; }
        .ta-head h2 { margin: 0; font-size: 1.1rem; font-weight: 700; color: var(--gray-950); }
        .ta-hero[data-stage="upcoming"] .ta-head h2 { color: var(--primary-800); }
        .ta-head p { margin: .15rem 0 0; font-size: .875rem; color: var(--gray-600); }
        .ta-head .ta-reason { color: var(--gray-900); }
        .ta-numbers { display: flex; flex-wrap: wrap; align-items: baseline; gap: .35rem .75rem; padding-inline-start: 3.6rem; }
        .ta-numbers b { font-size: 2.25rem; line-height: 1; font-weight: 800; color: var(--primary-700); font-variant-numeric: tabular-nums; }
        .ta-numbers .ta-before { font-size: 1.5rem; color: var(--gray-500); }
        .ta-numbers small { font-size: .85rem; color: var(--gray-600); }
        .ta-arrow { width: 1.1rem; height: 1.1rem; align-self: center; color: var(--gray-400); }
        [dir="rtl"] .ta-arrow { transform: scaleX(-1); }
        .ta-sr { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }

        .ta-steps { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .ta-steps li { display: flex; flex-direction: column; align-items: center; gap: .35rem; font-size: .75rem; color: var(--gray-500); text-align: center; position: relative; }
        .ta-steps li::before { content: ""; position: absolute; top: .75rem; inset-inline: 0; height: 2px; background: var(--gray-200); z-index: 0; }
        .ta-steps li:first-child::before { inset-inline-start: 50%; }
        .ta-steps li:last-child::before { inset-inline-end: 50%; }
        .ta-steps li[data-state="done"]::before { background: var(--primary-500); }
        .ta-steps b { position: relative; z-index: 1; display: grid; place-items: center; width: 1.5rem; height: 1.5rem; border-radius: 50%; font-size: .72rem; border: 2px solid var(--gray-300); background: white; color: var(--gray-500); }
        .ta-steps li[data-state="done"] b { border-color: var(--primary-600); background: var(--primary-600); color: white; }
        .ta-steps li[data-state="now"] { color: var(--gray-900); font-weight: 700; }
        .ta-steps li[data-state="now"] b { border-color: var(--primary-600); color: var(--primary-700); }
        .ta-steps[data-tone="warning"] li[data-state="now"] b { border-color: var(--warning-500); background: var(--warning-500); color: white; }

        .ta-fold { border: 1px solid var(--gray-200); border-radius: var(--radius-lg); }
        .ta-fold > summary { display: flex; align-items: center; justify-content: space-between; gap: .75rem; min-height: 2.75rem; padding: 0 1rem; cursor: pointer; list-style: none; font-size: .875rem; font-weight: 600; }
        .ta-fold > summary::-webkit-details-marker { display: none; }
        .ta-fold > summary small { font-weight: 400; color: var(--gray-500); display: flex; align-items: center; gap: .5rem; }
        .ta-chev { width: 1rem; height: 1rem; color: var(--gray-400); }
        .ta-fold[open] > summary .ta-chev { transform: rotate(180deg); }
        .ta-fold-body { border-top: 1px solid var(--gray-100); padding: .25rem 1rem .85rem; display: flex; flex-direction: column; gap: .75rem; }
        .ta-note { margin: 0; font-size: .85rem; color: var(--gray-600); white-space: pre-line; }
        .ta-actions { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem 1rem; }
        .ta-form { display: flex; flex-direction: column; gap: 1rem; }
        .ta-form-title { margin: 0; font-size: 1rem; font-weight: 700; }
        .ta-hero .ta-form { border-top: 1px solid var(--gray-200); padding-top: 1rem; }

        .ta-switch { display: inline-flex; padding: .2rem; border-radius: 999px; border: 1px solid var(--gray-200); background: var(--gray-50); }
        .ta-switch button { min-height: 2.1rem; padding: 0 1rem; border-radius: 999px; font-size: .85rem; font-weight: 600; color: var(--gray-500); }
        .ta-switch button[aria-selected="true"] { background: white; color: var(--primary-700); box-shadow: 0 1px 2px color-mix(in oklab, var(--gray-950) 12%, transparent); }
        .ta-week-top { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .5rem 1rem; margin-bottom: .5rem; }
        .ta-week-top small { color: var(--gray-500); font-size: .85rem; }

        .ta-week { list-style: none; margin: 0; padding: 0; }
        .ta-day { display: grid; grid-template-columns: 7.5rem minmax(0, 1fr) 12rem; align-items: center; gap: .4rem .85rem; padding: .6rem 0; }
        .ta-day + .ta-day { border-top: 1px solid var(--gray-100); }
        .ta-day-name { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem; font-size: .875rem; font-weight: 600; color: var(--gray-900); }
        .ta-day[data-mark="same"] .ta-day-name, .ta-day[data-mark="same"] .ta-times { color: var(--gray-500); font-weight: 400; }
        .ta-mark { padding: 0 .45rem; border-radius: 999px; font-size: .68rem; font-weight: 600; }
        .ta-mark[data-mark="added"] { background: var(--primary-50); color: var(--primary-700); }
        .ta-mark[data-mark="changed"] { background: var(--warning-50); color: var(--warning-700); }
        .ta-mark[data-mark="removed"] { background: var(--danger-50); color: var(--danger-700); }
        .ta-bar { position: relative; height: .6rem; border-radius: 999px; overflow: hidden; background: var(--gray-100); }
        .ta-bar > span { position: absolute; top: 0; bottom: 0; border-radius: 999px; background: var(--primary-500); }
        .ta-day[data-mark="same"] .ta-bar > span[data-kind="kept"] { background: var(--primary-200); }
        .ta-bar > span[data-kind="added"] { background: var(--info-500); }
        .ta-bar > span[data-kind="removed"] { background: repeating-linear-gradient(135deg, var(--danger-500) 0 4px, transparent 4px 7px); }
        .ta-times { display: flex; flex-direction: column; align-items: flex-end; font-size: .8125rem; font-variant-numeric: tabular-nums; color: var(--gray-800); }
        .ta-times s { color: var(--danger-600); }
        .ta-off { color: var(--gray-400); }
        .ta-legend { display: flex; flex-wrap: wrap; gap: 1rem; margin: .5rem 0 0; font-size: .75rem; color: var(--gray-500); }
        .ta-legend i { display: inline-block; width: .65rem; height: .65rem; border-radius: 50%; margin-inline-end: .35rem; vertical-align: middle; background: var(--info-500); }
        .ta-legend i[data-kind="removed"] { background: repeating-linear-gradient(135deg, var(--danger-500) 0 3px, transparent 3px 5px); }
        @media (max-width: 640px) {
            .ta-day { grid-template-columns: minmax(0, 1fr) auto; }
            .ta-bar { grid-column: 1 / -1; order: 3; }
        }

        .ta-propose { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem 1rem; }
        .ta-history-list { list-style: none; margin: 0; padding: 0; }
        .ta-history-list li { display: flex; align-items: flex-start; gap: .75rem; padding: .7rem 1rem; font-size: .85rem; }
        .ta-history-list li + li { border-top: 1px solid var(--gray-100); }
        .ta-history-list li[data-status="superseded"] { color: var(--gray-500); }
        .ta-history-list small { display: block; color: var(--gray-500); }
        .ta-history-list .ta-reason { color: var(--danger-600); }
        .ta .ta-history { background: white; border-radius: var(--radius-xl); }
        .ta .ta-history > summary { min-height: 3rem; font-size: .95rem; }

        .ta .spo-msg[data-mine="true"] { align-self: flex-end; background: var(--primary-50); border-start-start-radius: var(--radius-xl); border-start-end-radius: .3rem; }
        .ta-reply { display: flex; gap: .5rem; align-items: flex-end; }
        .ta-reply textarea { flex: 1; min-height: 2.6rem; border: 1px solid var(--gray-300); border-radius: var(--radius-lg, .5rem); padding: .5rem .6rem; font: inherit; background: transparent; }

        @media (prefers-reduced-motion: no-preference) {
            .ta-pulse::before { animation: ta-pulse 2.4s ease-in-out infinite; }
            .ta-chev { transition: transform .2s ease; }
            .ta-panel { animation: ta-panel-in .32s cubic-bezier(.2, .8, .2, 1) backwards; }
            .ta-fold[open] > .ta-fold-body, .ta-fold[open] > .ta-history-list { animation: ta-panel-in .28s cubic-bezier(.2, .8, .2, 1) backwards; }
        }
        @keyframes ta-pulse { 50% { opacity: .3; transform: scale(1.8); } }
        @keyframes ta-panel-in { from { opacity: 0; transform: translateY(6px); } }

        .dark .ta-hero, .dark .ta .ta-history { background: var(--gray-900); border-color: var(--gray-700); }
        .dark .ta-hero[data-stage="upcoming"] { background: color-mix(in oklab, var(--primary-500) 10%, transparent); border-color: var(--primary-800); }
        .dark .ta-hero[data-stage="upcoming"] .ta-head h2, .dark .ta-numbers b { color: var(--primary-300); }
        .dark .ta-head h2, .dark .ta-day-name { color: var(--gray-100); }
        .dark .ta-times { color: var(--gray-300); }
        .dark .ta-icon, .dark .ta-hero[data-stage="upcoming"] .ta-icon, .dark .ta-steps b, .dark .ta-switch button[aria-selected="true"] { background: var(--gray-800); }
        .dark .ta-switch, .dark .ta-bar { background: color-mix(in oklab, var(--gray-500) 18%, transparent); border-color: var(--gray-700); }
        .dark .ta-fold, .dark .ta-day + .ta-day, .dark .ta-fold-body { border-color: var(--gray-700); }
        .dark .ta-day[data-mark="same"] .ta-bar > span[data-kind="kept"] { background: var(--primary-800); }
        .dark .ta .spo-msg[data-mine="true"] { background: color-mix(in oklab, var(--primary-500) 18%, transparent); }
    </style>

    <div class="ta lms-stagger">
        @if ($hero && $hero['kind'] === 'proposal')
            <section class="ta-hero" data-stage="{{ $stage }}" data-hero>
                <div class="ta-head">
                    <span class="ta-icon">
                        <x-filament::icon :icon="$icon" />
                        @if ($stage === 'submitted')<span class="ta-pulse" aria-hidden="true"></span>@endif
                    </span>
                    <div>
                        <h2>{{ $hero['title'] }}</h2>
                        <p>{{ $hero['text'] }}</p>
                    </div>
                </div>

                @if ($steps)
                    <ol class="ta-steps lms-stagger" data-tone="{{ $steps['tone'] }}" aria-label="{{ __($l.'steps.label') }}">
                        @foreach ($steps['steps'] as $i => $step)
                            <li data-state="{{ $step['state'] }}" @if ($step['state'] === 'now') aria-current="step" @endif>
                                <b>{{ $step['state'] === 'done' ? '✓' : $i + 1 }}</b>{{ $step['label'] }}
                            </li>
                        @endforeach
                    </ol>
                @endif

                <details class="ta-fold" wire:ignore.self @if ($hero['open']) open @endif>
                    <summary>
                        <span>{{ $stage === 'countered' ? __($l.'counter_week') : __($l.'proposed_week') }}</span>
                        <small>
                            {{ __($l.'starts', ['date' => $hero['from']]) }} · {{ __($l.'hours', ['h' => $hero['hours']]) }}
                            <x-filament::icon icon="heroicon-m-chevron-down" class="ta-chev" />
                        </small>
                    </summary>
                    <div class="ta-fold-body">
                        @include('scheduling::filament.teacher.availability-week', ['rows' => $hero['week'], 'compare' => true, 'key' => 'proposal'])
                    </div>
                </details>

                @if ($proposal->note)
                    <p class="ta-note">{{ $proposal->note }}</p>
                @endif

                @if ($this->editing)
                    @include('scheduling::filament.teacher.availability-form')
                @else
                    <div class="ta-actions">
                        @if ($stage === 'draft')
                            <x-filament::button wire:click="startEditing" icon="heroicon-o-paper-airplane">{{ __($l.'continue') }}</x-filament::button>
                        @elseif ($stage === 'changes_requested')
                            <x-filament::button wire:click="startEditing">{{ __($l.'edit_send') }}</x-filament::button>
                        @elseif ($stage === 'countered')
                            {{ $this->acceptAction }}
                            <x-filament::link tag="button" wire:click="startEditing">{{ __($l.'edit') }}</x-filament::link>
                        @endif
                        @if ($this->withdrawAction->isVisible())
                            {{ $this->withdrawAction }}
                        @endif
                    </div>
                @endif

                @if ($stage !== 'draft')
                    <details class="ta-fold" wire:ignore.self>
                        <summary>
                            <span>{{ __($l.'conversation_count', ['n' => $hero['messages']]) }}</span>
                            <x-filament::icon icon="heroicon-m-chevron-down" class="ta-chev" />
                        </summary>
                        <div class="ta-fold-body spo-step">
                            <ol class="spo-thread" aria-label="{{ __($l.'conversation') }}">
                                @forelse ($this->thread() as $item)
                                    @if ($item['type'] === 'event')
                                        <li class="spo-event"><span>{{ $item['text'] }} · {{ $item['at'] }}</span></li>
                                    @else
                                        <li class="spo-msg" data-mine="{{ $item['mine'] ? 'true' : 'false' }}">
                                            <header><strong>{{ $item['who'] }}</strong><span>{{ $item['at'] }}</span></header>
                                            <p>{{ $item['body'] }}</p>
                                        </li>
                                    @endif
                                @empty
                                    <li class="sp-muted">{{ __($l.'no_messages') }}</li>
                                @endforelse
                            </ol>
                            <form class="ta-reply" wire:submit="comment">
                                <textarea wire:model="reply" placeholder="{{ __($l.'reply') }}" aria-label="{{ __($l.'reply') }}"></textarea>
                                <x-filament::button type="submit" size="sm" color="gray">{{ __($l.'send_reply') }}</x-filament::button>
                            </form>
                            @error('reply') <small style="color: var(--danger-600)">{{ $message }}</small> @enderror
                        </div>
                    </details>
                @endif
            </section>
        @elseif ($hero && $hero['kind'] === 'upcoming')
            <section class="ta-hero" data-stage="upcoming" data-upcoming>
                <div class="ta-head">
                    <span class="ta-icon"><x-filament::icon :icon="$icon" /></span>
                    <div>
                        <h2>{{ $hero['title'] }}</h2>
                        <p>{{ $hero['when'] }} · {{ __($l.'hero.note') }}</p>
                    </div>
                </div>
                <p class="ta-numbers">
                    <span class="ta-sr">{{ $hero['before'] !== null ? __('scheduling::review.summary.hours', ['before' => $hero['before'], 'after' => $hero['after'], 'arrow' => __('scheduling::shifts.arrow')]) : __($l.'hours', ['h' => $hero['after']]) }}</span>
                    @if ($hero['before'] !== null)
                        <b class="ta-before" aria-hidden="true" @if ($whole($hero['before'])) data-count="{{ $hero['before'] }}" @endif>{{ $hero['before'] }}</b>
                        <x-filament::icon icon="heroicon-m-arrow-long-right" class="ta-arrow" aria-hidden="true" />
                    @endif
                    <b aria-hidden="true" @if ($whole($hero['after'])) data-count="{{ $hero['after'] }}" @endif>{{ $hero['after'] }}</b>
                    <small aria-hidden="true">{{ __($l.'hero.hours_a_week') }}</small>
                </p>
            </section>
        @elseif ($hero && $hero['kind'] === 'rejected')
            <section class="ta-hero" data-stage="rejected" data-hero>
                <div class="ta-head">
                    <span class="ta-icon"><x-filament::icon :icon="$icon" /></span>
                    <div>
                        <h2>{{ $hero['title'] }}</h2>
                        @if ($hero['reason'])<p class="ta-reason">{{ __($l.'hero.reason', ['reason' => $hero['reason']]) }}</p>@endif
                        <p>{{ $hero['text'] }}</p>
                    </div>
                </div>
            </section>
        @endif

        <x-filament::section :heading="__($l.'week')">
            @php $tabs = $weeks['tabs']; @endphp
            @if (count($tabs) > 1)
                <div x-data="{ week: @js($tabs[0]['key']) }">
                    <div class="ta-week-top">
                        <div class="ta-switch" role="tablist" aria-label="{{ __($l.'which_week') }}">
                            @foreach ($tabs as $tab)
                                <button type="button" role="tab" id="ta-tab-{{ $tab['key'] }}" aria-controls="ta-panel-{{ $tab['key'] }}"
                                    x-on:click="week = @js($tab['key'])" x-bind:aria-selected="week === @js($tab['key']) ? 'true' : 'false'"
                                    aria-selected="{{ $loop->first ? 'true' : 'false' }}">{{ $tab['tab'] }}</button>
                            @endforeach
                        </div>
                        @foreach ($tabs as $tab)
                            <small x-show="week === @js($tab['key'])" @unless ($loop->first) style="display: none" @endunless>{{ $tab['hours'] }}</small>
                        @endforeach
                    </div>
                    @foreach ($tabs as $tab)
                        <div class="ta-panel" role="tabpanel" id="ta-panel-{{ $tab['key'] }}" aria-labelledby="ta-tab-{{ $tab['key'] }}"
                            x-show="week === @js($tab['key'])" @unless ($loop->first) style="display: none" @endunless>
                            @include('scheduling::filament.teacher.availability-week', ['rows' => $tab['rows'], 'compare' => $tab['key'] === 'upcoming', 'key' => $tab['key']])
                        </div>
                    @endforeach
                </div>
            @elseif (count($tabs) === 1)
                <div class="ta-week-top">
                    <small>{{ $weeks['quiet'] }}</small>
                    <small>{{ $tabs[0]['hours'] }}</small>
                </div>
                @include('scheduling::filament.teacher.availability-week', ['rows' => $tabs[0]['rows'], 'key' => $tabs[0]['key']])
            @else
                <p class="sp-muted">{{ __($l.'no_current') }}</p>
            @endif
        </x-filament::section>

        @if (! $proposal)
            @if ($this->editing)
                <x-filament::section>
                    @include('scheduling::filament.teacher.availability-form')
                </x-filament::section>
            @else
                <div class="ta-propose">
                    <x-filament::button color="gray" outlined icon="heroicon-o-plus" wire:click="startEditing">{{ __($l.'propose_change') }}</x-filament::button>
                    @if ($built = $this->builtOn())
                        <span class="sp-muted">{{ $built }}</span>
                    @endif
                </div>
            @endif
        @endif

        @if ($history !== [])
            <details class="ta-fold ta-history" wire:ignore.self data-history>
                <summary>
                    <span>{{ __($l.'history', ['n' => count($history)]) }}</span>
                    <x-filament::icon icon="heroicon-m-chevron-down" class="ta-chev" />
                </summary>
                <ul class="ta-history-list">
                    @foreach ($history as $past)
                        @php $status = $past->status->value; @endphp
                        <li data-status="{{ $status }}">
                            <span class="sp-pill" data-status="{{ $status }}">{{ $status === 'superseded' ? __($l.'superseded') : __('scheduling::shifts.statuses.'.$status) }}</span>
                            <div>
                                {{ __($l.'asked_from', ['date' => $this->day($past->effective_from)]) }}
                                @if ($at = $this->decidedAt($past))<small>{{ $at }}</small>@endif
                                @if ($status === 'rejected' && $past->decision_note)<small class="ta-reason">{{ __($l.'hero.reason', ['reason' => $past->decision_note]) }}</small>@endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif
    </div>
</x-filament-panels::page>
