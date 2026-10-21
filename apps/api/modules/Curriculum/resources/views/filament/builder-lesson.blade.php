<div class="cb-head cb-lesson" wire:key="les-{{ $lesson->id }}">
    <span class="cb-kind" data-k="lesson">{{ $n }}</span>
    <span class="cb-grow">
        <span>{{ $lesson->title() }}</span>
        <small>
            {{ __('curriculum::curriculum.builder.sessions_short', ['n' => $lesson->estSessions]) }}
            @if ($lesson->weight !== 1) · {{ __('curriculum::curriculum.builder.weight_short', ['n' => $lesson->weight]) }} @endif
            @if ($lesson->masteryRule === 'score_twice') · {{ __('curriculum::curriculum.mastery_rules.score_twice') }} @endif
        </small>
        @if (array_key_exists($lesson->id, $this->playsIn))
            {{-- What the learner plays it in: the games its practice is enough for. --}}
            @php $plays = $this->playsIn[$lesson->id]; @endphp
            <small class="cb-plays" @if ($plays === []) data-none @endif>
                🎮 {{ $plays === [] ? __('curriculum::curriculum.practice.plays_none') : __('curriculum::curriculum.practice.plays_in') }}
                @foreach ($plays as $g)<span title="{{ $g['stages'] }}">{{ $g['title'] }}</span>@endforeach
            </small>
        @endif
    </span>
    @php $count = $this->materialCounts[$lesson->id] ?? 0; $open = $this->openLesson === $lesson->id; @endphp
    <button type="button" class="cb-mats" wire:click="toggleMaterials('{{ $lesson->id }}')" aria-expanded="{{ $open ? 'true' : 'false' }}" @if ($count > 0) data-has @endif>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="m20 11.5-7.7 7.7a4.5 4.5 0 0 1-6.4-6.4l7.8-7.8a3 3 0 0 1 4.2 4.2l-7.8 7.8a1.5 1.5 0 0 1-2.1-2.1l7.1-7.1"/></svg>
        {{ trans_choice('curriculum::curriculum.resources.count', $count, ['count' => $count]) }}
    </button>
    <span class="cb-practice" @if (($this->practiceCounts[$lesson->id] ?? 0) > 0) data-has @endif>{{ ($this->practiceAction)(['node' => $lesson->id]) }} {{ ($this->suggestAction)(['node' => $lesson->id]) }}</span>
    <span class="cb-tools">
        <button type="button" class="cb-move" wire:click="move('{{ $lesson->id }}', 'up')">▲</button>
        <button type="button" class="cb-move" wire:click="move('{{ $lesson->id }}', 'down')">▼</button>
        {{ ($this->editNodeAction)(['node' => $lesson->id]) }}
        @unless ($published) {{ ($this->deleteNodeAction)(['node' => $lesson->id]) }} @endunless
    </span>
</div>
@if ($this->openLesson === $lesson->id)
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
                    <button type="button" class="cb-move" wire:click="moveMaterial('{{ $m->id }}', 'up')" title="{{ __('curriculum::curriculum.resources.up') }}">▲</button>
                    <button type="button" class="cb-move" wire:click="moveMaterial('{{ $m->id }}', 'down')" title="{{ __('curriculum::curriculum.resources.down') }}">▼</button>
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
