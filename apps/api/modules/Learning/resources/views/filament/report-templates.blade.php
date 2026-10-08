@php $l = 'learning::templates.'; $versions = $this->versions; $current = $versions[0] ?? null; $subjects = $this->subjects(); @endphp
<x-filament-panels::page>
    <style>
        .rt { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1.25rem; align-items: start; }
        @media (min-width: 1200px) { .rt { grid-template-columns: minmax(0, 1fr) 300px; } }
        .rt-subjects { display: flex; flex-wrap: wrap; gap: .5rem; }
        .rt-subjects button { padding: .35rem .9rem; border-radius: 999px; border: 1px solid var(--gray-200); background: white; font-size: .85rem; font-weight: 600; color: var(--gray-700); }
        .rt-subjects button[aria-pressed="true"] { background: var(--primary-600); border-color: var(--primary-600); color: white; }
        .rt-meta { display: flex; flex-wrap: wrap; gap: .4rem 1rem; align-items: baseline; font-size: .85rem; color: var(--gray-600); }
        .rt-meta strong { color: var(--gray-950); font-size: 1rem; }
        .rt-side { display: flex; flex-direction: column; gap: .6rem; background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl); padding: 1rem; font-size: .85rem; }
        .rt-side h3 { margin: 0; font-size: .9rem; font-weight: 700; }
        .rt-side small { color: var(--gray-500); }
        .rt-side ol { margin: 0; padding: 0; list-style: none; display: flex; flex-direction: column; gap: .4rem; }
        .rt-side li { padding: .4rem .6rem; border-radius: var(--radius-lg); background: var(--gray-50); }
        .dark .rt-subjects button { background: var(--gray-900); border-color: var(--gray-700); color: var(--gray-200); }
        .dark .rt-subjects button[aria-pressed="true"] { background: var(--primary-500); border-color: var(--primary-500); color: var(--gray-950); }
        .dark .rt-side { background: var(--gray-900); border-color: var(--gray-800); }
        .dark .rt-side li { background: var(--gray-800); }
        .dark .rt-meta strong { color: white; }
    </style>

    @if ($subjects === [])
        <x-filament::section>{{ __($l.'no_subjects') }}</x-filament::section>
    @else
        <div class="rt-subjects" role="group" aria-label="{{ __($l.'subject') }}">
            @foreach ($subjects as $id => $name)
                <button type="button" wire:click="pick('{{ $id }}')" aria-pressed="{{ $id === $this->subject ? 'true' : 'false' }}">{{ $name }}</button>
            @endforeach
        </div>

        <div class="rt">
            <form wire:submit="save">
                <x-filament::section
                    :heading="$current ? __($l.'version_meta', ['n' => $current->version, 'date' => $this->savedOn($current)]) : $subjects[$this->subject]"
                    :description="$current ? trans_choice($l.'reports_count', $this->reportsUsing($current), ['count' => $this->reportsUsing($current)]) : __($l.'no_version')"
                    icon="heroicon-o-queue-list"
                >
                    {{ $this->form }}
                    <x-slot name="footer">
                        <x-filament::button type="submit" icon="heroicon-m-check">{{ __($l.'save') }}</x-filament::button>
                    </x-slot>
                </x-filament::section>
            </form>

            @if (count($versions) > 1)
                <aside class="rt-side">
                    <h3>{{ __($l.'history') }}</h3>
                    <ol>
                        @foreach (array_slice($versions, 1) as $v)
                            <li>
                                {{ __($l.'history_line', ['n' => $v->version, 'date' => $this->savedOn($v), 'fields' => trans_choice($l.'fields_count', count($v->fields), ['count' => count($v->fields)])]) }}
                                <br><small>{{ trans_choice($l.'reports_count', $this->reportsUsing($v), ['count' => $this->reportsUsing($v)]) }}</small>
                            </li>
                        @endforeach
                    </ol>
                </aside>
            @endif
        </div>
    @endif
</x-filament-panels::page>
