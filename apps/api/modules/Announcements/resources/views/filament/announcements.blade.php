@php
    $l = 'announcements::announcements.';
    $reach = $this->reach;
    $left = $this->left;
    $focused = $this->focused;
@endphp
<x-filament-panels::page>
    {{-- The academy's colours through Filament's variables; the cards come in one by one and the count counts up (shared motion layer). --}}
    <style>
        .an-stack { display: flex; flex-direction: column; gap: 1.25rem; }
        .an-compose { display: grid; grid-template-columns: minmax(0, 1fr) 17rem; gap: 1rem; align-items: start; }
        @media (max-width: 1024px) { .an-compose { grid-template-columns: minmax(0, 1fr); } }
        .an-card { background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl, .75rem); padding: 1.1rem 1.2rem; }
        .an-side { display: flex; flex-direction: column; gap: .75rem; position: sticky; top: 5rem; }
        .an-tile { display: flex; flex-direction: column; gap: .2rem; padding: .9rem 1rem; background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl, .75rem); border-inline-start: 3px solid var(--primary-500); }
        .an-tile small { color: var(--gray-500); font-size: .78rem; }
        .an-tile strong { font-size: 1.9rem; line-height: 1.15; font-variant-numeric: tabular-nums; color: var(--gray-950); }
        .an-tile p { margin: .2rem 0 0; color: var(--gray-500); font-size: .76rem; line-height: 1.5; }
        .an-tile[data-key="left"] strong { font-size: 1.1rem; }
        .an-tile[data-key="left"][data-none] { border-inline-start-color: var(--danger-500); }
        .an-tile[data-key="left"][data-none] strong { color: var(--danger-700); }
        .an-tile[data-key="reach"][data-none] { border-inline-start-color: var(--gray-300); }
        .an-focus { border-inline-start: 3px solid var(--primary-500); }
        .an-focus h3 { margin: 0 0 .4rem; font-size: 1.05rem; font-weight: 600; color: var(--gray-950); }
        .an-body { margin: 0; white-space: pre-line; overflow-wrap: anywhere; color: var(--gray-700); line-height: 1.75; }
        .an-meta { display: block; margin-top: .6rem; color: var(--gray-500); font-size: .8rem; }
        .dark .an-card, .dark .an-tile { background: var(--gray-900); border-color: var(--gray-800); }
        .dark .an-tile strong, .dark .an-focus h3 { color: white; }
        .dark .an-body { color: var(--gray-300); }
        .dark .an-tile[data-key="left"][data-none] strong { color: var(--danger-400); }
    </style>

    <div class="an-stack">
        @if ($focused)
            <article class="an-card an-focus" wire:key="focus-{{ $focused->id }}">
                <h3>{{ $focused->title }}</h3>
                <p class="an-body">{{ $focused->body }}</p>
                <small class="an-meta">{{ $this->meta($focused) }}</small>
            </article>
        @endif

        <div class="an-compose">
            <form wire:submit.prevent class="an-card">
                {{ $this->form }}
            </form>

            <aside class="an-side lms-stagger">
                <div class="an-tile" data-key="reach" @if ($reach === 0) data-none @endif wire:key="reach">
                    <small>{{ __($l.'reach.label') }}</small>
                    <strong data-count="{{ $reach }}">{{ $reach }}</strong>
                    <p>{{ __($l.'reach.note') }}</p>
                </div>
                <div class="an-tile" data-key="left" @if ($left === 0) data-none @endif wire:key="left">
                    <strong>{{ trans_choice($l.'reach.left', $left, ['n' => $left]) }}</strong>
                    <p>{{ __($l.'reach.cap', ['n' => \Modules\Announcements\Domain\AnnouncementRules::PER_DAY]) }}</p>
                </div>
                <div>{{ $this->sendAction }}</div>
            </aside>
        </div>

        {{ $this->table }}
    </div>

</x-filament-panels::page>
