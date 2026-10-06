@php $l = 'people::family.'; @endphp
<div class="pf-switch">
    <style>
        .pf-switch { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; margin-bottom: 1rem; }
        .pf-switch > span { color: var(--gray-500); font-size: .875rem; }
        .pf-kid { display: inline-flex; align-items: center; gap: .45rem; padding: .3rem .8rem .3rem .35rem; border-radius: 999px; border: 1px solid var(--gray-200); background: white; font-size: .9rem; cursor: pointer; color: var(--gray-700); }
        .pf-kid i { display: grid; place-items: center; width: 1.75rem; height: 1.75rem; border-radius: 999px; background: var(--primary-50); color: var(--primary-700); font-style: normal; font-weight: 700; }
        .pf-kid[aria-pressed="true"] { border-color: var(--primary-500); background: var(--primary-50); color: var(--primary-800); font-weight: 600; }
        .pf-kid[aria-pressed="true"] i { background: var(--primary-600); color: white; }
        .dark .pf-kid { background: var(--gray-900); border-color: var(--gray-700); color: var(--gray-200); }
    </style>
    @if ($current && ! ($current->isSelf && count($learners) === 1))
        <span>{{ __($l.'viewing') }}</span>
        @foreach ($learners as $learner)
            <button type="button" class="pf-kid" aria-pressed="{{ $learner->id === $current->id ? 'true' : 'false' }}"
                @if ($learner->id !== $current->id) wire:click="choose('{{ $learner->id }}')" @endif>
                <i>{{ $learner->initial() }}</i>{{ $learner->isSelf ? __($l.'me') : $learner->name }}
            </button>
        @endforeach
    @endif
</div>
