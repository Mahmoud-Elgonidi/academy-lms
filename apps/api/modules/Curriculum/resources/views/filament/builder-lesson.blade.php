{{-- A lesson's row ($n its number in the program, $gripClass the list it drags in) and, when open, its materials. --}}
@php
    $b = 'curriculum::curriculum.builder.';
    $count = $this->materialCounts[$lesson->id] ?? 0;
    $open = $this->openLesson === $lesson->id;
    $text = $this->texts[$lesson->id] ?? null;
    $hasObjective = trim((string) $lesson->objectives) !== '';
@endphp
<div class="cb-lesson" id="node-{{ $lesson->id }}" wire:key="les-{{ $lesson->id }}" wire:sort:item="'{{ $lesson->id }}'">
    <div class="cb-head">
        <span class="cb-grip {{ $gripClass }}" title="{{ __($b.'drag') }}">{!! $grip !!}</span>
        <span class="cb-num">{{ $n }}</span>
        <span class="cb-grow">
            <span>{{ $lesson->title() }}</span>
            <small>
                {{ __($b.'sessions_short', ['n' => $lesson->estSessions]) }}
                @if ($lesson->weight !== 1) · {{ __($b.'weight_short', ['n' => $lesson->weight]) }} @endif
                @if ($lesson->masteryRule === 'score_twice') · {{ __('curriculum::curriculum.mastery_rules.score_twice') }} @endif
            </small>
            <span class="cb-badges">
                <span class="cb-badge" data-tone="{{ $hasObjective ? 'ok' : 'miss' }}">{{ __($b.'badges.'.($hasObjective ? 'objective' : 'no_objective')) }}</span>
                @if ($text)<span class="cb-badge" data-tone="text">{{ __($b.'badges.'.($text === 'quran' ? 'quran' : 'text')) }}</span>@endif
            </span>
            @if (array_key_exists($lesson->id, $this->playsIn))
                {{-- What the learner plays it in: the games its practice is enough for. --}}
                @php $plays = $this->playsIn[$lesson->id]; @endphp
                <small class="cb-plays" @if ($plays === []) data-none @endif>
                    🎮 {{ $plays === [] ? __('curriculum::curriculum.practice.plays_none') : __('curriculum::curriculum.practice.plays_in') }}
                    @foreach ($plays as $g)<span title="{{ $g['stages'] }}">{{ $g['title'] }}</span>@endforeach
                </small>
            @endif
        </span>
        <button type="button" class="cb-mats" wire:click="toggleMaterials('{{ $lesson->id }}')" aria-expanded="{{ $open ? 'true' : 'false' }}" @if ($count > 0) data-has @endif>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="m20 11.5-7.7 7.7a4.5 4.5 0 0 1-6.4-6.4l7.8-7.8a3 3 0 0 1 4.2 4.2l-7.8 7.8a1.5 1.5 0 0 1-2.1-2.1l7.1-7.1"/></svg>
            {{ trans_choice('curriculum::curriculum.resources.count', $count, ['count' => $count]) }}
        </button>
        <span class="cb-practice" @if (($this->practiceCounts[$lesson->id] ?? 0) > 0) data-has @endif>{{ ($this->practiceAction)(['node' => $lesson->id]) }} {{ ($this->suggestAction)(['node' => $lesson->id]) }}</span>
        <span class="cb-tools">
            <button type="button" class="cb-move" wire:click="move('{{ $lesson->id }}', 'up')" title="{{ __($b.'up') }}" aria-label="{{ __($b.'up') }}">▲</button>
            <button type="button" class="cb-move" wire:click="move('{{ $lesson->id }}', 'down')" title="{{ __($b.'down') }}" aria-label="{{ __($b.'down') }}">▼</button>
            {{ ($this->editNodeAction)(['node' => $lesson->id]) }}
            @unless ($published) {{ ($this->deleteNodeAction)(['node' => $lesson->id]) }} @endunless
        </span>
    </div>
    @if ($open)
        @php $mats = $this->materials($lesson->id); @endphp
        <div class="cb-materials" wire:key="mats-{{ $lesson->id }}">
            @forelse ($mats as $m)
                <div class="cb-mat" wire:key="mat-{{ $m->id }}">
                    <span class="cb-mat-type" data-t="{{ $m->type() }}">{{ __('curriculum::curriculum.resources.types.'.$m->type()) }}</span>
                    <span class="cb-grow">
                        <span>{{ $m->toData()->title() }}</span>
                        <small>
                            @if ($m->kind === 'link') {{ \Illuminate\Support\Str::limit($m->url, 60) }} @else {{ $m->file_name }} · {{ \Illuminate\Support\Number::fileSize((int) $m->size) }} @endif
                            @if ($m->audience === 'teachers') · <strong>{{ __('curriculum::curriculum.resources.teachers_only') }}</strong> @endif
                        </small>
                    </span>
                    <span class="cb-tools">
                        <a class="cb-move" href="{{ $m->kind === 'link' ? $m->url : route('curriculum.resource', ['resource' => $m->id]) }}" target="_blank" rel="noopener noreferrer" title="{{ __('curriculum::curriculum.resources.open') }}">↗</a>
                        <button type="button" class="cb-move" wire:click="moveMaterial('{{ $m->id }}', 'up')" title="{{ __('curriculum::curriculum.resources.up') }}" aria-label="{{ __('curriculum::curriculum.resources.up') }}">▲</button>
                        <button type="button" class="cb-move" wire:click="moveMaterial('{{ $m->id }}', 'down')" title="{{ __('curriculum::curriculum.resources.down') }}" aria-label="{{ __('curriculum::curriculum.resources.down') }}">▼</button>
                        {{ ($this->editMaterialAction)(['resource' => $m->id]) }}
                        {{ ($this->removeMaterialAction)(['resource' => $m->id]) }}
                    </span>
                </div>
            @empty
                <div class="cb-mat-empty">{{ __('curriculum::curriculum.resources.empty') }}</div>
            @endforelse
            <div class="cb-mat-add">
                {{ ($this->addMaterialAction)(['node' => $lesson->id, 'kind' => 'file']) }}
                {{ ($this->addMaterialAction)(['node' => $lesson->id, 'kind' => 'link']) }}
            </div>
        </div>
    @endif
</div>
