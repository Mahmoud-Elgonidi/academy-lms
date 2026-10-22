@php
    $l = 'scheduling::teacher.availability.';
    $days = __('scheduling::shifts.days');
    $proposal = $this->proposal;
    $current = $this->week();
@endphp
<x-filament-panels::page>
    <style>
        .ta-grid { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1rem; }
        @media (min-width: 1024px) { .ta-grid { grid-template-columns: minmax(0, 3fr) minmax(0, 2fr); } }
        .ta-week { display: flex; flex-direction: column; gap: .35rem; font-size: .9rem; }
        .ta-week div { display: flex; justify-content: space-between; gap: .75rem; border-bottom: 1px dashed var(--gray-200); padding-bottom: .3rem; }
        .ta-week span:last-child { font-variant-numeric: tabular-nums; color: var(--gray-700); text-align: end; }
        .ta-status { display: inline-flex; padding: .15rem .65rem; border-radius: 999px; font-size: .8rem; background: var(--gray-100); }
        .ta-status[data-s="countered"], .ta-status[data-s="changes_requested"] { background: var(--warning-50); color: var(--warning-800); }
        .ta-status[data-s="submitted"] { background: var(--primary-50); color: var(--primary-800); }
        .ta-thread { display: flex; flex-direction: column; gap: .5rem; }
        .ta-msg { padding: .5rem .7rem; border-radius: var(--radius-lg, .5rem); background: var(--gray-50); font-size: .88rem; }
        .ta-msg[data-mine] { background: var(--primary-50); }
        .ta-msg small { display: block; color: var(--gray-500); font-size: .75rem; margin-bottom: .15rem; }
        .ta-reply { display: flex; gap: .5rem; align-items: flex-end; }
        .ta-reply textarea { flex: 1; min-height: 2.6rem; border: 1px solid var(--gray-300); border-radius: var(--radius-lg, .5rem); padding: .5rem .6rem; font: inherit; background: transparent; }
        .dark .ta-msg { background: var(--gray-800); }
    </style>
    <div class="ta-grid">
        <x-filament::section :heading="__($l.'propose')" :description="__($l.'propose_hint')">
            <form wire:submit="send" style="display: flex; flex-direction: column; gap: 1rem">
                {{ $this->form }}
                <div><x-filament::button type="submit" icon="heroicon-o-paper-airplane">{{ __($l.'send') }}</x-filament::button></div>
            </form>
        </x-filament::section>

        <div style="display: flex; flex-direction: column; gap: 1rem">
            @if ($proposal)
                <x-filament::section :heading="__($l.'your_proposal')">
                    <div style="display: flex; flex-direction: column; gap: .75rem">
                        <div>
                            <span class="ta-status" data-s="{{ $proposal->status->value }}">{{ __('scheduling::shifts.statuses.'.$proposal->status->value) }}</span>
                            <small style="color: var(--gray-500)"> · {{ __($l.'from_date') }}: {{ $proposal->effective_from->locale(app()->getLocale())->translatedFormat('j F Y') }}</small>
                        </div>
                        @if ($proposal->status->value === 'countered')
                            <p style="margin: 0; font-size: .9rem">{{ __($l.'countered_hint') }}</p>
                            <div class="ta-week">
                                @foreach ($this->week($proposal->shifts()) as $day => $blocks)
                                    <div><span>{{ $days[$day] }}</span><span>{{ implode('، ', $blocks) }}</span></div>
                                @endforeach
                            </div>
                        @elseif ($proposal->status->value === 'changes_requested')
                            <p style="margin: 0; font-size: .9rem">{{ __($l.'changes_hint') }}</p>
                        @endif
                        @if ($proposal->comments->isNotEmpty())
                            <div class="ta-thread">
                                @foreach ($proposal->comments as $c)
                                    <div class="ta-msg" @if ($c->author_id === auth()->id()) data-mine @endif>
                                        <small>{{ __('scheduling::shifts.roles.'.$c->author_role) }} · {{ $c->created_at->setTimezone(app(\Modules\SharedKernel\Time\ViewerTimezone::class)->effective())->locale(app()->getLocale())->translatedFormat(\Modules\SharedKernel\Time\Clock::pattern('j/n H:i')) }}</small>
                                        {{ $c->body }}
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        @if ($proposal->status->value !== 'draft')
                            <form class="ta-reply" wire:submit="comment">
                                <textarea wire:model="reply" placeholder="{{ __($l.'reply') }}" aria-label="{{ __($l.'reply') }}"></textarea>
                                <x-filament::button type="submit" size="sm" color="gray">{{ __($l.'send_reply') }}</x-filament::button>
                            </form>
                            @error('reply') <small style="color: var(--danger-600)">{{ $message }}</small> @enderror
                        @endif
                    </div>
                </x-filament::section>
            @endif

            <x-filament::section :heading="__($l.'current')" :description="__($l.'hours', ['h' => round($this->current()->weeklyMinutes() / 60, 1)])">
                <div class="ta-week">
                    @forelse ($current as $day => $blocks)
                        <div><span>{{ $days[$day] }}</span><span>{{ implode('، ', $blocks) }}</span></div>
                    @empty
                        <span style="color: var(--gray-500)">{{ __($l.'no_current') }}</span>
                    @endforelse
                </div>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
