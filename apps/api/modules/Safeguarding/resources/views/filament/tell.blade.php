@php
    $s = 'safeguarding::screens.';
    $look = $this->look();
    $teacher = $look === 'teacher';
    $kids = $look === 'kids';
    $say = fn (string $key) => $teacher ? __($s.'teacher.'.$key) : __($s.'family.'.$key.'.'.$look);
    $icons = [
        'adult_behaviour' => 'heroicon-o-face-frown', 'outside_contact' => 'heroicon-o-chat-bubble-left-right', 'content' => 'heroicon-o-eye-slash',
        'bullying' => 'heroicon-o-hand-raised', 'wellbeing' => 'heroicon-o-heart', 'other' => 'heroicon-o-ellipsis-horizontal-circle',
    ];
    $mine = $this->mine;
    $about = $this->learnerName();
@endphp
<x-filament-panels::page>
    <div data-live-channel="{{ $this->channel() }}" hidden></div>
    <style>
        .tu-lead { margin: 0; color: var(--gray-600); line-height: 1.7; max-width: 46rem; }
        .tu-danger { display: flex; gap: .55rem; align-items: flex-start; padding: .7rem .9rem; border-radius: var(--radius-lg, .5rem); background: var(--danger-50); color: var(--danger-800); font-weight: 600; font-size: .92rem; max-width: 46rem; }
        .tu-danger svg { width: 1.25rem; height: 1.25rem; flex: none; margin-top: .1rem; }
        .tu-grid { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr); gap: 1.1rem; align-items: start; }
        @media (max-width: 64rem) { .tu-grid { grid-template-columns: minmax(0, 1fr); } }
        .tu-card { background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl, .75rem); padding: 1.1rem 1.2rem; display: flex; flex-direction: column; gap: .95rem; }
        .tu-card h2 { margin: 0; font-size: 1rem; font-weight: 700; }
        .tu-about { font-size: .85rem; color: var(--gray-500); margin: 0; }
        .tu-field { display: flex; flex-direction: column; gap: .35rem; border: 0; padding: 0; margin: 0; min-width: 0; }
        .tu-field > label, .tu-field > legend { font-weight: 600; font-size: .9rem; padding: 0; margin-bottom: .35rem; }
        .tu-hint { font-size: .8rem; color: var(--gray-500); }
        .tu-error { font-size: .82rem; color: var(--danger-600); margin: 0; }
        .tu-cats { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 15rem), 1fr)); gap: .5rem; }
        .tu-cats[data-kids] { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .7rem; }
        .tu-cat { position: relative; display: flex; gap: .6rem; align-items: center; padding: .65rem .75rem; border: 1px solid var(--gray-200); border-radius: var(--radius-lg, .5rem); cursor: pointer; font-size: .9rem; line-height: 1.45; background: white; }
        .tu-cat input { position: absolute; opacity: 0; pointer-events: none; }
        .tu-cat svg { width: 1.35rem; height: 1.35rem; flex: none; color: var(--primary-600); }
        .tu-cat:has(input:checked) { border-color: var(--primary-500); background: var(--primary-50); box-shadow: 0 0 0 2px var(--primary-200); }
        .tu-cat:has(input:focus-visible) { outline: 2px solid var(--primary-500); outline-offset: 2px; }
        .tu-cats[data-kids] .tu-cat { flex-direction: column; text-align: center; padding: 1rem .6rem; font-size: 1rem; font-weight: 600; }
        .tu-cats[data-kids] .tu-cat svg { width: 2.4rem; height: 2.4rem; }
        .tu-select, .tu-text { width: 100%; border: 1px solid var(--gray-300); border-radius: var(--radius-lg, .5rem); padding: .55rem .7rem; font: inherit; background: white; color: inherit; }
        .tu-text { min-height: 7.5rem; resize: vertical; line-height: 1.6; }
        .tu-urgent { display: flex; gap: .55rem; align-items: center; font-weight: 600; font-size: .9rem; color: var(--danger-700); }
        .tu-urgent input { width: 1.1rem; height: 1.1rem; accent-color: var(--danger-600); }
        .tu-thanks { align-items: flex-start; }
        .tu-thanks svg { width: 2.5rem; height: 2.5rem; color: var(--success-600); }
        .tu-thanks p { margin: 0; font-size: 1.02rem; line-height: 1.7; }
        .tu-list { display: flex; flex-direction: column; gap: .6rem; list-style: none; margin: 0; padding: 0; }
        .tu-item { border: 1px solid var(--gray-200); border-radius: var(--radius-lg, .5rem); padding: .65rem .8rem; display: flex; flex-direction: column; gap: .3rem; font-size: .88rem; }
        .tu-item-top { display: flex; justify-content: space-between; gap: .5rem; align-items: center; flex-wrap: wrap; }
        .tu-muted { color: var(--gray-500); font-size: .8rem; }
        .tu-pill { font-size: .72rem; font-weight: 600; padding: .08rem .5rem; border-radius: 999px; background: var(--gray-100); color: var(--gray-700); white-space: nowrap; }
        .tu-pill[data-status="new"] { background: var(--warning-50); color: var(--warning-800); }
        .tu-pill[data-status="open"] { background: var(--primary-50); color: var(--primary-700); }
        .tu-pill[data-status="closed"] { background: var(--success-50); color: var(--success-700); }
        .tu-reply { margin: .2rem 0 0; padding: .5rem .65rem; border-radius: var(--radius-lg, .5rem); background: var(--gray-50); white-space: pre-line; }
        .tu-text-sent { margin: 0; white-space: pre-line; color: var(--gray-700); display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
        .tu-empty { padding: 1rem; text-align: center; color: var(--gray-500); border: 1px dashed var(--gray-300); border-radius: var(--radius-lg, .5rem); font-size: .88rem; }
        .dark .tu-card, .dark .tu-cat, .dark .tu-select, .dark .tu-text { background: var(--gray-900); border-color: var(--gray-700); }
        .dark .tu-reply { background: var(--gray-800); }
        .dark .tu-lead { color: var(--gray-300); }
    </style>

    <p class="tu-lead">{{ $say('lead') }}</p>
    <div class="tu-danger" role="note"><x-filament::icon icon="heroicon-o-exclamation-triangle" />{{ $say('danger') }}</div>

    <div class="tu-grid">
        @if ($this->sent)
            <section class="tu-card tu-thanks" wire:key="thanks" aria-live="polite">
                <x-filament::icon icon="heroicon-o-check-circle" />
                <p>{{ $say('sent') }}</p>
                <span class="tu-muted">{{ __($s.'mine.number', ['number' => $this->sent]) }}</span>
                <x-filament::button color="gray" size="sm" wire:click="again" icon="heroicon-m-plus">{{ $teacher ? __($s.'teacher.again') : __($s.'family.again') }}</x-filament::button>
            </section>
        @else
            <form class="tu-card" wire:submit="send" wire:key="form">
                @if ($about)<p class="tu-about">{{ __($s.'family.about', ['name' => $about]) }}</p>@endif

                <fieldset class="tu-field">
                    <legend>{{ $teacher ? __('safeguarding::concerns.fields.category') : __($s.'family.what.'.$look) }}</legend>
                    <div class="tu-cats" @if ($kids) data-kids @endif>
                        @foreach ($this->categories as $value => $label)
                            <label class="tu-cat" wire:key="cat-{{ $value }}">
                                <input type="radio" name="category" value="{{ $value }}" wire:model="category">
                                <x-filament::icon :icon="$icons[$value] ?? 'heroicon-o-flag'" />
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('category')<p class="tu-error">{{ $message }}</p>@enderror
                </fieldset>

                @if ($teacher)
                    <div class="tu-field">
                        <label for="tu-student">{{ __($s.'teacher.student') }}</label>
                        <select id="tu-student" class="tu-select" wire:model="student">
                            <option value="">{{ __($s.'teacher.no_student') }}</option>
                            @foreach ($this->students as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                        </select>
                        @error('student')<p class="tu-error">{{ $message }}</p>@enderror
                    </div>
                @endif

                @if (! $kids && $this->lessons !== [])
                    <div class="tu-field">
                        <label for="tu-lesson">{{ __($s.'form.lesson') }}</label>
                        <select id="tu-lesson" class="tu-select" wire:model="lesson">
                            <option value="">{{ __($s.'form.no_lesson') }}</option>
                            @foreach ($this->lessons as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                        </select>
                        @error('lesson')<p class="tu-error">{{ $message }}</p>@enderror
                    </div>
                @endif

                <div class="tu-field">
                    <label for="tu-text">{{ $teacher ? __('safeguarding::concerns.fields.body') : __($s.'family.text.'.$look) }}</label>
                    <textarea id="tu-text" class="tu-text" wire:model="text" maxlength="5000" @if ($kids) rows="3" @endif></textarea>
                    @unless ($kids)<span class="tu-hint">{{ __($s.'family.text_hint') }}</span>@endunless
                    @error('body')<p class="tu-error">{{ $message }}</p>@enderror
                </div>

                @unless ($kids)
                    <label class="tu-urgent"><input type="checkbox" wire:model="urgent">{{ __($s.'form.urgent') }}</label>
                @endunless

                <div>
                    <x-filament::button type="submit" icon="heroicon-m-paper-airplane" :size="$kids ? 'xl' : 'md'">{{ $teacher ? __($s.'teacher.send') : __($s.'family.send.'.$look) }}</x-filament::button>
                </div>
            </form>
        @endif

        <section class="tu-card">
            <h2>{{ __($s.'mine.title') }}</h2>
            @if ($mine === [])
                <div class="tu-empty">{{ __($s.'mine.empty') }}</div>
            @else
                <ul class="tu-list lms-stagger">
                    @foreach ($mine as $c)
                        <li class="tu-item" wire:key="mine-{{ $c['id'] }}">
                            <div class="tu-item-top">
                                <strong>{{ $c['category']['label'] }}</strong>
                                <span class="tu-pill" data-status="{{ $c['status']['key'] }}">{{ $c['status']['label'] }}</span>
                            </div>
                            <span class="tu-muted">{{ __($s.'mine.number', ['number' => $c['number']]) }} · {{ __($s.'mine.sent_on', ['when' => $c['created_at']['label']]) }}</span>
                            @if ($c['text'] !== '')<p class="tu-text-sent">{{ $c['text'] }}</p>@endif
                            @if ($c['reply'])
                                <div><span class="tu-muted">{{ __($s.'mine.reply') }}</span><p class="tu-reply">{{ $c['reply'] }}</p></div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</x-filament-panels::page>

@script
<script>
    // A lead takes or closes what this person raised: the page follows their own live channel (LmsLive).
    const el = $wire.$el.querySelector('[data-live-channel]');
    if (el && window.LmsLive) {
        window.LmsLive.watch({ el, channel: el.dataset.liveChannel, event: '.concerns.changed', refresh: () => $wire.$refresh(), every: 120000 });
    }
</script>
@endscript
