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
        .cb-empty { padding: 2rem; text-align: center; color: var(--gray-500); border: 1px dashed var(--gray-300); border-radius: var(--radius-xl); }
        .dark .cb-level { background: var(--gray-900); border-color: var(--gray-800); }
        .dark .cb-level > .cb-head { background: var(--gray-800); }
    </style>

    <div class="cb">
        @if ($published)
            <div class="cb-note">{{ __('curriculum::curriculum.builder.published_note') }}</div>
        @endif
        @if ($lessons !== [])
            <div class="cb-totals">{{ __('curriculum::curriculum.builder.totals', ['levels' => $levels, 'lessons' => count($lessons), 'sessions' => $sessions]) }}</div>
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
