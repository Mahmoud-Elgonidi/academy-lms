{{-- The academy's page settings: the cards its teachers made, each with the way to take it off the page or put it back. --}}
@php
    /** @var list<\Modules\People\Contracts\DTOs\TeacherCardSummary> $cards */
    $s = 'site::site.settings.';
@endphp
<div class="site-cards">
    <p class="site-cards-hint">{{ __($s.'teachers_hint') }}</p>
    @if ($off)
        <p class="site-cards-off">{{ __($s.'teachers_off') }}</p>
    @endif
    @if ($cards === [])
        <div class="site-cards-empty">{{ __($s.'teachers_empty') }}</div>
    @else
        <ul class="site-cards-list lms-stagger">
            @foreach ($cards as $card)
                @php
                    $state = match (true) {
                        ! $card->active => 'inactive',
                        $card->hiddenByAcademy => 'hidden',
                        ! $card->shown => 'teacher_off',
                        default => 'shown',
                    };
                @endphp
                <li wire:key="card-{{ $card->id }}" data-state="{{ $state }}">
                    @if ($card->photoUrl)
                        <img class="site-cards-photo" src="{{ $card->photoUrl }}" alt="">
                    @else
                        <span class="site-cards-photo" aria-hidden="true">{{ mb_substr($card->displayName, 0, 1) }}</span>
                    @endif
                    <div class="site-cards-who">
                        <strong><bdi>{{ $card->displayName }}</bdi></strong>
                        <small><bdi>{{ $card->teacherName }}</bdi> · <span class="site-cards-state">{{ __($s.'card.'.$state) }}</span></small>
                        @if ($card->bio)<p>{{ $card->bio }}</p>@endif
                    </div>
                    {{ ($this->cardAction)(['card' => $card->id, 'hide' => ! $card->hiddenByAcademy]) }}
                </li>
            @endforeach
        </ul>
    @endif
</div>
<style>
    .site-cards { display: grid; gap: .85rem; }
    .site-cards-hint { color: var(--gray-600); font-size: .9rem; }
    .dark .site-cards-hint { color: var(--gray-400); }
    .site-cards-off { padding: .6rem .85rem; border-radius: var(--radius-lg, .5rem); background: var(--warning-50); color: var(--warning-900); font-size: .88rem; }
    .site-cards-empty { padding: 1.2rem; text-align: center; color: var(--gray-500); border: 1px dashed var(--gray-300); border-radius: var(--radius-xl, .75rem); }
    .site-cards-list { display: grid; gap: .6rem; }
    .site-cards-list li { display: flex; gap: .85rem; align-items: center; padding: .75rem .9rem; border: 1px solid var(--gray-200); border-radius: var(--radius-xl, .75rem); }
    .dark .site-cards-list li { border-color: var(--gray-800); }
    .site-cards-list li[data-state="hidden"], .site-cards-list li[data-state="inactive"], .site-cards-list li[data-state="teacher_off"] { opacity: .75; }
    .site-cards-photo { width: 2.75rem; height: 2.75rem; border-radius: 999px; object-fit: cover; flex: none; display: grid; place-items: center; background: var(--primary-50); color: var(--primary-700); font-weight: 700; }
    .site-cards-who { flex: 1; min-width: 0; display: grid; gap: .15rem; }
    .site-cards-who small { color: var(--gray-500); }
    .site-cards-who p { font-size: .85rem; color: var(--gray-700); white-space: pre-line; overflow-wrap: anywhere; }
    .dark .site-cards-who p { color: var(--gray-300); }
    .site-cards-list li[data-state="shown"] .site-cards-state { color: var(--success-700); }
    .site-cards-list li[data-state="hidden"] .site-cards-state { color: var(--danger-700); }
</style>
