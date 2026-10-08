@php
    $l = 'support::tickets.page.';
    $v = $this->screen;
@endphp
<x-filament-panels::page>
    @include('support::filament.styles')
    <div data-live-channel="{{ $v['channel'] }}" hidden></div>

    <div class="sp-strip">
        <span class="sp-pill" data-status="{{ $v['status'] }}">{{ $v['statusLabel'] }}</span>
        <span class="sp-pill" data-kind="{{ $v['kind'] }}">{{ $v['kindLabel'] }}</span>
        <span class="sp-pill" data-priority="{{ $v['priority'] }}">{{ $v['priorityLabel'] }}</span>
        @if ($v['target'])<span class="sp-pill" data-target="{{ $v['target']['state'] }}">{{ $v['target']['text'] }}</span>@endif
    </div>

    <div class="sp-ticket">
        <aside class="sp-card sp-box">
            <div class="sp-facts">
                <div>
                    <small>{{ __($l.'requester') }}</small>
                    <span>{{ $v['requester'] }} · {{ $v['side'] === 'teacher' ? __($l.'from_teacher') : __($l.'from_family') }}</span>
                    @if ($v['writtenBy'])<small>{{ __($l.'written_by', ['name' => $v['writtenBy']]) }}</small>@endif
                </div>
                @if ($v['student'])
                    <div><small>{{ __($l.'learner') }}</small><span>{{ $v['student'] }}</span></div>
                @endif
                <div>
                    <small>{{ __($l.'lesson') }}</small>
                    @if ($v['lesson'])
                        <span>{{ $v['lesson']['subject'] }} · {{ $v['lesson']['when'] }}</span>
                        <small>{{ $v['side'] === 'teacher' ? $v['lesson']['student'] : $v['lesson']['teacher'] }} · {{ $v['lesson']['status'] }}</small>
                    @else
                        <span class="sp-muted">{{ __($l.'no_lesson') }}</span>
                    @endif
                </div>
                <div>
                    <small>{{ __($l.'classify') }} @if ($v['active']){{ $this->classifyAction }}@endif</small>
                    <span>{{ $v['kindLabel'] }} · {{ $v['priorityLabel'] }}</span>
                </div>
                <div>
                    <small>{{ __($l.'assignee') }} {{ $this->assignAction }}</small>
                    <span>{{ $v['assignee'] ?? __('support::tickets.queue.nobody') }}</span>
                    @if (! $v['mine'] && $v['active'])
                        <div><x-filament::button size="xs" color="gray" wire:click="take" icon="heroicon-m-hand-raised">{{ __($l.'take') }}</x-filament::button></div>
                    @endif
                </div>
                <div><small>{{ __($l.'opened_at') }}</small><span>{{ $v['opened'] }}</span></div>
                <div><small>{{ __($l.'first_answer') }}</small><span>{{ $v['firstAnswer'] ?? __($l.'no_answer') }}</span></div>
            </div>

            @if ($v['complaint'])
                @php $c = $v['complaint']; @endphp
                <div class="sp-complaint" data-status="{{ $c['status'] }}">
                    <div class="sp-strip" style="justify-content: space-between">
                        <strong>{{ __($l.'complaint') }}</strong>
                        <span class="sp-pill" data-complaint="{{ $c['status'] }}">{{ $c['label'] }}</span>
                    </div>
                    <p>{{ $c['teacher'] ? __($l.'complaint_about', ['name' => $c['teacher']]) : __($l.'complaint_no_teacher') }}</p>
                    @if ($c['status'] === 'pending')
                        <p>{{ __($l.'complaint_pending') }}</p>
                    @else
                        <p>{{ $c['decided'] }}</p>
                        @if ($c['note'])<p class="sp-note">{{ $c['note'] }}</p>@endif
                    @endif
                    @if ($c['canDecide'])
                        <div class="sp-actions">
                            @if ($c['status'] !== 'validated'){{ ($this->decideAction)(['validated' => true]) }}@endif
                            @if ($c['status'] !== 'dismissed'){{ ($this->decideAction)(['validated' => false]) }}@endif
                        </div>
                    @endif
                </div>
            @endif
        </aside>

        <section class="sp-card sp-box">
            @include('support::filament.thread', ['lines' => $v['lines'], 'mineLabel' => '', 'theirsLabel' => $v['requester']])

            @if ($v['status'] === 'closed')
                <p class="sp-muted">{{ __($l.'closed_note') }}</p>
            @else
                <form class="sp-compose" data-mode="{{ $this->mode }}" wire:submit="send">
                    <div class="sp-compose-row">
                        <div class="sp-seg" role="group">
                            <button type="button" wire:click="$set('mode', 'reply')" aria-pressed="{{ $this->mode === 'reply' ? 'true' : 'false' }}">{{ __($l.'reply') }}</button>
                            <button type="button" wire:click="$set('mode', 'note')" aria-pressed="{{ $this->mode === 'note' ? 'true' : 'false' }}">{{ __($l.'note') }}</button>
                        </div>
                        <label class="sp-attach">
                            <x-filament::icon icon="heroicon-m-paper-clip" />
                            <span>{{ __($l.'attach') }}</span>
                            <input type="file" wire:model="attachment" accept="image/*,application/pdf">
                        </label>
                    </div>
                    <textarea wire:model="body" maxlength="5000" aria-label="{{ __($l.$this->mode) }}"
                        placeholder="{{ $this->mode === 'note' ? __($l.'note_placeholder') : __($l.'reply_placeholder', ['name' => $v['requester']]) }}"></textarea>
                    @if ($this->attachment)
                        <span class="sp-chosen"><span>{{ $this->attachment->getClientOriginalName() }}</span><button type="button" wire:click="forgetAttachment" aria-label="×">×</button></span>
                    @endif
                    @error('attachment')<p class="sp-error">{{ $message }}</p>@enderror
                    <div class="sp-compose-row" style="justify-content: flex-end">
                        @if ($this->mode === 'note')
                            <x-filament::button type="submit" size="sm" color="warning" icon="heroicon-m-lock-closed">{{ __($l.'save_note') }}</x-filament::button>
                        @else
                            @if ($v['canResolve'])
                                <x-filament::button type="button" size="sm" color="gray" wire:click="send(true)" icon="heroicon-m-check">{{ __($l.'resolve') }}</x-filament::button>
                            @endif
                            <x-filament::button type="submit" size="sm" icon="heroicon-m-paper-airplane">{{ __($l.'send') }}</x-filament::button>
                        @endif
                    </div>
                </form>
            @endif
        </section>
    </div>
</x-filament-panels::page>

@script
<script>
    // The family answers, a colleague writes a note or decides: the ticket follows the academy's support channel (LmsLive).
    const el = $wire.$el.querySelector('[data-live-channel]');
    if (el && window.LmsLive) {
        window.LmsLive.watch({ el, channel: el.dataset.liveChannel, event: '.changed', refresh: () => $wire.$refresh(), every: 60000 });
    }
</script>
@endscript
