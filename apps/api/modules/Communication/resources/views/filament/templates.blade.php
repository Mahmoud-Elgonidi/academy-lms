@php $l = 'communication::communication.'; @endphp
<x-filament-panels::page>
    <style>
        .tp { display: flex; flex-direction: column; gap: 1.25rem; }
        .tp h2 { margin: 0 0 .5rem; font-size: 1rem; font-weight: 700; }
        .tp-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: .75rem; }
        .tp-card { background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl); padding: .85rem 1rem; display: flex; flex-direction: column; gap: .4rem; }
        .tp-card strong { font-size: .9rem; }
        .tp-card p { margin: 0; color: var(--gray-700); font-size: .85rem; white-space: pre-line; }
        .tp-meta { display: flex; flex-wrap: wrap; gap: .35rem; align-items: center; font-size: .75rem; color: var(--gray-500); }
        .tp-chip { padding: .05rem .5rem; border-radius: 999px; background: var(--gray-100); color: var(--gray-700); font-weight: 600; }
        .tp-edited { background: var(--warning-50); color: var(--warning-800); }
        .tp-foot { display: flex; justify-content: space-between; align-items: center; margin-top: .2rem; }
        .tp-key { font-family: ui-monospace, monospace; font-size: .7rem; color: var(--gray-400); }
        .dark .tp-card { background: var(--gray-900); border-color: var(--gray-800); }
    </style>
    <div class="tp">
        @foreach ($this->groups() as $category => $templates)
            <section>
                <h2 class="lms-heading">{{ __($l.'categories.'.$category) }}</h2>
                <div class="tp-grid">
                    @foreach ($templates as $t)
                        <div class="tp-card" wire:key="tp-{{ $t['key'] }}">
                            <div class="tp-meta">
                                <span>{{ __($l.'templates.to') }}: {{ $t['audience'] }}</span>
                                @foreach ($t['channels'] as $c) <span class="tp-chip">{{ __($l.'channels.'.$c) }}</span> @endforeach
                                @if ($t['channels'] === []) <span class="tp-chip">{{ __($l.'channels.inbox') }}</span> @endif
                                @if ($t['edited']) <span class="tp-chip tp-edited">{{ __($l.'templates.edited') }}</span> @endif
                            </div>
                            <strong>{{ $t['title'] }}</strong>
                            <p>{{ $t['body'] }}</p>
                            <div class="tp-foot">
                                <span class="tp-key">{{ $t['key'] }}</span>
                                {{ ($this->editAction)(['key' => $t['key']]) }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</x-filament-panels::page>
