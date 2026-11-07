@php $l = 'platform::topups.console.'; $requests = $this->requests; @endphp
<x-filament-panels::page>
    @include('platform::console.styles')
    <style>
        .pr-filters { display: flex; flex-wrap: wrap; gap: .4rem; }
        .pr-filters a { padding: .35rem .8rem; border-radius: 999px; font-size: .85rem; border: 1px solid var(--gray-200); color: var(--gray-700); }
        .pr-filters a[aria-current] { background: var(--primary-600); border-color: var(--primary-600); color: white; }
        .dark .pr-filters a { border-color: var(--gray-700); color: var(--gray-300); }
        .pr-list { display: flex; flex-direction: column; gap: .75rem; }
        .pr-card { padding: 1rem 1.15rem; display: flex; flex-direction: column; gap: .55rem; }
        .pr-head { display: flex; flex-wrap: wrap; justify-content: space-between; gap: .5rem; align-items: baseline; }
        .pr-head h3 { font-weight: 700; font-size: 1.02rem; display: flex; flex-wrap: wrap; gap: .4rem; align-items: center; }
        .pr-head h3 a { color: var(--primary-600); }
        .pr-head time { font-size: .8rem; color: var(--gray-500); }
        .pr-what { font-size: .95rem; font-weight: 600; }
        .pr-facts { font-size: .84rem; color: var(--gray-600); }
        .dark .pr-facts { color: var(--gray-300); }
        .pr-note { white-space: pre-line; font-size: .88rem; padding: .55rem .8rem; border-radius: .6rem; background: var(--gray-50); }
        .dark .pr-note { background: var(--gray-900); }
        .pr-tag { font-size: .75rem; padding: .15rem .55rem; border-radius: 999px; background: var(--gray-100); color: var(--gray-700); font-weight: 600; }
        .pr-tag[data-status="requested"] { background: var(--warning-100); color: var(--warning-800); }
        .pr-tag[data-status="active"], .pr-tag[data-status="handled"] { background: var(--success-100); color: var(--success-800); }
        .pr-tag[data-kind] { background: var(--primary-50); color: var(--primary-700); }
        .pr-actions { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
        .pr-empty { padding: 2rem; text-align: center; color: var(--gray-500); }
    </style>
    <nav class="pr-filters" aria-label="{{ __($l.'filter') }}">
        @foreach (['waiting', 'all'] as $s)
            <a href="?status={{ $s }}" wire:navigate @if ($status === $s) aria-current="true" @endif>{{ __($l.'filters.'.$s) }}</a>
        @endforeach
    </nav>
    <div class="pr-list lms-stagger">
        @forelse ($requests as $r)
            <article class="pc-base pr-card lms-lift" wire:key="pr-{{ $r['kind'] }}-{{ $r['id'] }}">
                <div class="pr-head">
                    <h3>
                        <a href="{{ $r['academy_url'] }}" wire:navigate><bdi>{{ $r['academy'] }}</bdi></a>
                        <span class="pr-tag" data-kind>{{ __($l.'kinds.'.$r['kind']) }}</span>
                        <span class="pr-tag" data-status="{{ $r['status'] }}">{{ __('platform::topups.status.'.$r['status']) }}</span>
                    </h3>
                    <time title="{{ $r['when'] }}">{{ $r['ago'] }}</time>
                </div>
                <p class="pr-what">{{ $r['what'] }}</p>
                @if ($r['by'])<p class="pr-facts">{{ __($l.'by', ['name' => $r['by']]) }}</p>@endif
                @if ($r['note'])<p class="pr-note">{{ $r['note'] }}</p>@endif
                @if ($r['answer'])<p class="pr-facts">{{ $r['answer'] }}</p>@endif
                @if ($r['status'] === 'requested')
                    <div class="pr-actions">
                        @if ($r['kind'] === 'topup')
                            {{ ($this->activateAction)(['topup' => $r['id']]) }}
                            {{ ($this->declineAction)(['topup' => $r['id']]) }}
                        @else
                            <x-filament::button tag="a" size="sm" icon="heroicon-m-rectangle-stack" :href="$r['academy_url']">{{ __($l.'change_plan') }}</x-filament::button>
                            {{ ($this->handledAction)(['request' => $r['id']]) }}
                        @endif
                    </div>
                @endif
            </article>
        @empty
            <div class="pc-base pr-empty">{{ __($l.'empty') }}</div>
        @endforelse
    </div>
</x-filament-panels::page>
