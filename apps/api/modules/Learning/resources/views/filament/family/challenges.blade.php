{{--
    The week's challenges on the family home, as in the apps. The student
    collects what they finished (the points count up, confetti when they
    grew) and sees their guardians' cheers; a guardian follows and cheers.
    Each stage keeps its own words; the shapes follow the stage shell.
--}}
@php
    $d = $this->data();
    $l = 'learning::challenges.';
    $icons = ['attend' => 'heroicon-o-calendar-days', 'homework' => 'heroicon-o-clipboard-document-check', 'on_time' => 'heroicon-o-clock',
        'master' => 'heroicon-o-star', 'focus' => 'heroicon-o-bolt', 'month' => 'heroicon-o-trophy', 'boss' => 'heroicon-o-sparkles', 'chest' => 'heroicon-o-gift'];
    $cheerIcons = ['clap' => 'heroicon-o-hand-raised', 'star' => 'heroicon-o-star', 'heart' => 'heroicon-o-heart', 'fire' => 'heroicon-o-fire'];
    $group = null;
@endphp
<x-filament-widgets::widget>
    {{-- A reward just collected: a burst from the points with what it brought (the motion layer's own confetti). --}}
    <div class="lc" data-look="{{ $d['stage'] }}" x-data
        x-on:lc-collected.window="if (! matchMedia('(prefers-reduced-motion: reduce)').matches) setTimeout(() => window.LmsMotion?.burst($root.querySelector('.lc-points'), $event.detail.text), 350)">
        <style>
            .lc { display: grid; gap: .8rem; }
            .lc-card { background: white; border: 1px solid var(--gray-200); border-radius: 1rem; padding: 1rem 1.1rem; }
            .lc[data-look="kids"] .lc-card { border-width: 2px; border-radius: 1.6rem; box-shadow: 0 4px 0 var(--gray-200); }
            .lc-head { display: flex; align-items: center; gap: .8rem; flex-wrap: wrap; }
            .lc-head h3 { margin: 0; font-size: 1.15rem; font-weight: 800; flex: 1; min-width: 10rem; }
            .lc-head small { display: block; font-weight: 500; font-size: .85rem; color: var(--gray-500); }
            .lc-points { display: inline-flex; align-items: center; gap: .35rem; padding: .3rem .8rem; border-radius: 999px; background: var(--warning-50); color: var(--warning-800); font-weight: 800; }
            .lc-rank { font-size: .8rem; font-weight: 700; color: var(--primary-700); background: var(--primary-50); border-radius: 999px; padding: .2rem .6rem; }
            .lc-group { margin: .4rem .2rem 0; font-size: .82rem; font-weight: 700; color: var(--gray-500); }
            .lc-list { display: grid; gap: .6rem; }
            .lc-row { display: grid; grid-template-columns: auto 1fr auto; gap: .75rem; align-items: center; }
            .lc-row[data-state="done"] { background: var(--warning-50); border-color: var(--warning-200); }
            .lc-icon { display: grid; place-items: center; width: 2.7rem; height: 2.7rem; border-radius: .8rem; background: var(--primary-50); color: var(--primary-700); }
            .lc[data-look="kids"] .lc-icon { width: 3.1rem; height: 3.1rem; border-radius: 50%; }
            .lc-row[data-state="done"] .lc-icon, .lc-row[data-state="claimed"] .lc-icon { background: var(--warning-400); color: white; }
            .lc-row[data-state="missed"] .lc-icon { background: var(--gray-100); color: var(--gray-400); }
            .lc-row[data-key="boss"] { background: var(--primary-800); color: white; border-color: var(--primary-800); }
            .lc-row[data-key="boss"] .lc-hint { color: color-mix(in oklab, white 75%, transparent); }
            .lc-title { font-weight: 700; }
            .lc-hint { font-size: .85rem; color: var(--gray-500); }
            .lc-bar { display: block; height: .5rem; margin-top: .45rem; border-radius: 999px; background: var(--gray-100); overflow: hidden; }
            .lc-bar i { display: block; height: 100%; border-radius: inherit; background: var(--primary-500); }
            .lc-row[data-state="done"] .lc-bar i, .lc-row[data-state="claimed"] .lc-bar i { background: var(--warning-400); }
            .lc-row[data-key="boss"] .lc-bar { background: color-mix(in oklab, white 20%, transparent); }
            .lc-row[data-key="boss"] .lc-bar i { background: var(--danger-400); }
            .lc-end { display: flex; flex-direction: column; align-items: end; gap: .25rem; font-size: .85rem; white-space: nowrap; }
            .lc-end b { direction: ltr; }
            .lc-chip { font-size: .78rem; font-weight: 700; border-radius: 999px; padding: .15rem .55rem; background: var(--gray-100); color: var(--gray-600); }
            .lc-chip[data-tone="claimed"] { background: var(--success-50); color: var(--success-700); }
            .lc-chip[data-tone="missed"] { background: var(--warning-50); color: var(--warning-700); }
            .lc-chip[data-tone="done"] { background: var(--warning-100); color: var(--warning-800); }
            .lc-cheer { display: flex; align-items: center; gap: .8rem; flex-wrap: wrap; background: var(--primary-50); border-color: var(--primary-200); }
            .lc-cheer strong { flex: 1; font-size: 1.05rem; }
            .lc-cheers { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .5rem; }
            .lc-cheers button { display: grid; justify-items: center; gap: .2rem; padding: .55rem .2rem; border-radius: .9rem; border: 1px solid var(--gray-200); background: white; font-size: .8rem; font-weight: 700; color: var(--gray-700); }
            .lc-cheers button:disabled { opacity: .45; }
            .lc-cheers button:not(:disabled):hover { background: var(--primary-50); border-color: var(--primary-200); }
            .lc-soft { font-size: .85rem; color: var(--gray-500); text-align: center; }
            @media (prefers-reduced-motion: no-preference) {
                .lc-row[data-state="done"] .lc-collect { animation: lc-nudge 2.6s ease-in-out 1.2s infinite backwards; }
                @keyframes lc-nudge { 0%, 70%, 100% { transform: translateY(0); } 78% { transform: translateY(-3px); } 86% { transform: translateY(0); } 92% { transform: translateY(-1.5px); } }
            }
        </style>

        @if ($d['cheers'] !== [])
            <div class="lc-card lc-cheer" data-celebrate="cheer:{{ $d['cheer_key'] }}" data-celebrate-value="1">
                @foreach (collect($d['cheers'])->pluck('kind')->unique() as $kind)
                    <x-filament::icon :icon="$cheerIcons[$kind] ?? 'heroicon-o-hand-raised'" class="h-7 w-7 text-primary-600" />
                @endforeach
                <strong>{{ __($l.'web.cheer_from', ['names' => collect($d['cheers'])->pluck('from')->filter()->unique()->join('، ')]) }}</strong>
                <x-filament::button size="sm" wire:click="seen">{{ __($l.'web.thanks') }}</x-filament::button>
            </div>
        @endif

        <div class="lc-card">
            <div class="lc-head">
                <h3>
                    {{ $d['self'] ? __($l.'web.title.'.$d['stage']) : __($l.'web.of', ['name' => $d['first']]) }}
                    <small>{{ trans_choice($l.'web.days_left', $d['days_left']) }}</small>
                </h3>
                <span class="lc-rank">{{ $d['rank'] }}</span>
                {{-- A new node when the points change, so the motion layer counts up and celebrates again. --}}
                <span class="lc-points" wire:key="lc-points-{{ $d['points'] }}" data-celebrate="points:{{ $d['learner']->id }}" data-celebrate-value="{{ $d['points'] }}">
                    <x-filament::icon :icon="$d['stage'] === 'middle' ? 'heroicon-m-bolt' : 'heroicon-m-star'" class="h-5 w-5" />
                    <span data-count="{{ $d['points'] }}" data-count-key="challenges:{{ $d['learner']->id }}">{{ number_format($d['points']) }}</span>
                </span>
            </div>
            @if ($d['stickers'])
                <p class="lc-hint" style="margin: .5rem 0 0">{{ __($l.'web.album', ['earned' => $d['stickers']['earned'], 'total' => count($d['stickers']['items'])]) }}
                    @foreach (array_filter($d['stickers']['items'], fn ($s) => $s['earned']) as $s) · {{ __($l.'stickers.'.$s['key']) }} @endforeach
                </p>
            @endif
        </div>

        @if ($d['challenges'] === [])
            <p class="lc-soft">{{ __($l.'web.none') }}</p>
        @endif

        <div class="lc-list lms-stagger">
            @foreach ($d['challenges'] as $c)
                @if ($c['group'] !== $group)
                    @php $group = $c['group']; @endphp
                    <p class="lc-group">{{ __($l.'web.groups.'.$group) }}</p>
                @endif
                <div class="lc-card lc-row lms-lift" data-state="{{ $c['state'] }}" data-key="{{ $c['key'] }}" wire:key="lc-{{ $c['key'] }}-{{ $c['period'] }}">
                    <span class="lc-icon"><x-filament::icon :icon="$icons[$c['key']] ?? 'heroicon-o-star'" class="h-6 w-6" /></span>
                    <div>
                        <div class="lc-title">{{ $c['title'] }}</div>
                        <div class="lc-hint">{{ $c['hint'] }}</div>
                        <span class="lc-bar"><i class="lms-bar-fill" style="width: {{ $c['target'] ? min(100, round($c['progress'] * 100 / $c['target'])) : 0 }}%"></i></span>
                    </div>
                    <div class="lc-end">
                        @if ($c['state'] === 'done' && $d['self'])
                            <x-filament::button class="lc-collect" size="sm" color="warning" icon="heroicon-m-gift" wire:click="claim('{{ $c['key'] }}', '{{ $c['period'] }}')" wire:loading.attr="disabled">
                                {{ __($l.'web.collect', ['reward' => $c['reward_label']]) }}
                            </x-filament::button>
                        @else
                            <b>{{ $c['progress'] }}/{{ $c['target'] }}</b>
                            @if ($c['state'] === 'done')
                                <span class="lc-chip" data-tone="done">{{ __($l.'web.done') }}</span>
                            @elseif (in_array($c['state'], ['claimed', 'missed'], true))
                                <span class="lc-chip" data-tone="{{ $c['state'] }}">{{ $c['state_label'] }}</span>
                            @else
                                <span>{{ $c['reward_label'] }}</span>
                            @endif
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        @unless ($d['self'])
            <div class="lc-card">
                <div class="lc-title" style="margin-bottom: .6rem">{{ __($l.'web.cheer_on', ['name' => $d['first']]) }}</div>
                <div class="lc-cheers">
                    @foreach ($cheerIcons as $kind => $icon)
                        <button type="button" wire:click="cheer('{{ $kind }}')" wire:loading.attr="disabled" @disabled($d['cheers_left'] === 0)>
                            <x-filament::icon :icon="$icon" class="h-7 w-7 text-primary-600" />
                            {{ __($l.'cheers.'.$kind) }}
                        </button>
                    @endforeach
                </div>
                <p class="lc-soft" style="margin: .5rem 0 0">{{ trans_choice($l.'web.cheers_left', $d['cheers_left']) }}</p>
            </div>
        @endunless
    </div>
</x-filament-widgets::widget>
