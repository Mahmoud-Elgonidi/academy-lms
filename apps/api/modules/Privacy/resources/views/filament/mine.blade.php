@php
    $l = 'privacy::screens.mine.';
    $m = $this->mine;
@endphp
<x-filament-panels::page>
    <div data-live-channel="{{ $m['channel'] }}" hidden></div>
    <style>
        .md-lead { margin: 0; color: var(--gray-600); }
        .md-stack { display: flex; flex-direction: column; gap: 1rem; }
        .md-card { background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl, .75rem); padding: 1rem 1.1rem; display: flex; flex-direction: column; gap: .9rem; }
        .md-card h3 { margin: 0; font-size: 1.05rem; font-weight: 700; }
        .md-parts { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 17rem), 1fr)); gap: .9rem; }
        .md-part { border: 1px solid var(--gray-100); border-radius: var(--radius-lg, .5rem); padding: .8rem .9rem; display: flex; flex-direction: column; gap: .5rem; }
        .md-part h4 { margin: 0; font-size: .95rem; font-weight: 600; }
        .md-part p { margin: 0; font-size: .87rem; color: var(--gray-600); line-height: 1.6; }
        .md-state { font-size: .85rem; padding: .45rem .6rem; border-radius: var(--radius-lg, .5rem); background: var(--gray-50); }
        .md-state[data-tone="good"] { background: var(--success-50); color: var(--success-800); }
        .md-state[data-tone="warn"] { background: var(--warning-50); color: var(--warning-900); }
        .md-state[data-tone="bad"] { background: var(--danger-50); color: var(--danger-800); }
        .md-row { display: flex; gap: .6rem; align-items: center; flex-wrap: wrap; }
        .md-h2 { font-size: 1rem; font-weight: 700; margin: .5rem 0 .4rem; }
        .md-list { list-style: none; margin: 0; padding: 0; background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl, .75rem); }
        .md-list li { padding: .6rem .9rem; border-bottom: 1px solid var(--gray-100); display: flex; justify-content: space-between; gap: .75rem; flex-wrap: wrap; font-size: .88rem; }
        .md-list li:last-child { border-bottom: 0; }
        .md-muted { color: var(--gray-500); font-size: .82rem; }
        .md-details summary { cursor: pointer; font-size: .85rem; color: var(--primary-700); }
        .md-details ul { margin: .4rem 0 0; padding-inline-start: 1.1rem; font-size: .84rem; color: var(--gray-600); }
        .md-links { display: flex; gap: 1rem; flex-wrap: wrap; font-size: .85rem; }
        .md-empty { padding: 1rem; text-align: center; color: var(--gray-500); border: 1px dashed var(--gray-300); border-radius: var(--radius-xl, .75rem); font-size: .9rem; }
        .dark .md-card, .dark .md-list { background: var(--gray-900); border-color: var(--gray-800); }
        .dark .md-part { border-color: var(--gray-800); }
        .dark .md-state { background: var(--gray-800); }
    </style>

    <p class="md-lead">{{ __($l.'lead') }}</p>

    <div class="md-stack lms-stagger">
        @foreach ($m['cards'] as $card)
            @php
                $export = $card['export'];
                $erasure = $card['erasure'];
            @endphp
            <article class="md-card" wire:key="card-{{ $card['id'] }}">
                <h3>{{ $card['name'] }}</h3>
                <div class="md-parts">
                    <section class="md-part">
                        <h4>{{ __($l.'copy.title') }}</h4>
                        <p>{{ __($l.'copy.body', ['days' => \Modules\Privacy\Models\DataRequest::KEEP_DAYS]) }}</p>
                        @if ($export && $export['status'] === 'pending')
                            <div class="md-state" data-tone="warn">{{ __($l.'copy.preparing') }}</div>
                        @elseif ($export && $export['download_url'])
                            <div class="md-state md-row" data-tone="good">
                                <x-filament::link :href="$export['download_url']" icon="heroicon-o-arrow-down-tray">{{ __($l.'copy.download') }}</x-filament::link>
                                <span class="md-muted">{{ __($l.'copy.until', ['when' => $this->when($export['expires_at'])]) }}</span>
                            </div>
                        @endif
                        @if ($card['can_copy'])
                            <div><x-filament::button size="sm" color="gray" icon="heroicon-o-document-arrow-down" wire:click="askCopy('{{ $card['id'] }}')">{{ __($l.'copy.action') }}</x-filament::button></div>
                        @endif
                    </section>

                    <section class="md-part">
                        <h4>{{ __($l.'erase.title') }}</h4>
                        <p>{{ __($l.'erase.body', ['days' => \Modules\Privacy\Models\DataRequest::ANSWER_DAYS]) }}</p>
                        @if ($erasure && $erasure['status'] === 'pending')
                            <div class="md-state" data-tone="warn">{{ __($l.'erase.waiting', ['when' => $this->when($erasure['due_at'])]) }}</div>
                        @elseif ($erasure && $erasure['status'] === 'approved')
                            <div class="md-state" data-tone="bad">{{ __($l.'erase.approved', ['when' => $this->when($erasure['erase_after'])]) }}</div>
                        @elseif ($erasure && $erasure['status'] === 'refused')
                            <div class="md-state">{{ __($l.'erase.refused', ['answer' => $erasure['answer']]) }}</div>
                        @endif
                        <div class="md-row">
                            @if ($erasure && in_array($erasure['status'], \Modules\Privacy\Models\DataRequest::OPEN, true))
                                <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-uturn-left" wire:click="cancelRequest('{{ $erasure['id'] }}')">{{ __($l.'erase.cancel') }}</x-filament::button>
                            @else
                                {{ ($this->eraseAction)(['subject' => $card['id']]) }}
                            @endif
                        </div>
                        <details class="md-details">
                            <summary>{{ __('privacy::screens.office.goes') }} · {{ __('privacy::screens.office.stays') }}</summary>
                            <ul>@foreach ($m['goes'] as $g)<li>{{ $g }}</li>@endforeach</ul>
                            <ul>@foreach ($m['stays'] as $g)<li>{{ $g }}</li>@endforeach</ul>
                            <p class="md-muted">{{ __('privacy::requests.stays_note') }}</p>
                        </details>
                    </section>
                </div>
            </article>
        @endforeach
    </div>

    <section>
        <h2 class="md-h2">{{ __($l.'history') }}</h2>
        @if ($m['history'] === [])
            <div class="md-empty">{{ __($l.'none') }}</div>
        @else
            <ul class="md-list">
                @foreach ($m['history'] as $r)
                    <li wire:key="h-{{ $r['id'] }}">
                        <span>
                            {{ __('privacy::requests.kinds.'.$r['kind']) }} · {{ __($l.'about', ['name' => $r['subject']]) }}
                            @if ($r['requested_by'] !== auth()->id() && $r['requested_by'] !== $r['subject_id'])<span class="md-muted">({{ __($l.'by_office') }})</span>@endif
                        </span>
                        <span class="md-muted">{{ __('privacy::requests.statuses.'.$r['status']) }} · {{ $this->when($r['created_at']) }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <nav class="md-links" aria-label="{{ __('privacy::legal.nav.privacy') }}">
        @foreach ($m['links'] as $key => $href)
            <x-filament::link :href="$href" target="_blank" icon="heroicon-o-arrow-top-right-on-square" size="sm">{{ __('privacy::legal.nav.'.$key) }}</x-filament::link>
        @endforeach
    </nav>

    <x-filament-actions::modals />
</x-filament-panels::page>

@script
<script>
    // A copy gets ready, the academy answers: the page follows the person's own live channel (LmsLive).
    const el = $wire.$el.querySelector('[data-live-channel]');
    if (el && window.LmsLive) {
        window.LmsLive.watch({ el, channel: el.dataset.liveChannel, event: '.privacy.changed', refresh: () => $wire.$refresh(), every: 120000 });
    }
</script>
@endscript
