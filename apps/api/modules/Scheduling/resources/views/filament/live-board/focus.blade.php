{{-- Layout "focus": problems first with a countdown to what the system will do; details in a side panel. --}}
@php
    $groups = $board['groups'];
    $selected = $board['selected'];
@endphp

<div class="lf">
    <div class="lf-list">
        <div class="lb-card-head lf-head">
            <h2 class="lb-h2 lms-heading">{{ __('scheduling::board.now_and_next') }}</h2>
            <span class="lb-muted">{{ __('scheduling::board.summary', ['attention' => count($groups['attention']), 'fine' => count($groups['fine']), 'upcoming' => count($groups['upcoming']), 'done' => count($groups['done'])]) }}</span>
        </div>

        @if ($groups['attention'] !== [])
            <section class="lf-group">
                <h3 class="lf-group-title"><i class="lf-bullet" data-tone="danger"></i>{{ __('scheduling::board.groups.attention') }}
                    <small>{{ __('scheduling::board.groups.attention_hint') }}</small></h3>
                @foreach ($groups['attention'] as $item)
                    <article class="lf-card" data-severity="{{ $item['severity'] }}" aria-current="{{ ($selected['id'] ?? null) === $item['id'] ? 'true' : 'false' }}" wire:key="f-{{ $item['id'] }}">
                        <div class="lf-card-main">
                            <span class="lf-ring" data-severity="{{ $item['severity'] }}">
                                @if ($item['minutesLeft'] !== null)
                                    <strong class="lms-heading">{{ $item['minutesLeft'] }}</strong><small>{{ __('scheduling::board.minutes') }}</small>
                                @else
                                    <x-filament::icon :icon="$item['issue'] === 'weak_connection' ? 'heroicon-o-wifi' : 'heroicon-o-flag'" class="lb-icon" />
                                @endif
                            </span>
                            <div class="lf-text">
                                <strong>{{ $item['problem'] }}</strong>
                                <span>{{ $item['context'] }}</span>
                                <span class="lb-rule">{{ $item['rule'] }}</span>
                                @if ($item['handled'])
                                    <span class="lb-handled">✓ {{ $item['handled'] }}</span>
                                @endif
                            </div>
                            <button type="button" class="lb-expand" wire:click="select('{{ $item['id'] }}')" aria-label="{{ __('scheduling::board.details') }}">
                                <x-filament::icon icon="heroicon-m-chevron-left" class="lb-icon lf-chevron" />
                            </button>
                        </div>
                        <div class="lf-card-actions">
                            @include('scheduling::filament.live-board.actions', ['item' => $item, 'buttons' => 2])
                        </div>
                    </article>
                @endforeach
            </section>
        @endif

        @if ($groups['fine'] !== [])
            <section class="lf-group">
                <h3 class="lf-group-title"><i class="lf-bullet" data-tone="primary"></i>{{ __('scheduling::board.groups.fine') }}</h3>
                @foreach ($groups['fine'] as $item)
                    <button type="button" class="lf-row" wire:click="select('{{ $item['id'] }}')" aria-pressed="{{ ($selected['id'] ?? null) === $item['id'] ? 'true' : 'false' }}" wire:key="f-{{ $item['id'] }}">
                        <strong class="lb-num">{{ $item['time'] }}</strong>
                        <span class="lf-who">{{ $item['teacher'] }} ← {{ $item['student'] }} <small>· {{ $item['subject'] }}</small></span>
                        @if ($item['typeLabel'])<span class="lb-tag" data-type="{{ $item['type'] }}">{{ $item['typeLabel'] }}</span>@endif
                        <span class="lb-bar"><span style="width: {{ $item['progress'] }}%"></span></span>
                        <small class="lf-progress">{{ $item['progressText'] }}</small>
                    </button>
                @endforeach
            </section>
        @endif

        @if ($groups['upcoming'] !== [])
            <section class="lf-group">
                <h3 class="lf-group-title"><i class="lf-bullet" data-tone="gray"></i>{{ __('scheduling::board.groups.upcoming') }}</h3>
                @foreach ($groups['upcoming'] as $item)
                    <button type="button" class="lf-row" wire:click="select('{{ $item['id'] }}')" aria-pressed="{{ ($selected['id'] ?? null) === $item['id'] ? 'true' : 'false' }}" wire:key="f-{{ $item['id'] }}">
                        <strong class="lb-num">{{ $item['time'] }}</strong>
                        <span class="lf-who">{{ $item['teacher'] }} ← {{ $item['student'] }} <small>· {{ $item['subject'] }}</small></span>
                        <span class="lf-checks">
                            @foreach ($item['checks'] as $check)
                                <span class="lb-check" data-ok="{{ $check['ok'] ? 'true' : 'false' }}">{{ $check['ok'] ? '✓' : '!' }} {{ $check['label'] }}</span>
                            @endforeach
                        </span>
                    </button>
                @endforeach
            </section>
        @endif

        @if ($groups['done'] !== [])
            <section class="lf-group">
                <div class="lf-done-head">
                    <h3 class="lf-group-title"><i class="lf-bullet" data-tone="hollow"></i>{{ __('scheduling::board.groups.done') }}
                        <small>{{ __('scheduling::board.groups.done_hint', ['count' => count($groups['done'])]) }}</small></h3>
                    <button type="button" class="lb-link" wire:click="toggleDone" aria-expanded="{{ $this->showDone ? 'true' : 'false' }}">
                        {{ $this->showDone ? __('scheduling::board.hide') : __('scheduling::board.show') }}
                    </button>
                </div>
                @if ($this->showDone)
                    @foreach ($groups['done'] as $item)
                        <button type="button" class="lf-row" wire:click="select('{{ $item['id'] }}')" wire:key="f-{{ $item['id'] }}">
                            <strong class="lb-num">{{ $item['time'] }}</strong>
                            <span class="lf-who">{{ $item['teacher'] }} ← {{ $item['student'] }} <small>· {{ $item['subject'] }}</small></span>
                            <span class="lb-pill" data-status="{{ $item['status']['key'] }}">{{ $item['result'] }}</span>
                        </button>
                    @endforeach
                @endif
            </section>
        @endif
    </div>

    @if ($selected)
        <aside class="lf-panel" aria-label="{{ __('scheduling::board.details') }}" wire:key="panel-{{ $selected['id'] }}">
            <span class="lb-pill" data-status="{{ $selected['status']['key'] }}">{{ $selected['status']['label'] }}</span>
            <h3 class="lf-panel-title lms-heading">{{ $selected['student'] }} · {{ $selected['subject'] }}</h3>
            <p class="lb-muted">{{ $selected['problem'] ?? __('scheduling::board.with_teacher', ['teacher' => $selected['teacher']]) }}</p>

            <div class="lb-clocks">
                @foreach ($selected['clocks'] as $clock)
                    <div class="lb-clock">
                        <small>{{ $clock['who'] }}</small>
                        <strong class="lb-num lms-heading">{{ $clock['time'] }}</strong>
                        <small>{{ $clock['city'] }}</small>
                    </div>
                @endforeach
            </div>

            @if ($selected['actions'] !== [])
                <h4 class="lb-h3">{{ __('scheduling::board.detail.you_can') }}</h4>
                <div class="lf-panel-actions">
                    @foreach ($selected['actions'] as $action)
                        <div class="lf-panel-action" data-danger="{{ ($action['danger'] ?? false) ? 'true' : 'false' }}">
                            {{ ($this->{$action['name'].'Action'})($action['args']) }}
                            <small>{{ $action['hint'] }}</small>
                        </div>
                    @endforeach
                </div>
            @endif

            <h4 class="lb-h3">{{ __('scheduling::board.detail.log') }}</h4>
            <ol class="lb-log">
                @foreach ($selected['log'] as $entry)
                    <li><span class="lb-num">{{ $entry['at'] }}</span>{{ $entry['text'] }}</li>
                @endforeach
            </ol>

            <dl class="lb-dl">
                @foreach ($selected['guardians'] as $guardian)
                    <dt>{{ $guardian['relationship'] }}</dt>
                    <dd>{{ $guardian['name'] }}@if ($guardian['phone']) · <a href="tel:{{ $guardian['phone'] }}" dir="ltr">{{ $guardian['phone'] }}</a>@endif</dd>
                @endforeach
                <dt>{{ __('scheduling::board.detail.funding') }}</dt>
                <dd>{{ $selected['funding'] }}</dd>
            </dl>
        </aside>
    @endif
</div>
