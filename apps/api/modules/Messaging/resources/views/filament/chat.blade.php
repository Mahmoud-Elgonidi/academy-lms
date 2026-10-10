@php
    $l = 'messaging::screens.';
    $look = $this->look();
    $open = $this->opened;
    $rows = $this->rows;
@endphp
<x-filament-panels::page>
    @include('messaging::filament.styles')
    <div data-live-channel="{{ $this->channel() }}" hidden></div>

    <p class="ms-note"><x-filament::icon icon="heroicon-m-eye" />{{ __($l.'watched.'.$look) }}</p>

    <div class="ms-chat" @if ($open) data-open @endif>
        <div class="ms-list lms-stagger" role="list">
            @forelse ($rows as $r)
                <button type="button" role="listitem" class="ms-card ms-item lms-lift" wire:click="pick('{{ $r['with'] }}')" wire:key="row-{{ $r['with'] }}" aria-current="{{ ($open['with'] ?? null) === $r['with'] ? 'true' : 'false' }}">
                    <span class="ms-avatar" aria-hidden="true">{{ $r['initial'] }}</span>
                    <span class="ms-item-main">
                        <strong>{{ $r['name'] }}</strong>
                        <small>{{ $r['excerpt'] ?? __($l.'start') }}</small>
                    </span>
                    <span class="ms-item-side">
                        @if ($r['ago'])<span>{{ $r['ago'] }}</span>@endif
                        @if ($r['unread'] > 0)<b class="ms-unread">{{ $r['unread'] }}</b>@elseif ($r['readOnly'])<x-filament::icon icon="heroicon-m-lock-closed" class="ms-lock" />@endif
                    </span>
                </button>
            @empty
                <div class="ms-empty">{{ __($l.'empty.'.($look === 'teacher' ? 'teacher' : 'family')) }}</div>
            @endforelse
        </div>

        @if ($open)
            <section class="ms-card ms-box" wire:key="open-{{ $open['with'] }}">
                <header class="ms-head">
                    <x-filament::icon-button class="ms-back" icon="heroicon-m-arrow-left" color="gray" wire:click="back" :label="__($l.'back')" />
                    <div>
                        <strong>{{ $open['name'] }}</strong>
                        @if ($open['others'])<small>{{ __($l.'with', ['names' => $open['others']]) }}</small>@endif
                    </div>
                </header>

                <div class="ms-thread" data-thread data-last="{{ $open['lines'] ? end($open['lines'])['id'] : '' }}">
                    @forelse ($open['lines'] as $line)
                        <div class="ms-line" data-side="{{ $line['side'] }}" @if ($line['mine']) data-mine @endif wire:key="line-{{ $line['id'] }}">
                            <div class="ms-bubble">{{ $line['body'] }}</div>
                            @if ($line['hidden'])<span class="ms-hidden"><x-filament::icon icon="heroicon-m-eye-slash" />{{ $line['hidden'] }}</span>@endif
                            <small>@unless ($line['mine']){{ $line['author'] }} · {{ $line['sideLabel'] }} · @endunless<time title="{{ $line['when'] }}">{{ $line['ago'] }}</time></small>
                        </div>
                    @empty
                        <p class="ms-first">{{ __($l.'first.'.$look) }}</p>
                    @endforelse
                </div>

                @if ($open['whyNot'])
                    <p class="ms-why">{{ $open['whyNot'] }}</p>
                @else
                    <form class="ms-compose" wire:submit="send">
                        <textarea wire:model="body" maxlength="{{ \Modules\Messaging\MessagingRules::MAX_LENGTH }}" rows="1" aria-label="{{ __($l.'write.'.$look) }}" placeholder="{{ __($l.'write.'.$look) }}" x-data x-on:keydown.enter="if (! $event.shiftKey && ! $event.isComposing) { $event.preventDefault(); $el.form.requestSubmit() }"></textarea>
                        <x-filament::button type="submit" icon="heroicon-m-paper-airplane" wire:loading.attr="disabled" wire:target="send">{{ __($l.'send') }}</x-filament::button>
                    </form>
                    <p class="ms-hint">{{ __($l.'enter_hint') }}</p>
                @endif
            </section>
        @endif
    </div>
</x-filament-panels::page>

@script
<script>
    // A message comes in, or is read elsewhere: the page follows the person's own live channel (LmsLive).
    const el = $wire.$el.querySelector('[data-live-channel]');
    if (el && window.LmsLive) {
        window.LmsLive.watch({ el, channel: el.dataset.liveChannel, event: '.messages.changed', refresh: () => $wire.$refresh(), every: 120000 });
    }
    // The newest message stays in view when one arrives.
    let last = null;
    const follow = () => {
        const thread = $wire.$el.querySelector('[data-thread]');
        if (thread && thread.dataset.last !== last) {
            last = thread.dataset.last;
            thread.scrollTop = thread.scrollHeight;
        }
    };
    follow();
    Livewire.hook('morphed', ({ component }) => { if (component.id === $wire.$id) follow(); });
</script>
@endscript
