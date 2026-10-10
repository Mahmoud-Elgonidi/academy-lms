@php
    $o = 'messaging::screens.oversight.';
    $board = $this->board;
    $sel = $this->selected;
@endphp
<x-filament-panels::page>
    @include('messaging::filament.styles')
    <div data-live-channel="{{ $this->channel() }}" hidden></div>

    <div class="ms-filters">
        <div class="ms-tabs" role="group">
            @foreach (['all', 'flagged', 'locked'] as $tab)
                <button type="button" wire:click="filter('{{ $tab }}')" aria-pressed="{{ $this->tab === $tab ? 'true' : 'false' }}">
                    {{ __($o.'tabs.'.$tab) }}
                    <span class="ms-count" @if ($tab === 'flagged' && $board['counts']['flagged'] > 0) data-tone="warning" @endif>{{ $board['counts'][$tab] }}</span>
                </button>
            @endforeach
        </div>
        <input type="search" class="ms-input" wire:model.live.debounce.400ms="search" placeholder="{{ __($o.'search') }}" aria-label="{{ __($o.'search') }}">
    </div>

    <div class="ms-chat" @if ($sel) data-open @endif>
        <div class="ms-list lms-stagger" role="list">
            @forelse ($board['rows'] as $r)
                <button type="button" role="listitem" class="ms-card ms-row lms-lift" wire:click="show('{{ $r['id'] }}')" wire:key="c-{{ $r['id'] }}"
                    aria-current="{{ ($sel['conversation']->id ?? null) === $r['id'] ? 'true' : 'false' }}" @if ($r['needsLook']) data-look @endif>
                    <strong>{{ $r['teacher'] }} ↔ {{ $r['student'] }}</strong>
                    <span class="ms-meta">
                        <span>{{ trans_choice($o.'messages', $r['messages'], ['count' => $r['messages']]) }}</span>
                        @if ($r['ago'])<span>· {{ $r['ago'] }}</span>@endif
                        @if ($r['needsLook'])<span class="ms-pill" data-tone="warning">{{ __($o.'needs_look') }}</span>@endif
                        @if ($r['hidden'] > 0)<span class="ms-pill">{{ trans_choice($o.'hidden_count', $r['hidden'], ['count' => $r['hidden']]) }}</span>@endif
                        @if ($r['locked'])<span class="ms-pill" data-tone="danger">{{ __($o.'locked') }}</span>@endif
                    </span>
                </button>
            @empty
                <div class="ms-empty">{{ __($o.'empty') }}</div>
            @endforelse
        </div>

        @if ($sel)
            <section class="ms-card ms-box" wire:key="sel-{{ $sel['conversation']->id }}">
                <header class="ms-head">
                    <x-filament::icon-button class="ms-back" icon="heroicon-m-arrow-left" color="gray" wire:click="close" :label="__('messaging::screens.back')" />
                    <div>
                        <strong>{{ $sel['title'] }}</strong>
                        <small>{{ __($o.'members') }}: {{ collect($sel['members'])->map(fn ($m) => $m['name'].' ('.$m['label'].')')->implode('، ') }}</small>
                    </div>
                    <div class="ms-actions">
                        @if ($sel['conversation']->isLocked()){{ $this->unlockAction }}@else{{ $this->lockAction }}@endif
                    </div>
                </header>
                @if ($sel['lock'])<div class="ms-banner">{{ $sel['lock'] }}</div>@endif

                <div class="ms-thread">
                    @foreach ($sel['lines'] as $line)
                        <div class="ms-line" data-side="{{ $line['side'] }}" @if ($line['side'] === 'teacher') data-mine @endif wire:key="o-{{ $line['id'] }}">
                            <div class="ms-bubble">{{ $line['body'] }}</div>
                            <span class="ms-staff">
                                @if ($line['hidden'])<span class="ms-hidden"><x-filament::icon icon="heroicon-m-eye-slash" />{{ $line['hidden'] }}</span>@endif
                                @if ($line['outsideApp'])<span class="ms-pill" data-tone="warning">{{ __($o.'outside_app') }}</span>@endif
                                @if ($line['canReveal'] && ! isset($this->revealed[$line['id']]))
                                    <button type="button" wire:click="reveal('{{ $line['id'] }}')">{{ __($o.'reveal') }}</button>
                                @endif
                            </span>
                            @isset($this->revealed[$line['id']])
                                <div class="ms-original"><b>{{ __($o.'revealed') }}</b>{{ $this->revealed[$line['id']] }}</div>
                            @endisset
                            <small>{{ $line['author'] }} · {{ $line['sideLabel'] }} · <time title="{{ $line['when'] }}">{{ $line['ago'] }}</time></small>
                        </div>
                    @endforeach
                </div>
                <p class="ms-logged">{{ __($o.'logged') }}</p>
            </section>
        @else
            <div class="ms-card ms-pick">{{ __($o.'pick') }}</div>
        @endif
    </div>
</x-filament-panels::page>

@script
<script>
    // A message is written anywhere in the academy: the list follows the oversight channel (LmsLive).
    const el = $wire.$el.querySelector('[data-live-channel]');
    if (el && window.LmsLive) {
        window.LmsLive.watch({ el, channel: el.dataset.liveChannel, event: '.changed', refresh: () => $wire.$refresh(), every: 120000 });
    }
</script>
@endscript
