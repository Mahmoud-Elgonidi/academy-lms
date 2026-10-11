@php
    $tree = $this->tree;
    $published = $this->version()->status === 'published';
    $lessons = $tree->lessons();
    $sessions = array_sum(array_map(fn ($l) => $l->estSessions, $lessons));
    $levels = count(array_filter($tree->childrenOf(null), fn ($n) => $n->kind === 'level'));
@endphp

<x-filament-panels::page>
    <style>
        .cb { display: flex; flex-direction: column; gap: 1rem; }
        .cb-note { padding: .7rem 1rem; border-radius: var(--radius-xl); background: var(--info-50); color: var(--info-800); font-size: .85rem; border: 1px solid var(--info-200); }
        .cb-totals { color: var(--gray-600); font-size: .875rem; }
        .cb-level { background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl); overflow: hidden; }
        .cb-head { display: flex; align-items: center; gap: .6rem; padding: .7rem 1rem; }
        .cb-level > .cb-head { background: var(--gray-50); border-bottom: 1px solid var(--gray-200); }
        .cb-head strong { font-size: .95rem; }
        .cb-kind { font-size: .7rem; font-weight: 700; padding: .1rem .5rem; border-radius: 999px; background: var(--primary-50); color: var(--primary-700); white-space: nowrap; }
        .cb-kind[data-k="unit"] { background: var(--gray-100); color: var(--gray-700); }
        .cb-kind[data-k="lesson"] { background: transparent; color: var(--gray-500); padding: 0; min-width: 1.6rem; text-align: center; }
        .cb-grow { flex: 1; min-width: 0; display: flex; flex-direction: column; }
        .cb-grow small { color: var(--gray-500); font-size: .75rem; }
        .cb-tools { display: flex; align-items: center; gap: .25rem; }
        .cb-tools button.cb-move { border: 0; background: none; color: var(--gray-400); cursor: pointer; font-size: .9rem; padding: .15rem .3rem; border-radius: .375rem; }
        .cb-tools button.cb-move:hover { color: var(--gray-700); background: var(--gray-100); }
        .cb-unit { border-top: 1px solid var(--gray-100); }
        .cb-unit > .cb-head { padding-inline-start: 1.6rem; }
        .cb-lesson { padding-block: .45rem; padding-inline-start: 2.6rem; border-top: 1px dashed var(--gray-100); }
        .cb-level > .cb-lesson { padding-inline-start: 1.6rem; }
        .cb-add { display: flex; gap: 1rem; padding: .45rem 1rem .6rem; padding-inline-start: 2.6rem; }
        .cb-mats { display: inline-flex; align-items: center; gap: .25rem; border: 1px solid var(--gray-200); background: none; border-radius: 999px; padding: .1rem .55rem; font-size: .72rem; color: var(--gray-500); cursor: pointer; white-space: nowrap; }
        .cb-mats svg { width: .8rem; height: .8rem; }
        .cb-mats[data-has] { color: var(--primary-700); border-color: var(--primary-200); background: var(--primary-50); }
        .cb-mats[aria-expanded="true"] { border-color: var(--primary-500); }
        .cb-practice { white-space: nowrap; }
        .cb-practice .fi-link { font-size: .72rem; color: var(--gray-500); }
        .cb-practice[data-has] .fi-link { color: var(--success-700); }
        .cb-materials { margin-inline-start: 2.6rem; margin-inline-end: 1rem; margin-bottom: .5rem; padding: .5rem; border-radius: var(--radius-lg); background: var(--gray-50); display: flex; flex-direction: column; gap: .35rem; }
        .cb-mat { display: flex; align-items: center; gap: .6rem; padding: .35rem .5rem; border-radius: var(--radius-md, .5rem); background: white; border: 1px solid var(--gray-200); }
        .cb-mat small { color: var(--gray-500); font-size: .72rem; overflow-wrap: anywhere; }
        .cb-mat-type { font-size: .68rem; font-weight: 700; padding: .05rem .45rem; border-radius: 999px; background: var(--gray-100); color: var(--gray-700); white-space: nowrap; }
        .cb-mat-type[data-t="pdf"] { background: var(--danger-50); color: var(--danger-700); }
        .cb-mat-type[data-t="audio"], .cb-mat-type[data-t="video"] { background: var(--primary-50); color: var(--primary-700); }
        .cb-mat-type[data-t="link"] { background: var(--info-50); color: var(--info-700); }
        .cb-mat a.cb-move { text-decoration: none; }
        .cb-mat-empty { color: var(--gray-500); font-size: .8rem; padding: .25rem .5rem; }
        .cb-mat-add { display: flex; gap: 1rem; padding: .15rem .5rem; }
        .dark .cb-materials { background: var(--gray-800); }
        .dark .cb-mat { background: var(--gray-900); border-color: var(--gray-700); }
        .dark .cb-mats { border-color: var(--gray-700); }
        .cb-plays { display: flex; flex-wrap: wrap; align-items: center; gap: .25rem; margin-top: .15rem; color: var(--success-700) !important; }
        .cb-plays span { padding: 0 .45rem; border-radius: 999px; background: var(--success-50); font-weight: 600; }
        .cb-plays[data-none] { color: var(--warning-700) !important; }
        .dark .cb-plays span { background: color-mix(in oklab, var(--success-500) 15%, transparent); }
        .cb-coverage { font-weight: 600; color: var(--warning-700); }
        .cb-coverage[data-full] { color: var(--success-700); }
        .cb-empty { padding: 2rem; text-align: center; color: var(--gray-500); border: 1px dashed var(--gray-300); border-radius: var(--radius-xl); }
        .dark .cb-level { background: var(--gray-900); border-color: var(--gray-800); }
        .dark .cb-level > .cb-head { background: var(--gray-800); }
    </style>

    <div class="cb">
        @if ($published)
            <div class="cb-note">{{ __('curriculum::curriculum.builder.published_note') }}</div>
        @endif
        @if ($lessons !== [])
            @php $practice = $this->practiceSummary(); @endphp
            <div class="cb-totals">
                {{ __('curriculum::curriculum.builder.totals', ['levels' => $levels, 'lessons' => count($lessons), 'sessions' => $sessions]) }}
                · <span class="cb-coverage" @if ($practice['with'] === $practice['total']) data-full @endif>🎮 {{ __('curriculum::curriculum.practice.program', ['with' => $practice['with'], 'total' => $practice['total']]) }}</span>
                @if ($practice['missing'] !== [] && $practice['with'] > 0)
                    <br><small>{{ __('curriculum::curriculum.practice.missing', ['lessons' => implode('، ', array_slice($practice['missing'], 0, 5))]) }}@if (count($practice['missing']) > 5) {{ trans_choice('curriculum::curriculum.practice.more', count($practice['missing']) - 5, ['count' => count($practice['missing']) - 5]) }}@endif</small>
                @endif
            </div>
        @endif

        @php $n = 0; @endphp
        @forelse ($tree->childrenOf(null) as $level)
            <section class="cb-level" wire:key="lvl-{{ $level->id }}">
                <div class="cb-head">
                    <span class="cb-kind">{{ __('curriculum::curriculum.kinds.level') }}</span>
                    <span class="cb-grow"><strong class="lms-heading">{{ $level->title() }}</strong></span>
                    <span class="cb-tools">
                        <button type="button" class="cb-move" wire:click="move('{{ $level->id }}', 'up')" title="{{ __('curriculum::curriculum.builder.up') }}">▲</button>
                        <button type="button" class="cb-move" wire:click="move('{{ $level->id }}', 'down')" title="{{ __('curriculum::curriculum.builder.down') }}">▼</button>
                        {{ ($this->editNodeAction)(['node' => $level->id]) }}
                        @unless ($published) {{ ($this->deleteNodeAction)(['node' => $level->id]) }} @endunless
                    </span>
                </div>

                @foreach ($tree->childrenOf($level->id) as $child)
                    @if ($child->kind === 'unit')
                        <div class="cb-unit" wire:key="unit-{{ $child->id }}">
                            <div class="cb-head">
                                <span class="cb-kind" data-k="unit">{{ __('curriculum::curriculum.kinds.unit') }}</span>
                                <span class="cb-grow"><strong>{{ $child->title() }}</strong></span>
                                <span class="cb-tools">
                                    <button type="button" class="cb-move" wire:click="move('{{ $child->id }}', 'up')">▲</button>
                                    <button type="button" class="cb-move" wire:click="move('{{ $child->id }}', 'down')">▼</button>
                                    {{ ($this->editNodeAction)(['node' => $child->id]) }}
                                    @unless ($published) {{ ($this->deleteNodeAction)(['node' => $child->id]) }} @endunless
                                </span>
                            </div>
                            @foreach ($tree->childrenOf($child->id) as $lesson)
                                @include('curriculum::filament.builder-lesson', ['lesson' => $lesson, 'n' => ++$n])
                            @endforeach
                            <div class="cb-add">{{ ($this->addChildAction)(['node' => $child->id, 'kind' => 'lesson']) }}</div>
                        </div>
                    @else
                        @include('curriculum::filament.builder-lesson', ['lesson' => $child, 'n' => ++$n])
                    @endif
                @endforeach
                <div class="cb-add" style="padding-inline-start: 1.6rem">
                    {{ ($this->addChildAction)(['node' => $level->id, 'kind' => 'unit']) }}
                    {{ ($this->addChildAction)(['node' => $level->id, 'kind' => 'lesson']) }}
                </div>
            </section>
        @empty
            <div class="cb-empty">{{ __('curriculum::curriculum.builder.empty') }}</div>
        @endforelse
    </div>
</x-filament-panels::page>
