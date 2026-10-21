@php $l = 'platform::site.requests.'; $requests = $this->requests; @endphp
<x-filament-panels::page>
    @include('platform::console.styles')
    <style>
        .sr-filters { display: flex; flex-wrap: wrap; gap: .4rem; }
        .sr-filters a { padding: .35rem .8rem; border-radius: 999px; font-size: .85rem; border: 1px solid var(--gray-200); color: var(--gray-700); }
        .sr-filters a[aria-current] { background: var(--primary-600); border-color: var(--primary-600); color: white; }
        .dark .sr-filters a { border-color: var(--gray-700); color: var(--gray-300); }
        .sr-list { display: flex; flex-direction: column; gap: .75rem; }
        .sr-card { padding: 1rem 1.15rem; display: flex; flex-direction: column; gap: .6rem; }
        .sr-head { display: flex; flex-wrap: wrap; justify-content: space-between; gap: .5rem; align-items: baseline; }
        .sr-head h3 { font-weight: 700; font-size: 1.05rem; }
        .sr-head time { font-size: .8rem; color: var(--gray-500); }
        .sr-facts { display: flex; flex-wrap: wrap; gap: .4rem 1.1rem; font-size: .88rem; color: var(--gray-700); }
        .dark .sr-facts { color: var(--gray-300); }
        .sr-facts a { color: var(--primary-600); text-decoration: underline; }
        .sr-message { white-space: pre-line; font-size: .9rem; padding: .6rem .8rem; border-radius: .6rem; background: var(--gray-50); }
        .dark .sr-message { background: var(--gray-900); }
        .sr-note { font-size: .82rem; color: var(--gray-500); white-space: pre-line; }
        .sr-tag { font-size: .75rem; padding: .15rem .55rem; border-radius: 999px; background: var(--gray-100); color: var(--gray-700); }
        .sr-tag[data-status="new"] { background: var(--warning-100); color: var(--warning-800); }
        .sr-tag[data-status="created"] { background: var(--success-100); color: var(--success-800); }
        .sr-actions { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
        .sr-empty { padding: 2rem; text-align: center; color: var(--gray-500); }
    </style>
    <nav class="sr-filters" aria-label="{{ __($l.'filter') }}">
        @foreach (['open', 'new', 'contacted', 'created', 'declined', 'all'] as $s)
            <a href="?status={{ $s }}" wire:navigate @if ($status === $s) aria-current="true" @endif>{{ __($l.'filters.'.$s) }}</a>
        @endforeach
    </nav>
    <div class="sr-list lms-stagger">
        @forelse ($requests as $r)
            <article class="pc-base sr-card" wire:key="sr-{{ $r['id'] }}">
                <div class="sr-head">
                    <h3><bdi>{{ $r['academy'] }}</bdi> <span class="sr-tag" data-status="{{ $r['status'] }}">{{ __($l.'statuses.'.$r['status']) }}</span></h3>
                    <time title="{{ $r['when'] }}">{{ $r['ago'] }}</time>
                </div>
                <div class="sr-facts">
                    <span><bdi>{{ $r['contact'] }}</bdi></span>
                    <a href="mailto:{{ $r['email'] }}" dir="ltr">{{ $r['email'] }}</a>
                    @if ($r['phone'])<a href="{{ $r['whatsapp'] }}" target="_blank" rel="noopener" dir="ltr">{{ $r['phone'] }}</a>@endif
                    @if ($r['country'])<span>{{ __($l.'country') }}: {{ $r['country'] }}</span>@endif
                    @if ($r['students'])<span>{{ __($l.'students') }}: <bdi dir="ltr">{{ $r['students'] }}</bdi></span>@endif
                    @if ($r['plan'])<span>{{ __($l.'plan') }}: <bdi>{{ $r['plan'] }}</bdi></span>@endif
                    @if ($r['utm'])<span dir="ltr">utm: {{ $r['utm'] }}</span>@endif
                </div>
                @if ($r['message'])<p class="sr-message">{{ $r['message'] }}</p>@endif
                @if ($r['note'])<p class="sr-note">{{ __($l.'note') }}: {{ $r['note'] }}</p>@endif
                @if (in_array($r['status'], ['new', 'contacted'], true))
                    <div class="sr-actions">
                        @if ($r['make'])
                            <x-filament::button tag="a" size="sm" icon="heroicon-m-plus" :href="$r['make']">{{ __($l.'make') }}</x-filament::button>
                        @endif
                        @if ($r['status'] === 'new'){{ ($this->contactedAction)(['request' => $r['id']]) }}@endif
                        {{ ($this->declineAction)(['request' => $r['id']]) }}
                    </div>
                @endif
            </article>
        @empty
            <div class="pc-base sr-empty">{{ __($l.'empty') }}</div>
        @endforelse
    </div>
    <x-filament-actions::modals />
</x-filament-panels::page>
