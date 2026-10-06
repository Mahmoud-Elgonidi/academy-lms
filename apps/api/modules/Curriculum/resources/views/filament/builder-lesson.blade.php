<div class="cb-head cb-lesson" wire:key="les-{{ $lesson->id }}">
    <span class="cb-kind" data-k="lesson">{{ $n }}</span>
    <span class="cb-grow">
        <span>{{ $lesson->title() }}</span>
        <small>
            {{ __('curriculum::curriculum.builder.sessions_short', ['n' => $lesson->estSessions]) }}
            @if ($lesson->weight !== 1) · {{ __('curriculum::curriculum.builder.weight_short', ['n' => $lesson->weight]) }} @endif
            @if ($lesson->masteryRule === 'score_twice') · {{ __('curriculum::curriculum.mastery_rules.score_twice') }} @endif
        </small>
    </span>
    <span class="cb-tools">
        <button type="button" class="cb-move" wire:click="move('{{ $lesson->id }}', 'up')">▲</button>
        <button type="button" class="cb-move" wire:click="move('{{ $lesson->id }}', 'down')">▼</button>
        {{ ($this->editNodeAction)(['node' => $lesson->id]) }}
        @unless ($published) {{ ($this->deleteNodeAction)(['node' => $lesson->id]) }} @endunless
    </span>
</div>
