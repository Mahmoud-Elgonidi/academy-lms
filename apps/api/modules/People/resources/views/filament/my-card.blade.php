@php
    $l = 'people::cards.';
    $p = $this->preview;
@endphp
<x-filament-panels::page>
    <style>
        .mcard-grid { display: grid; gap: 1.5rem; grid-template-columns: minmax(0, 1fr); align-items: start; }
        @media (min-width: 64rem) { .mcard-grid { grid-template-columns: minmax(0, 3fr) minmax(0, 2fr); } }
        .mcard-state { display: flex; gap: .55rem; align-items: center; padding: .7rem .9rem; border-radius: var(--radius-xl, .75rem); font-size: .9rem; margin-bottom: 1rem; }
        .mcard-state[data-state="on"] { background: var(--success-50); color: var(--success-800); }
        .mcard-state[data-state="off"], .mcard-state[data-state="none"] { background: var(--gray-100); color: var(--gray-700); }
        .mcard-state[data-state="hidden"] { background: var(--warning-50); color: var(--warning-900); }
        .dark .mcard-state[data-state="off"], .dark .mcard-state[data-state="none"] { background: var(--gray-800); color: var(--gray-200); }
        .mcard-save { display: flex; justify-content: flex-end; margin-top: 1rem; }
        .mcard-preview h3 { font-size: .8rem; font-weight: 600; color: var(--gray-500); margin-bottom: .6rem; }
        .mcard { border: 1px solid var(--gray-200); border-radius: var(--radius-xl, 1rem); background: white; padding: 1.25rem; display: flex; flex-direction: column; gap: .75rem; }
        .dark .mcard { background: var(--gray-900); border-color: var(--gray-800); }
        .mcard-head { display: flex; gap: .85rem; align-items: center; }
        .mcard-photo { width: 4rem; height: 4rem; border-radius: 999px; object-fit: cover; background: var(--primary-50); color: var(--primary-700); display: grid; place-items: center; font-weight: 700; font-size: 1.4rem; flex: none; }
        .mcard-name { font-weight: 700; font-size: 1.1rem; }
        .mcard-bio { white-space: pre-line; color: var(--gray-700); font-size: .92rem; }
        .dark .mcard-bio { color: var(--gray-300); }
        .mcard-subjects { display: flex; flex-wrap: wrap; gap: .35rem; }
        .mcard-subjects span { font-size: .78rem; padding: .15rem .6rem; border-radius: 999px; background: var(--primary-50); color: var(--primary-700); }
        .mcard-muted { font-size: .78rem; color: var(--gray-500); }
    </style>

    <div class="mcard-grid">
        <form wire:submit="save">
            <div class="mcard-state" data-state="{{ $p['state'] }}">
                <x-filament::icon :icon="$p['state'] === 'on' ? 'heroicon-o-eye' : 'heroicon-o-eye-slash'" class="h-5 w-5" />
                <span>{{ __($l.'state.'.$p['state']) }}</span>
            </div>
            {{ $this->form }}
            <div class="mcard-save">
                <x-filament::button type="submit" icon="heroicon-m-check">{{ __($l.'save') }}</x-filament::button>
            </div>
        </form>

        <aside class="mcard-preview" aria-live="polite">
            <h3>{{ __($l.'preview') }}</h3>
            <article class="mcard">
                <div class="mcard-head">
                    @if ($p['photo'])
                        <img class="mcard-photo" src="{{ $p['photo'] }}" alt="">
                    @else
                        <span class="mcard-photo" aria-hidden="true">{{ mb_substr($p['name'] !== '' ? $p['name'] : '?', 0, 1) }}</span>
                    @endif
                    <div class="mcard-name"><bdi>{{ $p['name'] }}</bdi></div>
                </div>
                @if ($p['bio'])<p class="mcard-bio">{{ $p['bio'] }}</p>@endif
                @if ($p['subjects'] !== [])
                    <div class="mcard-subjects">
                        @foreach ($p['subjects'] as $s)
                            <span>{{ $s->name }}@if ($s->levelFrom || $s->levelTo) · {{ $s->levelFrom ?? '…' }} – {{ $s->levelTo ?? '…' }}@endif</span>
                        @endforeach
                    </div>
                    <small class="mcard-muted">{{ __($l.'preview_subjects') }}</small>
                @endif
            </article>
        </aside>
    </div>
</x-filament-panels::page>
