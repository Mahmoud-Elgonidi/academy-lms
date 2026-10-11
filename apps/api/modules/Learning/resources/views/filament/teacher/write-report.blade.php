@php $map = $this->roadmap(); $locked = $this->sessionReport->status === 'reviewed'; @endphp
<x-filament-panels::page>
    <style>
        .wr { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1.25rem; align-items: start; }
        @media (min-width: 1200px) { .wr { grid-template-columns: minmax(0, 1fr) 340px; } }
        .wr-side { display: flex; flex-direction: column; gap: .75rem; background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl); padding: 1rem; }
        .wr-side h3 { margin: 0; font-size: .9rem; font-weight: 700; }
        .wr-side small { color: var(--gray-500); }
        .wr-note { padding: .6rem .8rem; border-radius: var(--radius-lg); background: var(--info-50); color: var(--info-800); font-size: .85rem; }
        .dark .wr-side { background: var(--gray-900); border-color: var(--gray-800); }
    </style>

    @if ($locked)
        <div class="wr-note">{{ __('learning::teacher.write.locked') }}</div>
    @endif
    @if ($this->sessionReport->review_note)
        <div class="wr-note"><strong>{{ __('learning::reports.detail.review_note') }}:</strong> {{ $this->sessionReport->review_note }}</div>
    @endif

    <div class="wr">
        <form wire:submit="submit" class="flex flex-col gap-6">
            {{ $this->form }}
            @unless ($locked)
                <div class="flex flex-wrap gap-2">
                    <x-filament::button type="submit">{{ $this->sessionReport->isOpen() ? __('learning::teacher.write.submit') : __('learning::teacher.write.save') }}</x-filament::button>
                    @if (! $this->sessionReport->isOpen() && $this->sessionReport->kind === 'lesson')
                        <x-filament::button color="gray" icon="heroicon-m-puzzle-piece" tag="a" :href="\Modules\Learning\Filament\Teacher\HomeworkGame::getUrl(['report' => $this->sessionReport->id])">{{ __('learning::homework.game.open') }}</x-filament::button>
                    @endif
                </div>
            @endunless
        </form>

        @if ($map)
            <aside class="wr-side">
                <h3>{{ __('learning::teacher.write.roadmap') }} · {{ $map['program']['name'] }}</h3>
                <div>
                    <strong>{{ $map['percent'] }}%</strong>
                    <small>· {{ __('learning::progress.lessons', $map['lessons']) }}</small>
                    <div class="ac-meter" style="height:.45rem; border-radius:999px; background:var(--gray-100); overflow:hidden; margin-top:.3rem"><i style="display:block; height:100%; width: {{ $map['percent'] }}%; background:var(--primary-500)"></i></div>
                </div>
                @foreach ($this->plannedObjectives() as $title => $objectives)
                    <div>
                        <small>{{ __('learning::teacher.write.objectives', ['lesson' => $title]) }}</small>
                        <p style="margin:.2rem 0 0; font-size:.85rem; white-space:pre-line">{{ $objectives }}</p>
                    </div>
                @endforeach
                @include('learning::filament.roadmap', ['map' => $map])
            </aside>
        @endif
    </div>
</x-filament-panels::page>
