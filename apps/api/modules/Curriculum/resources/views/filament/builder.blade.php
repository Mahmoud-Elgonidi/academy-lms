@php
    $tree = $this->tree;
    $b = 'curriculum::curriculum.builder.';
    $published = $this->version()->status === 'published';
    $draft = $this->version()->status === 'draft';
    $lessons = $tree->lessons();
    $sessions = array_sum(array_map(fn ($l) => $l->estSessions, $lessons));
    $levelNodes = array_values(array_filter($tree->childrenOf(null), fn ($n) => $n->kind === 'level'));
    $readiness = $this->readiness[0];
    $grip = '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><circle cx="7" cy="5" r="1.4"/><circle cx="13" cy="5" r="1.4"/><circle cx="7" cy="10" r="1.4"/><circle cx="13" cy="10" r="1.4"/><circle cx="7" cy="15" r="1.4"/><circle cx="13" cy="15" r="1.4"/></svg>';
@endphp

<x-filament-panels::page>
    <style>
        .cb { display: flex; flex-direction: column; gap: 1rem; }
        .cb-note { padding: .7rem 1rem; border-radius: var(--radius-xl); background: var(--info-50); color: var(--info-800); font-size: .85rem; border: 1px solid var(--info-200); }
        .cb-totals { color: var(--gray-600); font-size: .875rem; }
        .cb-coverage { font-weight: 600; color: var(--warning-700); }
        .cb-coverage[data-full] { color: var(--success-700); }

        /* The readiness bar: stays in sight under the top bar while the tree scrolls. */
        .cb-ready { position: sticky; top: 4.5rem; z-index: 10; display: flex; flex-direction: column; gap: .5rem; padding: .75rem 1rem; border-radius: var(--radius-xl); background: white; border: 1px solid var(--gray-200); box-shadow: 0 6px 20px -14px rgb(0 0 0 / .35); }
        .cb-ready-top { display: flex; align-items: center; gap: .8rem; flex-wrap: wrap; }
        .cb-ready-pct { font-size: 1.15rem; font-weight: 800; font-variant-numeric: tabular-nums; min-width: 3.2rem; color: var(--success-700); }
        .cb-ready[data-state="warn"] .cb-ready-pct { color: var(--warning-700); }
        .cb-ready[data-state="blocked"] .cb-ready-pct { color: var(--danger-700); }
        .cb-ready-meter { flex: 1 1 8rem; min-width: 6rem; height: .5rem; border-radius: 999px; background: var(--gray-100); overflow: hidden; }
        .cb-ready-meter i { display: block; height: 100%; border-radius: inherit; background: var(--success-500); }
        .cb-ready[data-state="warn"] .cb-ready-meter i { background: var(--warning-500); }
        .cb-ready[data-state="blocked"] .cb-ready-meter i { background: var(--danger-500); }
        .cb-ready-say { font-size: .875rem; font-weight: 600; }
        .cb-ready details summary { cursor: pointer; font-size: .8rem; color: var(--gray-600); }
        .cb-ready ul { list-style: none; margin: .4rem 0 0; padding: 0; display: flex; flex-direction: column; gap: .2rem; max-height: 11rem; overflow-y: auto; }
        .cb-ready li a { display: flex; align-items: baseline; gap: .45rem; padding: .25rem .5rem; border-radius: var(--radius-md, .5rem); font-size: .82rem; color: inherit; text-decoration: none; }
        .cb-ready li a:hover { background: var(--gray-50); }
        .cb-ready li a::before { content: ''; flex: none; width: .5rem; height: .5rem; border-radius: 999px; background: var(--warning-500); transform: translateY(-.05rem); }
        .cb-ready li[data-blocks] a::before { background: var(--danger-500); }
        .cb-ready li[data-blocks] a { color: var(--danger-700); font-weight: 600; }
        .cb-ready small { color: var(--gray-500); font-size: .75rem; padding-inline-start: .5rem; }

        /* The tree: levels are cards, units sections in them, lessons numbered rows. */
        .cb-levels { display: flex; flex-direction: column; gap: 1rem; }
        .cb-level { background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl); overflow: hidden; scroll-margin-top: 10rem; }
        .cb-level:target, .cb-unit:target, .cb-lesson:target { outline: 2px solid var(--primary-500); outline-offset: -2px; }
        .cb-head { display: flex; align-items: center; gap: .6rem; padding: .7rem 1rem; }
        .cb-level-head { background: var(--gray-50); border-bottom: 1px solid var(--gray-200); padding-block: .85rem; }
        .cb-level-no { flex: none; display: grid; place-items: center; width: 2.2rem; height: 2.2rem; border-radius: .8rem; background: var(--primary-600); color: white; font-weight: 800; font-size: .95rem; }
        .cb-head strong { font-size: .95rem; }
        .cb-level-head strong { font-size: 1.02rem; }
        .cb-kind { font-size: .7rem; font-weight: 700; padding: .1rem .5rem; border-radius: 999px; background: var(--primary-50); color: var(--primary-700); white-space: nowrap; }
        .cb-kind[data-k="unit"] { background: var(--gray-100); color: var(--gray-700); }
        .cb-count { font-size: .72rem; color: var(--gray-500); white-space: nowrap; }
        .cb-grow { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: .1rem; }
        .cb-grow small { color: var(--gray-500); font-size: .75rem; }
        .cb-tools { display: flex; align-items: center; gap: .25rem; }
        .cb-tools button.cb-move, .cb-mat a.cb-move { border: 0; background: none; color: var(--gray-400); cursor: pointer; font-size: .85rem; padding: .15rem .3rem; border-radius: .375rem; }
        .cb-tools button.cb-move:hover { color: var(--gray-700); background: var(--gray-100); }
        .cb-tools button.cb-move:focus-visible { outline: 2px solid var(--primary-500); }
        .cb-grip { flex: none; display: grid; place-items: center; width: 1.3rem; height: 1.6rem; border-radius: .35rem; color: var(--gray-300); cursor: grab; touch-action: none; }
        .cb-grip:hover { color: var(--gray-600); background: var(--gray-100); }
        .cb-grip svg { width: 1rem; height: 1rem; }
        .sortable-chosen { background: var(--primary-50) !important; }
        .cb-children { display: flex; flex-direction: column; }
        .cb-unit { border-top: 1px solid var(--gray-100); scroll-margin-top: 10rem; }
        .cb-children > .cb-unit:first-child { border-top: 0; }
        .cb-unit-head { padding-inline-start: 1.25rem; }
        .cb-lessons { display: flex; flex-direction: column; min-height: .5rem; margin-inline-start: 1.6rem; border-inline-start: 2px solid var(--gray-100); }
        .cb-unit-empty { margin-inline-start: 2.6rem; padding: .3rem 0; font-size: .78rem; color: var(--gray-500); }
        .cb-lesson { scroll-margin-top: 10rem; }
        .cb-lesson > .cb-head { padding-block: .45rem; border-top: 1px dashed var(--gray-100); }
        .cb-num { flex: none; display: grid; place-items: center; min-width: 1.7rem; height: 1.7rem; padding-inline: .3rem; border-radius: 999px; background: var(--gray-100); color: var(--gray-700); font-size: .75rem; font-weight: 700; font-variant-numeric: tabular-nums; }
        .cb-badges { display: flex; flex-wrap: wrap; gap: .25rem; margin-top: .1rem; }
        .cb-badge { font-size: .68rem; font-weight: 600; padding: 0 .45rem; border-radius: 999px; background: var(--gray-100); color: var(--gray-600); white-space: nowrap; }
        .cb-badge[data-tone="ok"] { background: var(--success-50); color: var(--success-700); }
        .cb-badge[data-tone="miss"] { background: var(--warning-50); color: var(--warning-700); }
        .cb-badge[data-tone="text"] { background: var(--primary-50); color: var(--primary-700); }
        .cb-mats { display: inline-flex; align-items: center; gap: .25rem; border: 1px solid var(--gray-200); background: none; border-radius: 999px; padding: .1rem .55rem; font-size: .72rem; color: var(--gray-500); cursor: pointer; white-space: nowrap; }
        .cb-mats svg { width: .8rem; height: .8rem; }
        .cb-mats[data-has] { color: var(--primary-700); border-color: var(--primary-200); background: var(--primary-50); }
        .cb-mats[aria-expanded="true"] { border-color: var(--primary-500); }
        .cb-practice { white-space: nowrap; }
        .cb-practice .fi-link { font-size: .72rem; color: var(--gray-500); }
        .cb-practice[data-has] .fi-link { color: var(--success-700); }
        .cb-materials { margin-inline-start: 3.2rem; margin-inline-end: 1rem; margin-bottom: .5rem; padding: .5rem; border-radius: var(--radius-lg); background: var(--gray-50); display: flex; flex-direction: column; gap: .35rem; }
        .cb-mat { display: flex; align-items: center; gap: .6rem; padding: .35rem .5rem; border-radius: var(--radius-md, .5rem); background: white; border: 1px solid var(--gray-200); }
        .cb-mat small { color: var(--gray-500); font-size: .72rem; overflow-wrap: anywhere; }
        .cb-mat-type { font-size: .68rem; font-weight: 700; padding: .05rem .45rem; border-radius: 999px; background: var(--gray-100); color: var(--gray-700); white-space: nowrap; }
        .cb-mat-type[data-t="pdf"] { background: var(--danger-50); color: var(--danger-700); }
        .cb-mat-type[data-t="audio"], .cb-mat-type[data-t="video"] { background: var(--primary-50); color: var(--primary-700); }
        .cb-mat-type[data-t="link"] { background: var(--info-50); color: var(--info-700); }
        .cb-mat a.cb-move { text-decoration: none; }
        .cb-mat-empty { color: var(--gray-500); font-size: .8rem; padding: .25rem .5rem; }
        .cb-mat-add { display: flex; gap: 1rem; padding: .15rem .5rem; }
        .cb-plays { display: flex; flex-wrap: wrap; align-items: center; gap: .25rem; margin-top: .15rem; color: var(--success-700) !important; }
        .cb-plays span { padding: 0 .45rem; border-radius: 999px; background: var(--success-50); font-weight: 600; }
        .cb-plays[data-none] { color: var(--warning-700) !important; }

        /* Quick add: a title, then Enter. */
        .cb-quick { display: flex; align-items: center; gap: .6rem; padding: .45rem 1rem; }
        .cb-unit > .cb-quick { padding-inline-start: 2.6rem; }
        .cb-level > .cb-quick { border-top: 1px solid var(--gray-100); padding-inline-start: 1.25rem; }
        .cb-quick-field { flex: 1; min-width: 0; display: flex; align-items: center; gap: .4rem; padding: .3rem .6rem; border-radius: var(--radius-lg, .6rem); border: 1px dashed var(--gray-300); background: transparent; transition: border-color .15s, background-color .15s; }
        .cb-quick-field:focus-within { border-style: solid; border-color: var(--primary-500); background: white; }
        .cb-quick-field > span { color: var(--gray-400); font-weight: 700; }
        .cb-quick input { flex: 1; min-width: 0; border: 0; background: transparent; padding: .1rem 0; font-size: .875rem; color: inherit; outline: none; box-shadow: none; }
        .cb-quick input::placeholder { color: var(--gray-400); }
        .cb-quick kbd { font: inherit; font-size: .68rem; color: var(--gray-400); white-space: nowrap; opacity: 0; transition: opacity .15s; }
        .cb-quick-field:focus-within kbd { opacity: 1; }
        .cb-root-quick { border-radius: var(--radius-xl); border: 1px solid var(--gray-200); background: white; }
        .cb-add { display: flex; gap: 1rem; padding: 0 1rem .6rem; padding-inline-start: 1.25rem; }

        /* An empty curriculum. */
        .cb-empty { display: flex; flex-direction: column; align-items: center; gap: .75rem; padding: 2.5rem 1.5rem; text-align: center; border: 1px dashed var(--gray-300); border-radius: var(--radius-xl); background: white; }
        .cb-empty svg { width: 4.5rem; height: 4.5rem; color: var(--primary-500); }
        .cb-empty h2 { font-size: 1.15rem; font-weight: 700; }
        .cb-empty p { color: var(--gray-600); font-size: .9rem; max-width: 34rem; }
        .cb-empty .cb-quick { width: 100%; max-width: 28rem; padding: 0; }
        .cb-ways { display: grid; grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr)); gap: .6rem; width: 100%; max-width: 40rem; }

        .dark .cb-ready, .dark .cb-level, .dark .cb-empty, .dark .cb-root-quick { background: var(--gray-900); border-color: var(--gray-800); }
        .dark .cb-ready-meter { background: var(--gray-800); }
        .dark .cb-ready li a:hover { background: var(--gray-800); }
        .dark .cb-level-head { background: var(--gray-800); border-color: var(--gray-700); }
        .dark .cb-unit, .dark .cb-level > .cb-quick { border-color: var(--gray-800); }
        .dark .cb-lessons { border-color: var(--gray-800); }
        .dark .cb-lesson > .cb-head { border-color: var(--gray-800); }
        .dark .cb-num, .dark .cb-badge, .dark .cb-kind[data-k="unit"] { background: var(--gray-800); color: var(--gray-300); }
        .dark .cb-badge[data-tone="ok"] { background: color-mix(in oklab, var(--success-500) 15%, transparent); color: var(--success-400); }
        .dark .cb-badge[data-tone="miss"] { background: color-mix(in oklab, var(--warning-500) 15%, transparent); color: var(--warning-400); }
        .dark .cb-badge[data-tone="text"], .dark .cb-kind { background: color-mix(in oklab, var(--primary-500) 18%, transparent); color: var(--primary-300); }
        .dark .cb-grip:hover, .dark .cb-tools button.cb-move:hover { background: var(--gray-800); color: var(--gray-200); }
        .dark .cb-quick-field { border-color: var(--gray-700); }
        .dark .cb-quick-field:focus-within { background: var(--gray-950, var(--gray-900)); border-color: var(--primary-400); }
        .dark .sortable-chosen { background: color-mix(in oklab, var(--primary-500) 15%, transparent) !important; }
        .dark .cb-materials { background: var(--gray-800); }
        .dark .cb-mat { background: var(--gray-900); border-color: var(--gray-700); }
        .dark .cb-mats { border-color: var(--gray-700); }
        .dark .cb-plays span { background: color-mix(in oklab, var(--success-500) 15%, transparent); }
        .dark .cb-ready-say, .dark .cb-totals { color: var(--gray-300); }
        .dark .cb-empty p { color: var(--gray-400); }
    </style>

    <div class="cb">
        @if ($published)
            <div class="cb-note">{{ __($b.'published_note') }}</div>
        @endif

        @if ($draft && $tree->nodes !== [])
            @php
                $blockers = $readiness->blockers();
                $warnings = $readiness->warnings();
                $state = $blockers !== [] ? 'blocked' : ($warnings !== [] ? 'warn' : 'ok');
                $shown = array_slice($readiness->items, 0, 40);
            @endphp
            <div class="cb-ready" data-state="{{ $state }}" role="region" aria-label="{{ __('curriculum::curriculum.readiness.title') }}">
                <div class="cb-ready-top">
                    <span class="cb-ready-pct">{{ $readiness->percent }}%</span>
                    <span class="cb-ready-meter" aria-hidden="true"><i class="lms-bar-fill" style="width: {{ $readiness->percent }}%"></i></span>
                    <span class="cb-ready-say">{{ __('curriculum::curriculum.readiness.'.['blocked' => 'blocked', 'warn' => 'ready_warnings', 'ok' => 'ready'][$state]) }}</span>
                </div>
                @if ($readiness->items !== [])
                    <details @if ($blockers !== []) open @endif>
                        <summary>
                            {{ collect([
                                $blockers !== [] ? trans_choice('curriculum::curriculum.readiness.blockers', count($blockers), ['count' => count($blockers)]) : null,
                                $warnings !== [] ? trans_choice('curriculum::curriculum.readiness.warnings', count($warnings), ['count' => count($warnings)]) : null,
                            ])->filter()->implode(' · ') }}
                        </summary>
                        <ul>
                            @foreach ($shown as $item)
                                <li @if ($item->blocks()) data-blocks @endif wire:key="ready-{{ $item->key }}-{{ $item->nodeId }}">
                                    <a href="#node-{{ $item->nodeId ?? 'top' }}">{{ $this->sayReadiness($item) }}</a>
                                </li>
                            @endforeach
                        </ul>
                        @if (count($readiness->items) > count($shown))
                            <small>{{ __('curriculum::curriculum.readiness.more', ['count' => count($readiness->items) - count($shown)]) }}</small>
                        @endif
                    </details>
                @endif
            </div>
        @endif

        @if ($lessons !== [])
            @php $practice = $this->practiceSummary(); @endphp
            <div class="cb-totals">
                {{ __($b.'totals', ['levels' => count($levelNodes), 'lessons' => count($lessons), 'sessions' => $sessions]) }}
                · <span class="cb-coverage" @if ($practice['with'] === $practice['total']) data-full @endif>🎮 {{ __('curriculum::curriculum.practice.program', ['with' => $practice['with'], 'total' => $practice['total']]) }}</span>
                @if ($practice['missing'] !== [] && $practice['with'] > 0)
                    <br><small>{{ __('curriculum::curriculum.practice.missing', ['lessons' => implode('، ', array_slice($practice['missing'], 0, 5))]) }}@if (count($practice['missing']) > 5) {{ trans_choice('curriculum::curriculum.practice.more', count($practice['missing']) - 5, ['count' => count($practice['missing']) - 5]) }}@endif</small>
                @endif
            </div>
        @endif

        @if ($tree->nodes === [])
            <div class="cb-empty" id="node-top">
                <svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="10" y="8" width="44" height="12" rx="4"/><rect x="18" y="26" width="36" height="10" rx="3.5" opacity=".7"/><rect x="18" y="42" width="36" height="10" rx="3.5" opacity=".45"/>
                    <path d="M14 20v27h4M14 31h4" opacity=".6"/>
                </svg>
                <h2 class="lms-heading">{{ __($b.'empty_title') }}</h2>
                <p>{{ __($b.'empty_hint') }}</p>
                @if ($this->startWays() !== [])
                    <div class="cb-ways lms-stagger">
                        @foreach ($this->startWays() as $way)
                            <div class="lms-lift">{{ $way }}</div>
                        @endforeach
                    </div>
                @endif
                @include('curriculum::filament.builder-quick', ['parent' => 'root', 'kind' => 'level'])
            </div>
        @else
            {{-- Levels move among themselves; their units and loose lessons within the level; a unit's lessons within it and to the other units of the level. --}}
            <div class="cb-levels lms-stagger" id="node-top" wire:sort="sortNode" wire:sort:config="{ handle: '.cb-grip-level' }" wire:sort:group="cb-levels">
                @php $n = 0; @endphp
                @foreach ($levelNodes as $li => $level)
                    @php $children = $tree->childrenOf($level->id); $count = count($tree->lessons($level->id)); @endphp
                    <section class="cb-level" id="node-{{ $level->id }}" wire:key="lvl-{{ $level->id }}" wire:sort:item="'{{ $level->id }}'" aria-labelledby="title-{{ $level->id }}">
                        <div class="cb-head cb-level-head">
                            <span class="cb-grip cb-grip-level" title="{{ __($b.'drag') }}">{!! $grip !!}</span>
                            <span class="cb-level-no" aria-hidden="true">{{ $li + 1 }}</span>
                            <span class="cb-grow">
                                <small>{{ __($b.'level_n', ['n' => $li + 1]) }}</small>
                                <strong class="lms-heading" id="title-{{ $level->id }}">{{ $level->title() }}</strong>
                            </span>
                            <span class="cb-count">{{ trans_choice($b.'lessons_count', $count, ['count' => $count]) }}</span>
                            <span class="cb-tools">
                                <button type="button" class="cb-move" wire:click="move('{{ $level->id }}', 'up')" title="{{ __($b.'up') }}" aria-label="{{ __($b.'up') }}">▲</button>
                                <button type="button" class="cb-move" wire:click="move('{{ $level->id }}', 'down')" title="{{ __($b.'down') }}" aria-label="{{ __($b.'down') }}">▼</button>
                                {{ ($this->editNodeAction)(['node' => $level->id]) }}
                                @unless ($published) {{ ($this->deleteNodeAction)(['node' => $level->id]) }} @endunless
                            </span>
                        </div>

                        <div class="cb-children" wire:sort="sortNode" wire:sort:config="{ handle: '.cb-grip-child' }" wire:sort:group-id="{{ $level->id }}" wire:sort:group="level-{{ $level->id }}">
                            @foreach ($children as $child)
                                @if ($child->kind === 'unit')
                                    @php $unitLessons = $tree->childrenOf($child->id); @endphp
                                    <div class="cb-unit" id="node-{{ $child->id }}" wire:key="unit-{{ $child->id }}" wire:sort:item="'{{ $child->id }}'">
                                        <div class="cb-head cb-unit-head">
                                            <span class="cb-grip cb-grip-child" title="{{ __($b.'drag') }}">{!! $grip !!}</span>
                                            <span class="cb-kind" data-k="unit">{{ __('curriculum::curriculum.kinds.unit') }}</span>
                                            <span class="cb-grow"><strong>{{ $child->title() }}</strong></span>
                                            <span class="cb-count">{{ trans_choice($b.'lessons_count', count($unitLessons), ['count' => count($unitLessons)]) }}</span>
                                            <span class="cb-tools">
                                                <button type="button" class="cb-move" wire:click="move('{{ $child->id }}', 'up')" title="{{ __($b.'up') }}" aria-label="{{ __($b.'up') }}">▲</button>
                                                <button type="button" class="cb-move" wire:click="move('{{ $child->id }}', 'down')" title="{{ __($b.'down') }}" aria-label="{{ __($b.'down') }}">▼</button>
                                                {{ ($this->editNodeAction)(['node' => $child->id]) }}
                                                @unless ($published) {{ ($this->deleteNodeAction)(['node' => $child->id]) }} @endunless
                                            </span>
                                        </div>
                                        <div class="cb-lessons" wire:sort="sortNode" wire:sort:config="{ handle: '.cb-grip-lesson' }" wire:sort:group-id="{{ $child->id }}" wire:sort:group="lessons-{{ $level->id }}">
                                            @foreach ($unitLessons as $lesson)
                                                @include('curriculum::filament.builder-lesson', ['lesson' => $lesson, 'n' => ++$n, 'gripClass' => 'cb-grip-lesson'])
                                            @endforeach
                                        </div>
                                        @if ($unitLessons === [])
                                            <div class="cb-unit-empty">{{ __($b.'unit_empty') }}</div>
                                        @endif
                                        @include('curriculum::filament.builder-quick', ['parent' => $child->id, 'kind' => 'lesson'])
                                    </div>
                                @else
                                    @include('curriculum::filament.builder-lesson', ['lesson' => $child, 'n' => ++$n, 'gripClass' => 'cb-grip-child'])
                                @endif
                            @endforeach
                        </div>

                        @include('curriculum::filament.builder-quick', ['parent' => $level->id, 'kind' => $this->quickKind($level->id)])
                        <div class="cb-add">
                            {{ ($this->addChildAction)(['node' => $level->id, 'kind' => 'unit']) }}
                            {{ ($this->addChildAction)(['node' => $level->id, 'kind' => 'lesson']) }}
                        </div>
                    </section>
                @endforeach
            </div>
            <div class="cb-root-quick">
                @include('curriculum::filament.builder-quick', ['parent' => 'root', 'kind' => 'level'])
            </div>
        @endif
    </div>
</x-filament-panels::page>
