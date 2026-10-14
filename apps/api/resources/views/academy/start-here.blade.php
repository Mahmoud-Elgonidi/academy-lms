@php $l = 'setup.'; $complete = $left === 0; @endphp
<x-filament-panels::page>
    {{-- The academy's colours through Filament's variables; the parts come in one by one, the bar fills and the count counts up (shared motion layer). --}}
    <style>
        .sh-stack { display: flex; flex-direction: column; gap: 1rem; }
        .sh-card { background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl, .75rem); padding: 1rem 1.15rem; }
        .sh-progress { display: flex; flex-direction: column; gap: .65rem; border-inline-start: 3px solid var(--warning-500); }
        .sh-progress[data-complete] { border-inline-start-color: var(--success-500); }
        .sh-progress header { display: flex; align-items: baseline; justify-content: space-between; gap: .75rem; flex-wrap: wrap; }
        .sh-progress header span { color: var(--gray-600); font-weight: 600; }
        .sh-progress header b { font-size: 1.6rem; line-height: 1.1; font-variant-numeric: tabular-nums; color: var(--gray-950); }
        .sh-progress header small { color: var(--gray-500); font-size: .95rem; }
        .sh-bar { height: .6rem; border-radius: 999px; background: var(--gray-100); overflow: hidden; }
        .sh-bar > i { display: block; height: 100%; border-radius: inherit; background: var(--primary-500); }
        .sh-progress[data-complete] .sh-bar > i { background: var(--success-500); }
        .sh-progress p { margin: 0; color: var(--gray-600); font-size: .9rem; }
        .sh-groups { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 22rem), 1fr)); gap: 1rem; align-items: start; }
        .sh-card h3 { margin: 0 0 .25rem; font-weight: 600; color: var(--gray-950); }
        .sh-card > p { margin: 0 0 .35rem; color: var(--gray-500); font-size: .85rem; }
        .sh-steps { list-style: none; margin: 0; padding: 0; }
        .sh-step { display: grid; grid-template-columns: auto 1fr; gap: .2rem .75rem; padding: .8rem 0; border-top: 1px solid var(--gray-100); }
        .sh-step:first-child { border-top: 0; }
        .sh-mark { grid-row: span 3; width: 1.6rem; height: 1.6rem; margin-top: .1rem; border-radius: 999px; display: grid; place-items: center; border: 2px solid var(--gray-300); color: transparent; }
        .sh-mark svg { width: 1rem; height: 1rem; }
        .sh-step[data-done] .sh-mark { background: var(--success-500); border-color: var(--success-500); color: white; }
        .sh-title { font-weight: 600; color: var(--gray-950); }
        .sh-step[data-done] .sh-title { color: var(--gray-600); }
        .sh-hint { color: var(--gray-500); font-size: .85rem; line-height: 1.5; }
        .sh-act { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; margin-top: .35rem; }
        .sh-state { font-size: .78rem; color: var(--gray-500); }
        .sh-step[data-done] .sh-state { color: var(--success-700); }
        .sh-step[data-locked] .sh-title, .sh-step[data-locked] .sh-hint { opacity: .65; }
        .sh-optional { border-style: dashed; }
        .dark .sh-card { background: var(--gray-900); border-color: var(--gray-800); }
        .dark .sh-progress header b, .dark .sh-card h3, .dark .sh-title { color: white; }
        .dark .sh-progress header span, .dark .sh-progress p, .dark .sh-step[data-done] .sh-title { color: var(--gray-400); }
        .dark .sh-bar { background: var(--gray-800); }
        .dark .sh-step { border-top-color: var(--gray-800); }
        .dark .sh-mark { border-color: var(--gray-600); }
        .dark .sh-step[data-done] .sh-state { color: var(--success-400); }
        @media (prefers-reduced-motion: no-preference) {
            .sh-step[data-done] .sh-mark { animation: sh-pop .45s cubic-bezier(.2, .9, .3, 1.3) .3s backwards; }
            @keyframes sh-pop { from { transform: scale(.4); opacity: 0; } }
        }
    </style>

    <div class="sh-stack">
        <section class="sh-card sh-progress" @if ($complete) data-complete @endif>
            <header>
                <span>{{ __($l.'progress.title') }}</span>
                <span><b data-count="{{ $done }}" data-count-key="setup:done" data-celebrate="setup:done" data-celebrate-value="{{ $done }}" data-celebrate-text="{{ __($l.'progress.cheer') }}">{{ $done }}</b> <small>{{ __($l.'progress.of', ['total' => $total]) }}</small></span>
            </header>
            <div class="sh-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $percent }}" aria-label="{{ __($l.'progress.title') }}"><i class="lms-bar-fill" style="width: {{ max(2, $percent) }}%"></i></div>
            <p>{{ $complete ? __($l.'progress.complete') : trans_choice($l.'progress.left', $left, ['count' => $left]) }}</p>
        </section>

        <div class="sh-groups lms-stagger">
            @foreach ($groups as $group)
                <section class="sh-card" wire:key="group-{{ $group['key'] }}">
                    <h3>{{ $group['label'] }}</h3>
                    @include('academy.start-here-steps', ['steps' => $group['steps']])
                </section>
            @endforeach
        </div>

        @if ($optional !== [])
            <section class="sh-card sh-optional">
                <h3>{{ __($l.'optional.title') }}</h3>
                <p>{{ __($l.'optional.hint') }}</p>
                @include('academy.start-here-steps', ['steps' => $optional])
            </section>
        @endif
    </div>
</x-filament-panels::page>
