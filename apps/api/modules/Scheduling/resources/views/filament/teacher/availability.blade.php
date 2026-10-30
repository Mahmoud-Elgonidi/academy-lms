{{-- The teacher's hours: the week in force and one approved to start later (the admins' day grid), the proposal in steps with its conversation, and the form. --}}
@php
    $l = 'scheduling::teacher.availability.';
    $proposal = $this->proposal;
    $upcoming = $this->upcoming;
    $steps = $this->steps();
    $status = $proposal?->status->value;
    $hint = match ($status) {
        'draft' => __($l.'draft_hint'),
        'submitted' => __($l.'review_hint'),
        'countered' => __($l.'countered_hint'),
        'changes_requested' => __($l.'changes_hint'),
        default => null,
    };
@endphp
<x-filament-panels::page>
    @include('scheduling::filament.shift-styles')
    <style>
        .ta { display: flex; flex-direction: column; gap: 1rem; }
        .ta-grid { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1rem; }
        @media (min-width: 1024px) { .ta-grid { grid-template-columns: minmax(0, 3fr) minmax(0, 2fr); } }
        .ta-upcoming { display: flex; flex-direction: column; gap: .75rem; padding: 1rem 1.1rem; border-radius: var(--radius-xl); border: 1px solid var(--primary-300); background: var(--primary-50); }
        .ta-upcoming h2 { margin: 0; font-size: 1.05rem; font-weight: 700; color: var(--primary-800); }
        .ta-upcoming p { margin: 0; font-size: .85rem; color: var(--gray-600); }
        .ta-upcoming .sw { background: white; }
        .ta-change { align-self: flex-start; padding: .15rem .65rem; border-radius: 999px; font-size: .8rem; font-weight: 600; background: white; color: var(--primary-700); border: 1px solid var(--primary-200); font-variant-numeric: tabular-nums; }
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
        .ta .spo-msg[data-mine="true"] { align-self: flex-end; background: var(--primary-50); border-start-start-radius: var(--radius-xl); border-start-end-radius: .3rem; }
        .ta-reply { display: flex; gap: .5rem; align-items: flex-end; }
        .ta-reply textarea { flex: 1; min-height: 2.6rem; border: 1px solid var(--gray-300); border-radius: var(--radius-lg, .5rem); padding: .5rem .6rem; font: inherit; background: transparent; }
        .dark .ta-upcoming { background: color-mix(in oklab, var(--primary-500) 10%, transparent); border-color: var(--primary-800); }
        .dark .ta-upcoming h2 { color: var(--primary-300); }
        .dark .ta-upcoming .sw, .dark .ta-change, .dark .ta-steps b { background: var(--gray-900); }
        .dark .ta .spo-msg[data-mine="true"] { background: color-mix(in oklab, var(--primary-500) 18%, transparent); }
    </style>

    <div class="ta">
        <x-filament::section :heading="__($l.'current')" :description="__($l.'hours', ['h' => round($this->current()->weeklyMinutes() / 60, 1)])">
            @if ($this->current()->blocks === [])
                <p class="sp-muted">{{ __($l.'no_current') }}</p>
            @else
                @include('scheduling::filament.shift-week', ['week' => $this->currentGrid(), 'left' => __($l.'columns.current'), 'right' => '', 'single' => true])
            @endif
        </x-filament::section>

        @if ($upcoming)
            <section class="ta-upcoming" data-upcoming>
                <h2>{{ __($l.'upcoming', ['date' => $upcoming['from']]) }}</h2>
                <p>{{ __($l.'upcoming_hint') }}</p>
                <span class="ta-change">{{ $upcoming['summary']['hours'] }}</span>
                @include('scheduling::filament.shift-week', ['week' => $upcoming['week'], 'left' => __($l.'columns.current'), 'right' => __($l.'columns.upcoming')])
            </section>
        @endif

        <div class="ta-grid">
            <x-filament::section :heading="__($l.'propose')" :description="__($l.'propose_hint')">
                <form wire:submit="send" style="display: flex; flex-direction: column; gap: 1rem">
                    {{ $this->form }}
                    <div><x-filament::button type="submit" icon="heroicon-o-paper-airplane">{{ __($l.'send') }}</x-filament::button></div>
                </form>
            </x-filament::section>

            @if ($proposal && $steps)
                <x-filament::section :heading="__($l.'your_proposal')">
                    <div class="spo">
                        <ol class="ta-steps" data-tone="{{ $steps['tone'] }}">
                            @foreach ($steps['steps'] as $i => $step)
                                <li data-state="{{ $step['state'] }}" @if ($step['state'] === 'now') aria-current="step" @endif>
                                    <b>{{ $step['state'] === 'done' ? '✓' : $i + 1 }}</b>{{ $step['label'] }}
                                </li>
                            @endforeach
                        </ol>
                        <div class="spo-facts">
                            <span class="sp-pill" data-status="{{ $status }}">{{ __('scheduling::shifts.statuses.'.$status) }}</span>
                            <span><small>{{ __($l.'from_date') }}</small> {{ $proposal->effective_from->locale(app()->getLocale())->translatedFormat('l j F') }}</span>
                        </div>
                        @if ($hint)
                            <p class="spo-note" @if (in_array($status, ['countered', 'changes_requested'], true)) data-tone="info" @endif>{{ $hint }}</p>
                        @endif
                        @if ($status === 'countered')
                            @include('scheduling::filament.shift-week', ['week' => app(\Modules\Scheduling\Filament\ShiftProposalView::class)->compare($proposal->revision > 1 ? $proposal->shifts($proposal->revision - 1) : $this->current(), $proposal->shifts()), 'left' => __('scheduling::review.columns.proposed'), 'right' => __('scheduling::shifts.statuses.countered')])
                        @endif

                        @if ($status !== 'draft')
                            <section class="spo-step">
                                <h3>{{ __($l.'conversation') }}</h3>
                                <ol class="spo-thread">
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
                            </section>
                        @endif
                    </div>
                </x-filament::section>
            @endif
        </div>
    </div>
</x-filament-panels::page>
