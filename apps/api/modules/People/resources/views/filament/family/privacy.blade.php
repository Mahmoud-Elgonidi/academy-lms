@php $l = 'people::consent.'; $p = $this->privacy; @endphp
<x-filament-panels::page>
    <div data-live-channel="{{ $p['channel'] }}" hidden></div>
    <p class="pp-lead">{{ __($l.'family.lead', ['age' => $p['age']]) }}</p>
    <div class="pp-stack lms-stagger">
        @forelse ($p['children'] as $child)
            <article class="pp-card" wire:key="consent-{{ $child['id'] }}" data-waiting="{{ $child['waiting'] ? 'yes' : 'no' }}">
                <div class="pp-head">
                    <h3>{{ $child['name'] }}</h3>
                    <span class="pp-pill" data-tone="{{ $child['waiting'] ? 'warn' : 'good' }}">{{ $child['waiting'] ? __($l.'state.waiting') : __($l.'state.granted') }}</span>
                </div>
                @if ($child['waiting'])
                    <p class="pp-intro">{{ $child['text']['intro'] }}</p>
                    <ul class="pp-points">
                        @foreach ($child['text']['sections'] as $section)
                            <li><strong>{{ $section['title'] }}</strong><span>{{ $section['body'] }}</span></li>
                        @endforeach
                    </ul>
                    <label class="pp-agree">
                        <input type="checkbox" wire:model="agreed.{{ $child['id'] }}">
                        <span>{{ $child['text']['agree'] }}</span>
                    </label>
                    @error('agreed.'.$child['id'])<p class="pp-error">{{ $message }}</p>@enderror
                    <div>
                        <x-filament::button wire:click="agree('{{ $child['id'] }}')" icon="heroicon-o-check">{{ __($l.'actions.agree') }}</x-filament::button>
                    </div>
                @else
                    <p class="pp-muted">{{ $child['by'] }}</p>
                    <div>
                        <x-filament::button color="danger" outlined size="sm" icon="heroicon-o-no-symbol"
                            wire:click="withdraw('{{ $child['id'] }}')" wire:confirm="{{ __($l.'actions.withdraw_confirm', ['student' => $child['name']]) }}">
                            {{ __($l.'actions.withdraw') }}
                        </x-filament::button>
                    </div>
                @endif
            </article>
        @empty
            <div class="pp-empty">{{ __($l.'family.none') }}</div>
        @endforelse
    </div>

    <style>
        .pp-lead { margin: 0; color: var(--gray-600); }
        .pp-stack { display: flex; flex-direction: column; gap: 1rem; }
        .pp-card { background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl, .75rem); padding: 1rem 1.1rem; display: flex; flex-direction: column; gap: .75rem; }
        .pp-card[data-waiting="yes"] { border-color: var(--warning-300); box-shadow: 0 0 0 3px var(--warning-50); }
        .pp-head { display: flex; justify-content: space-between; align-items: center; gap: .75rem; flex-wrap: wrap; }
        .pp-head h3 { margin: 0; font-size: 1.05rem; font-weight: 700; }
        .pp-pill { font-size: .75rem; padding: .1rem .6rem; border-radius: 999px; background: var(--gray-100); color: var(--gray-700); }
        .pp-pill[data-tone="good"] { background: var(--success-50); color: var(--success-700); }
        .pp-pill[data-tone="warn"] { background: var(--warning-50); color: var(--warning-700); }
        .pp-intro { margin: 0; line-height: 1.7; }
        .pp-points { list-style: none; margin: 0; padding: 0; display: grid; gap: .5rem; }
        .pp-points li { background: var(--primary-50); border-radius: var(--radius-lg, .5rem); padding: .6rem .75rem; display: flex; flex-direction: column; gap: .1rem; font-size: .9rem; }
        .pp-points strong { font-size: .88rem; }
        .pp-agree { display: flex; gap: .6rem; align-items: flex-start; border: 1px solid var(--gray-200); border-radius: var(--radius-lg, .5rem); padding: .7rem .8rem; font-weight: 600; font-size: .92rem; cursor: pointer; }
        .pp-agree input { margin-top: .25rem; accent-color: var(--primary-600); width: 1.1rem; height: 1.1rem; flex: none; }
        .pp-error { margin: 0; color: var(--danger-600); font-size: .85rem; }
        .pp-muted { margin: 0; color: var(--gray-500); font-size: .9rem; }
        .pp-empty { padding: 1.5rem; text-align: center; color: var(--gray-500); border: 1px dashed var(--gray-300); border-radius: var(--radius-xl, .75rem); }
        .dark .pp-card { background: var(--gray-900); border-color: var(--gray-800); }
        .dark .pp-points li { background: var(--gray-800); }
    </style>
</x-filament-panels::page>

@script
<script>
    // Another guardian may agree, or the office record it: the page follows the person's own live channel (LmsLive).
    const el = $wire.$el.querySelector('[data-live-channel]');
    if (el && window.LmsLive) {
        window.LmsLive.watch({ el, channel: el.dataset.liveChannel, event: '.consents.changed', refresh: () => $wire.$refresh(), every: 120000 });
    }
</script>
@endscript
