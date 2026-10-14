<div class="cm-bell" data-live-now="{{ $renderedAt }}" data-live-channel="{{ $channel }}">
    <style>
        .cm-list { display: flex; flex-direction: column; max-height: 26rem; overflow-y: auto; }
        .cm-item { display: flex; gap: .6rem; text-align: start; padding: .6rem .75rem; border: 0; background: none; cursor: pointer; width: 100%; border-bottom: 1px solid var(--gray-100); }
        .cm-item:hover { background: var(--gray-50); }
        .cm-item strong { display: block; font-size: .85rem; color: var(--gray-900); }
        .cm-item p { margin: .15rem 0 0; font-size: .8rem; color: var(--gray-600); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .cm-item[data-open] p { display: block; -webkit-line-clamp: unset; overflow: visible; white-space: pre-line; overflow-wrap: anywhere; }
        .cm-item small { color: var(--gray-400); font-size: .72rem; }
        .cm-dot { flex: none; width: .5rem; height: .5rem; border-radius: 999px; margin-top: .4rem; background: transparent; }
        .cm-item[data-unread] .cm-dot { background: var(--primary-500); }
        .cm-item[data-urgent] .cm-dot { background: var(--danger-500); }
        .cm-head { display: flex; align-items: center; justify-content: space-between; padding: .6rem .75rem; border-bottom: 1px solid var(--gray-200); }
        .cm-head strong { font-size: .9rem; }
        .cm-head button { border: 0; background: none; color: var(--primary-700); font-size: .78rem; font-weight: 600; cursor: pointer; }
        .cm-empty { padding: 1.25rem; text-align: center; color: var(--gray-500); font-size: .85rem; }
        @media (max-width: 480px) {
            .fi-dropdown-panel:has(.cm-list) { width: calc(100vw - 5rem) !important; max-width: calc(100vw - 5rem) !important; }
        }
        .dark .cm-item:hover { background: var(--gray-800); }
        .dark .cm-item strong { color: var(--gray-100); }
        .dark .cm-item, .dark .cm-head { border-color: var(--gray-800); }
    </style>
    <x-filament::dropdown placement="bottom-end" width="sm" teleport>
        <x-slot name="trigger">
            <x-filament::icon-button icon="heroicon-o-bell" color="gray" :label="__('communication::communication.bell.title')"
                :badge="$this->unread ?: null" badge-color="danger" />
        </x-slot>

        <div class="cm-head">
            <strong>{{ __('communication::communication.bell.title') }}</strong>
            @if ($this->unread > 0)
                <button type="button" wire:click="markAllRead">{{ __('communication::communication.bell.mark_all') }}</button>
            @endif
        </div>
        <div class="cm-list">
            @forelse ($this->latest as $n)
                <button type="button" class="cm-item" wire:key="cm-{{ $n->id }}" wire:click="open('{{ $n->id }}')"
                    @if (! $n->read_at) data-unread @endif @if ($n->urgent && ! $n->read_at) data-urgent @endif @if ($expanded === $n->id) data-open aria-expanded="true" @endif>
                    <span class="cm-dot"></span>
                    <span>
                        <strong>{{ $n->title }}</strong>
                        <p>{{ $n->body }}</p>
                        <small>{{ $n->created_at->locale(app()->getLocale())->diffForHumans() }}</small>
                    </span>
                </button>
            @empty
                <div class="cm-empty">{{ __('communication::communication.bell.empty') }}</div>
            @endforelse
        </div>
    </x-filament::dropdown>
</div>

@script
<script>
    // Refreshes when a message arrives or is read on another device (LmsLive, shared/live.blade.php);
    // every minute only while there is no live connection.
    const el = $wire.$el;
    const live = window.LmsLive
        ? window.LmsLive.watch({ el, channel: el.dataset.liveChannel, event: '.inbox.changed', refresh: () => $wire.$refresh(), every: 60000 })
        : (setInterval(() => $wire.$refresh(), 60000), null);
    // "5 minutes ago" is only read when the list opens: bring it up to date then, if the list is more than a minute old.
    el.addEventListener('click', (e) => {
        if (live && e.target.closest('.fi-dropdown-trigger') && live.now() - Number(el.dataset.liveNow) > 60000) $wire.$refresh();
    });
</script>
@endscript
