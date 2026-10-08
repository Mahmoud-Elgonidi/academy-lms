@php
    $l = 'support::tickets.queue.';
    $counts = $this->counts;
    $rows = $this->rows;
@endphp
<x-filament-panels::page>
    @include('support::filament.styles')
    <div data-live-channel="{{ $this->liveChannel() }}" hidden></div>

    @if ($counts['late'] > 0)
        <div class="sp-banner" role="status">
            <x-filament::icon icon="heroicon-o-exclamation-triangle" />
            <span>{{ trans_choice($l.'late', $counts['late'], ['count' => $counts['late']]) }}</span>
        </div>
    @endif

    <div>
        <div class="sp-tabs" role="group" aria-label="{{ __($l.'title') }}">
            @foreach ($this->tabs() as $tab)
                <button type="button" wire:click="$set('tab', '{{ $tab }}')" aria-pressed="{{ $this->tab === $tab ? 'true' : 'false' }}">
                    {{ __($l.'tabs.'.$tab) }}
                    @if (($counts[$tab] ?? 0) > 0)
                        <span class="sp-count" @if ($tab === 'complaints' || ($tab === 'todo' && $counts['late'] > 0)) data-tone="danger" @endif>{{ $counts[$tab] }}</span>
                    @endif
                </button>
            @endforeach
        </div>
        <div class="sp-filters">
            <input type="search" class="sp-input" wire:model.live.debounce.400ms="search" placeholder="{{ __($l.'search') }}" aria-label="{{ __($l.'search') }}">
            <select class="sp-select" wire:model.live="kind" aria-label="{{ __('support::tickets.page.kind') }}">
                <option value="">{{ __($l.'all_kinds') }}</option>
                @foreach (\Modules\Support\Enums\TicketKind::cases() as $k)
                    <option value="{{ $k->value }}">{{ $k->label() }}</option>
                @endforeach
            </select>
            <select class="sp-select" wire:model.live="priority" aria-label="{{ __('support::tickets.page.priority') }}">
                <option value="">{{ __($l.'all_priorities') }}</option>
                @foreach (\Modules\Support\Enums\TicketPriority::cases() as $p)
                    <option value="{{ $p->value }}">{{ $p->label() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="sp-list lms-stagger">
        @forelse ($rows as $r)
            <a href="{{ $r['url'] }}" wire:navigate wire:key="t-{{ $r['id'] }}" class="sp-card sp-row lms-lift" @if (($r['target']['state'] ?? null) === 'late') data-late @endif>
                <div class="sp-row-main">
                    <div class="sp-row-title">
                        <span class="sp-ref">{{ $r['reference'] }}</span>
                        <strong>{{ $r['subject'] }}</strong>
                    </div>
                    <div class="sp-meta">
                        <span>{{ $r['requester'] }}@if ($r['side'] === 'teacher') · {{ __($l.'from_teacher') }}@endif</span>
                        @if ($r['student'])<span>{{ __($l.'about', ['name' => $r['student']]) }}</span>@endif
                        @if ($r['teacher'] && $r['kind'] === 'complaint')<span>{{ __($l.'teacher', ['name' => $r['teacher']]) }}</span>@endif
                    </div>
                    <div class="sp-tags">
                        <span class="sp-pill" data-kind="{{ $r['kind'] }}">{{ $r['kindLabel'] }}</span>
                        @if (in_array($r['priority'], ['high', 'urgent'], true))<span class="sp-pill" data-priority="{{ $r['priority'] }}">{{ $r['priorityLabel'] }}</span>@endif
                        @if ($r['complaint'])<span class="sp-pill" data-complaint="{{ $r['complaint'] }}">{{ __('support::tickets.complaint.'.$r['complaint']) }}</span>@endif
                        @if ($r['target'])<span class="sp-pill" data-target="{{ $r['target']['state'] }}">{{ $r['target']['text'] }}</span>@endif
                    </div>
                </div>
                <div class="sp-side">
                    <span class="sp-pill" data-status="{{ $r['status'] }}">{{ $r['statusLabel'] }}</span>
                    <span>{{ $r['assignee'] ?? __($l.'nobody') }}</span>
                    <span>{{ $r['last'] }}</span>
                </div>
            </a>
        @empty
            <div class="sp-empty">{{ $this->tab === 'todo' && $this->search === '' && ! $this->kind && ! $this->priority ? __($l.'empty_todo') : __($l.'empty') }}</div>
        @endforelse
    </div>
</x-filament-panels::page>

@script
<script>
    // Families and teachers write, colleagues answer: the queue follows the academy's support channel (LmsLive).
    const el = $wire.$el.querySelector('[data-live-channel]');
    if (el && window.LmsLive) {
        window.LmsLive.watch({ el, channel: el.dataset.liveChannel, event: '.changed', refresh: () => $wire.$refresh(), every: 60000 });
    }
</script>
@endscript
