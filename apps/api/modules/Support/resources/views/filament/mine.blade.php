@php
    $m = 'support::tickets.mine.';
    $list = $this->mine;
    $open = $this->opened;
@endphp
<x-filament-panels::page>
    @include('support::filament.styles')
    <div data-live-channel="{{ $this->channel() }}" hidden></div>

    <div>{{ $this->newTicketAction }}</div>

    <div class="sp-mine" @if ($open) data-open @endif>
        <div class="sp-mine-list lms-stagger" aria-label="{{ __($m.'list') }}">
            @forelse ($list as $t)
                <button type="button" class="sp-card sp-mine-item lms-lift" wire:click="show('{{ $t['id'] }}')" wire:key="mine-{{ $t['id'] }}" aria-current="{{ ($open['id'] ?? null) === $t['id'] ? 'true' : 'false' }}">
                    <small>{{ $t['reference'] }} · {{ $t['kind'] }}</small>
                    <strong>{{ $t['subject'] }}</strong>
                    <span class="sp-strip">
                        <span class="sp-pill" data-status="{{ $t['status'] }}">{{ $t['statusLabel'] }}</span>
                        @if ($t['yourTurn'])<span class="sp-turn">{{ __($m.'your_turn') }}</span>@endif
                    </span>
                    <small>{{ $t['last'] }}</small>
                </button>
            @empty
                <div class="sp-empty">{{ __($m.'empty') }}</div>
            @endforelse
        </div>

        @if ($open)
            <section class="sp-card sp-box" wire:key="open-{{ $open['id'] }}">
                <div class="sp-compose-row">
                    <x-filament::link tag="button" wire:click="show(null)" icon="heroicon-m-arrow-left" size="sm" class="sp-back">{{ __($m.'back') }}</x-filament::link>
                    <span class="sp-pill" data-status="{{ $open['status'] }}">{{ $open['statusLabel'] }}</span>
                </div>
                <div>
                    <small class="sp-muted">{{ $open['reference'] }} · {{ $open['kind'] }}@if ($open['lesson']) · {{ $open['lesson'] }}@endif</small>
                    <h3 style="font-size: 1.05rem; font-weight: 700; margin-top: .15rem">{{ $open['subject'] }}</h3>
                </div>

                @include('support::filament.thread', ['lines' => $open['lines'], 'mineLabel' => __($m.'you'), 'theirsLabel' => __($m.'academy')])

                @if ($open['canReopen'])
                    <div class="sp-complaint" data-status="dismissed">
                        <p>{{ __($m.'reopen_hint', ['date' => $open['reopenUntil']]) }}</p>
                        <div><x-filament::button size="sm" color="gray" wire:click="reopen" icon="heroicon-m-arrow-uturn-left">{{ __($m.'reopen') }}</x-filament::button></div>
                    </div>
                @endif

                @if ($open['canReply'])
                    <form class="sp-compose" wire:submit="sendReply">
                        <textarea wire:model="body" maxlength="5000" aria-label="{{ __($m.'reply') }}" placeholder="{{ __($m.'reply_placeholder') }}"></textarea>
                        @if ($this->attachment)
                            <span class="sp-chosen"><span>{{ $this->attachment->getClientOriginalName() }}</span><button type="button" wire:click="forgetAttachment" aria-label="×">×</button></span>
                        @endif
                        @error('attachment')<p class="sp-error">{{ $message }}</p>@enderror
                        <div class="sp-compose-row">
                            <label class="sp-attach">
                                <x-filament::icon icon="heroicon-m-paper-clip" />
                                <span>{{ __('support::tickets.page.attach') }}</span>
                                <input type="file" wire:model="attachment" accept="image/*,application/pdf">
                            </label>
                            <x-filament::button type="submit" size="sm" icon="heroicon-m-paper-airplane">{{ __($m.'send') }}</x-filament::button>
                        </div>
                    </form>
                @else
                    <p class="sp-muted">{{ __($m.'closed') }}</p>
                @endif

                @if ($open['canClose'])
                    <div>
                        <x-filament::link tag="button" color="gray" size="sm" wire:click="closeTicket" wire:confirm="{{ __($m.'close_hint') }}">{{ __($m.'close') }}</x-filament::link>
                    </div>
                @endif
            </section>
        @endif
    </div>
</x-filament-panels::page>

@script
<script>
    // The academy answers: the page follows the person's own live channel (LmsLive).
    const el = $wire.$el.querySelector('[data-live-channel]');
    if (el && window.LmsLive) {
        window.LmsLive.watch({ el, channel: el.dataset.liveChannel, event: '.tickets.changed', refresh: () => $wire.$refresh(), every: 120000 });
    }
</script>
@endscript
