@php
    $l = 'platform::games.';
    $d = $this->record;
    $facts = $this->facts;
    $preview = $this->preview;
    $issues = $this->issues;
    $turns = $this->turns;
    $designing = $d->status === 'designing';
    $open = $d->isOpen();
@endphp
<x-filament-panels::page>
    @include('platform::console.styles')
    @include('platform::games.styles')
    <div class="gd" data-live-channel="{{ $this->channel() }}" data-live-now="{{ $d->updated_at->getTimestamp() }}">
        <div class="gd-grid">
            <section class="pc-base gd-play">
                <header>
                    <h3>{{ __($l.'draft.preview') }}</h3>
                    <div class="gs-stages" role="group" aria-label="{{ __($l.'draft.look') }}">
                        @foreach (\Modules\Platform\Actions\Games\StartGameDraft::STAGES as $stage)
                            <button type="button" wire:click="setStage('{{ $stage }}')" aria-pressed="{{ $this->stage === $stage ? 'true' : 'false' }}">{{ __('shared::games.stages.'.$stage) }}</button>
                        @endforeach
                    </div>
                    <div class="gd-tools">
                        <x-filament::icon-button icon="heroicon-m-arrow-path" color="gray" :label="__($l.'draft.replay')" data-game-restart />
                        <x-filament::icon-button icon="heroicon-m-speaker-wave" color="gray" :label="__($l.'draft.sound')" data-game-sound />
                    </div>
                </header>
                @if ($preview)
                    <div class="gs-phone" wire:key="preview-{{ $preview['src'] }}-{{ $this->stage }}">
                        <div class="gs-player" wire:ignore data-game-player data-src="{{ $preview['src'] }}" data-items="{{ json_encode($preview['items'], JSON_UNESCAPED_UNICODE) }}"
                             data-stage="{{ $this->stage }}" data-locale="ar" data-dir="rtl" data-title="{{ $this->getTitle() }}"></div>
                    </div>
                @else
                    <div class="gd-waiting">
                        <div>
                            <div class="gd-orbit"><i></i><i></i><span>{{ $d->island->emoji }}</span></div>
                            <b>{{ $designing ? __($l.'designer.working') : $this->failure() }}</b>
                            @if ($designing)<small>{{ __($l.'designer.working_hint') }}</small>@endif
                        </div>
                    </div>
                @endif
                <p class="gs-hint">{{ __($l.'draft.preview_hint') }}</p>
                <div class="gd-events" wire:ignore data-game-events
                     data-l-ready="{{ __($l.'draft.event.ready') }}" data-l-right="{{ __($l.'draft.event.answer_right') }}" data-l-wrong="{{ __($l.'draft.event.answer_wrong') }}"
                     data-l-finish="{{ __($l.'draft.event.finish') }}" data-l-error="{{ __($l.'draft.event.error') }}">
                    <h4>
                        <span>{{ __($l.'draft.events') }}</span>
                        @if ($open && ! $designing)
                            <x-filament::button size="xs" color="danger" outlined icon="heroicon-m-paper-airplane" data-send-errors hidden>{{ __($l.'draft.send_errors') }}</x-filament::button>
                        @endif
                    </h4>
                    <p data-events-empty>{{ __($l.'draft.events_none') }}</p>
                    <ol></ol>
                </div>
            </section>

            <aside class="gd-side lms-stagger">
                <section class="pc-base gd-box">
                    <div class="gd-status" data-state="{{ $d->status }}">
                        @if ($designing)
                            <span class="gd-dots" aria-hidden="true"><i></i><i></i><i></i></span>
                            <div>
                                <strong>{{ __($l.'designer.working') }}</strong>
                                <small class="gs-hint">{{ __($l.'designer.since', ['time' => $d->updated_at->diffForHumans()]) }}</small>
                            </div>
                        @else
                            <span class="gs-state" data-state="{{ $d->status }}">{{ __($l.'status.'.$d->status) }}</span>
                            @if ($d->status === 'failed')<span>{{ $this->failure() }}</span>@endif
                        @endif
                    </div>
                    @if ($this->stale())
                        <div class="gd-actions">
                            <span class="gs-hint">{{ __($l.'designer.stale') }}</span>
                            <x-filament::button size="xs" color="gray" wire:click="stop">{{ __($l.'designer.stop') }}</x-filament::button>
                        </div>
                    @endif
                    @unless ($open)
                        <p class="gs-hint">{{ __($l.'draft.closed', ['status' => __($l.'status.'.$d->status)]) }}</p>
                    @endunless
                </section>

                @if ($issues !== [])
                    <section class="pc-base gd-box">
                        <h3>{{ __($l.'draft.issues') }}</h3>
                        <p class="gs-hint" style="text-align: start">{{ __($l.'draft.issues_hint') }}</p>
                        <ul class="gd-issues">
                            @foreach ($issues as $issue)
                                <li><span>{{ $issue['label'] }}</span><code>{{ $issue['detail'] }}</code></li>
                            @endforeach
                        </ul>
                        @if ($open && ! $designing)
                            <div class="gd-actions"><x-filament::button size="sm" color="danger" icon="heroicon-m-paper-airplane" wire:click="sendIssues">{{ __($l.'draft.send_issues') }}</x-filament::button></div>
                        @endif
                    </section>
                @endif

                <section class="pc-base gd-box">
                    <h3>{{ __($l.'draft.conversation') }}</h3>
                    <div class="gd-chat">
                        @foreach ($turns as $turn)
                            <div class="gd-turn" data-role="{{ $turn['role'] }}" wire:key="turn-{{ $loop->index }}">
                                <small>{{ $turn['role'] === 'admin' ? __($l.'draft.you') : __($l.'designer.name') }} · {{ $turn['when'] }}</small>
                                <p>{{ $turn['body'] }}</p>
                            </div>
                        @endforeach
                        @if ($designing)
                            <div class="gd-turn" data-role="designer"><p><span class="gd-dots" aria-label="{{ __($l.'designer.working') }}"><i></i><i></i><i></i></span></p></div>
                        @endif
                    </div>
                    @if ($open)
                        <form class="gd-form" wire:submit="send">
                            <textarea wire:model="changes" rows="4" maxlength="4000" placeholder="{{ __($l.'draft.changes_placeholder') }}" aria-label="{{ __($l.'attributes.changes') }}" @disabled($designing)></textarea>
                            @error('changes')<p class="gs-hint" style="color: var(--danger-600); text-align: start">{{ $message }}</p>@enderror
                            <footer>
                                @if ($d->status === 'failed')
                                    <x-filament::button size="sm" color="gray" icon="heroicon-m-arrow-path" wire:click="retry">{{ __($l.'draft.retry') }}</x-filament::button>
                                @endif
                                <x-filament::button type="submit" size="sm" icon="heroicon-m-paper-airplane" :disabled="$designing">{{ __($l.'draft.send') }}</x-filament::button>
                            </footer>
                        </form>
                    @endif
                </section>

                <section class="pc-base gd-box">
                    <h3>{{ __($l.'draft.facts') }}</h3>
                    <dl class="gd-facts">
                        @if ($facts['summary'])<dt>{{ __($l.'draft.summary') }}</dt><dd>{{ $facts['summary'] }}</dd>@endif
                        @if ($facts['how'])<dt>{{ __($l.'draft.how') }}</dt><dd>{{ $facts['how'] }}</dd>@endif
                        <dt>{{ __($l.'stages_label') }}</dt><dd>{{ implode('، ', $facts['stages']) }}</dd>
                        <dt>{{ __($l.'kinds_label') }}</dt><dd>{{ implode('، ', $facts['kinds']) }}</dd>
                        @if ($facts['min'])<dt>{{ __($l.'draft.items') }}</dt><dd>{{ __($l.'draft.items_value', ['min' => $facts['min'], 'max' => $facts['max']]) }}</dd>@endif
                        @if ($facts['topic'])<dt>{{ __($l.'attributes.topic') }}</dt><dd>{{ $facts['topic'] }}</dd>@endif
                        @if ($facts['model'])<dt>{{ __($l.'draft.model') }}</dt><dd dir="ltr">{{ $facts['model'] }}</dd>@endif
                        @if ($facts['tokens'])<dt>{{ __($l.'draft.tokens') }}</dt><dd>{{ number_format($facts['tokens']) }}</dd>@endif
                    </dl>
                    @if ($open)
                        <div class="gd-actions">
                            {{ $this->publishAction }}
                            {{ $this->discardAction }}
                        </div>
                    @endif
                </section>
            </aside>
        </div>
    </div>
    <x-filament-actions::modals />
