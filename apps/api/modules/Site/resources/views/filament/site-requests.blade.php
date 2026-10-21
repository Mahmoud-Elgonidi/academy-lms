@php $l = 'site::site.requests.'; $requests = $this->requests; $kinds = $this->kinds; @endphp
<x-filament-panels::page>
    <style>
        .sq-filters { display: flex; flex-wrap: wrap; gap: .4rem; }
        .sq-filters a { padding: .35rem .8rem; border-radius: 999px; font-size: .85rem; border: 1px solid var(--gray-200); color: var(--gray-700); }
        .sq-filters a[aria-current] { background: var(--primary-600); border-color: var(--primary-600); color: white; }
        .dark .sq-filters a { border-color: var(--gray-700); color: var(--gray-300); }
        .sq-list { display: flex; flex-direction: column; gap: .75rem; }
        .sq-card { padding: 1rem 1.15rem; display: flex; flex-direction: column; gap: .6rem; border: 1px solid var(--gray-200); border-radius: var(--radius-xl, .75rem); background: white; }
        .dark .sq-card { background: var(--gray-900); border-color: var(--gray-800); }
        .sq-head { display: flex; flex-wrap: wrap; justify-content: space-between; gap: .5rem; align-items: baseline; }
        .sq-head h3 { font-weight: 700; font-size: 1.05rem; }
        .sq-head time { font-size: .8rem; color: var(--gray-500); }
        .sq-facts { display: flex; flex-wrap: wrap; gap: .4rem 1.1rem; font-size: .88rem; color: var(--gray-700); }
        .dark .sq-facts { color: var(--gray-300); }
        .sq-facts a { color: var(--primary-600); text-decoration: underline; }
        .sq-message { white-space: pre-line; font-size: .9rem; padding: .6rem .8rem; border-radius: .6rem; background: var(--gray-50); overflow-wrap: anywhere; }
        .dark .sq-message { background: var(--gray-950); }
        .sq-note { font-size: .82rem; color: var(--gray-500); white-space: pre-line; }
        .sq-tag { font-size: .75rem; padding: .15rem .55rem; border-radius: 999px; background: var(--gray-100); color: var(--gray-700); }
        .sq-tag[data-status="new"] { background: var(--warning-100); color: var(--warning-800); }
        .sq-tag[data-status="accepted"] { background: var(--success-100); color: var(--success-800); }
        .sq-tag[data-kind] { background: var(--primary-50); color: var(--primary-700); }
        .sq-actions { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
        .sq-empty { padding: 2rem; text-align: center; color: var(--gray-500); border: 1px dashed var(--gray-300); border-radius: var(--radius-xl, .75rem); }
    </style>
    <nav class="sq-filters" aria-label="{{ __($l.'filter') }}">
        @foreach (['open', 'new', 'contacted', 'accepted', 'declined', 'all'] as $s)
            <a href="?status={{ $s }}&amp;kind={{ $kind }}" wire:navigate @if ($status === $s) aria-current="true" @endif>{{ __($l.'filters.'.$s) }}</a>
        @endforeach
    </nav>
    @if (count($kinds) > 1)
        <nav class="sq-filters" aria-label="{{ __($l.'filter') }}">
            @foreach (['all', ...$kinds] as $k)
                <a href="?status={{ $status }}&amp;kind={{ $k }}" wire:navigate @if ($kind === $k) aria-current="true" @endif>{{ __($l.'kinds.'.$k) }}</a>
            @endforeach
        </nav>
    @endif
    <div class="sq-list lms-stagger">
        @forelse ($requests as $r)
            <article class="sq-card" wire:key="sq-{{ $r['id'] }}">
                <div class="sq-head">
                    <h3>
                        <bdi>{{ $r['name'] }}</bdi>
                        <span class="sq-tag" data-kind="{{ $r['kind'] }}">{{ __('site::site.kinds.'.$r['kind']) }}</span>
                        <span class="sq-tag" data-status="{{ $r['status'] }}">{{ __($l.'statuses.'.$r['status']) }}</span>
                    </h3>
                    <time title="{{ $r['when'] }}">{{ $r['ago'] }}</time>
                </div>
                <div class="sq-facts">
                    <a href="{{ $r['whatsapp'] }}" target="_blank" rel="noopener" dir="ltr" title="{{ __($l.'whatsapp') }}">{{ $r['phone'] }}</a>
                    @if ($r['email'])<a href="mailto:{{ $r['email'] }}" dir="ltr">{{ $r['email'] }}</a>@endif
                    @if ($r['country'])<span>{{ __($l.'country') }}: {{ $r['country'] }}</span>@endif
                    @if ($r['subject'])<span>{{ __($l.'subject') }}: <bdi>{{ $r['subject'] }}</bdi></span>@endif
                    @if ($r['child'])<span>{{ __($l.'for_child', ['name' => $r['child']]) }}@if ($r['born']) · {{ __($l.'born', ['year' => $r['born']]) }}@endif</span>@endif
                    @if ($r['subjects'] !== [])<span>{{ __($l.'subjects') }}: <bdi>{{ implode('، ', $r['subjects']) }}</bdi></span>@endif
                    @if ($r['years'] !== null)<span>{{ __($l.'years', ['years' => $r['years']]) }}</span>@endif
                    @if ($r['utm'])<span dir="ltr">utm: {{ $r['utm'] }}</span>@endif
                </div>
                @if ($r['message'])<p class="sq-message">{{ $r['message'] }}</p>@endif
                @if ($r['note'])<p class="sq-note">{{ __($l.'note') }}: {{ $r['note'] }}</p>@endif
                <div class="sq-actions">
                    @if ($r['lead'])
                        <x-filament::button tag="a" size="sm" color="gray" icon="heroicon-m-arrow-top-right-on-square" :href="$r['lead']">{{ __($l.'open_lead') }}</x-filament::button>
                    @endif
                    @if (in_array($r['status'], ['new', 'contacted'], true))
                        @if ($r['status'] === 'new'){{ ($this->contactedAction)(['request' => $r['id']]) }}@endif
                        {{ ($this->acceptAction)(['request' => $r['id']]) }}
                        {{ ($this->declineAction)(['request' => $r['id']]) }}
                    @endif
                </div>
            </article>
        @empty
            <div class="sq-empty">{{ __($l.'empty') }}</div>
        @endforelse
    </div>
    <x-filament-actions::modals />
</x-filament-panels::page>