</x-filament-panels::page>

@script
<script>
    // The draft finishes a round (or someone else sends changes): the page follows the studio's channel (LmsLive).
    const live = $wire.$el.querySelector('[data-live-channel]');
    if (live && window.LmsLive) {
        window.LmsLive.watch({ el: live, channel: live.dataset.liveChannel, event: '.changed', refresh: () => $wire.refreshDraft(), every: 30000 });
    }

    // The preview: mount the player, log what the game reports, restart, sound, and send its errors to the designer.
    const log = $wire.$el.querySelector('[data-game-events]');
    const list = log.querySelector('ol');
    const errors = [];
    let muted = false;
    const add = (kind, text) => {
        log.querySelector('[data-events-empty]')?.remove();
        const li = document.createElement('li');
        li.dataset.kind = kind;
        li.textContent = text;
        list.prepend(li);
        if (kind === 'error') {
            errors.push(text);
            log.querySelector('[data-send-errors]')?.removeAttribute('hidden');
        }
    };
    const player = () => $wire.$el.querySelector('[data-game-player]');
    const mount = () => { const root = player(); if (root) window.LmsGame?.mount(root); };
    $wire.$el.addEventListener('lms-game:ready', () => add('ready', log.dataset.lReady));
    $wire.$el.addEventListener('lms-game:answer', (e) => add(e.detail.correct ? 'right' : 'wrong', (e.detail.correct ? log.dataset.lRight : log.dataset.lWrong) + ' · ' + e.detail.item));
    $wire.$el.addEventListener('lms-game:finish', () => add('finish', log.dataset.lFinish));
    $wire.$el.addEventListener('lms-game:error', (e) => add('error', log.dataset.lError + ': ' + e.detail.message + (e.detail.line ? ' (line ' + e.detail.line + ')' : '')));
    $wire.$el.addEventListener('click', (e) => {
        if (e.target.closest('[data-game-restart]')) { const root = player(); if (root) window.LmsGame?.of(root)?.restart(); }
        if (e.target.closest('[data-game-sound]')) { muted = ! muted; const root = player(); if (root) window.LmsGame?.of(root)?.mute(muted); }
        if (e.target.closest('[data-send-errors]')) { $wire.sendErrors(errors.splice(0)); }
    });
    new MutationObserver(mount).observe($wire.$el, { childList: true, subtree: true });
    mount();
</script>
@endscript
