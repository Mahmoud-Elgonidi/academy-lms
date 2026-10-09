@php $l = 'platform::health.'; $overall = $this->overall(); @endphp
<x-filament-panels::page>
    @include('platform::console.styles')
    <style>
        .ph-head { display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; justify-content: space-between; padding: .9rem 1rem; }
        .ph-head strong { font-size: 1.05rem; display: flex; align-items: center; gap: .55rem; }
        .ph-head small { color: var(--gray-500); font-size: .8rem; }
        .ph-dot { inline-size: .7rem; block-size: .7rem; border-radius: 999px; background: var(--success-500); flex-shrink: 0; }
        [data-status="warn"] > .ph-dot, .ph-dot[data-status="warn"] { background: var(--warning-500); }
        [data-status="fail"] > .ph-dot, .ph-dot[data-status="fail"] { background: var(--danger-500); }
        .ph-dot[data-status="off"] { background: var(--gray-300); }
        .ph-checks { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 17rem), 1fr)); gap: .6rem; }
        .ph-check { padding: .8rem .95rem; display: flex; flex-direction: column; gap: .3rem; }
        .ph-check header { display: flex; justify-content: space-between; align-items: center; gap: .5rem; }
        .ph-check h3 { font-weight: 600; font-size: .9rem; display: flex; align-items: center; gap: .45rem; }
        .ph-check p { font-size: .84rem; color: var(--gray-700); overflow-wrap: anywhere; }
        .ph-check em { font-style: normal; font-size: .78rem; color: var(--gray-500); overflow-wrap: anywhere; direction: ltr; unicode-bidi: plaintext; }
        .ph-check[data-status="fail"] { border-color: var(--danger-300); background: var(--danger-50); }
        .ph-check[data-status="warn"] { border-color: var(--warning-300); }
        .ph-chip { font-size: .7rem; font-weight: 700; padding: .1rem .55rem; border-radius: 999px; background: var(--success-50); color: var(--success-700); flex-shrink: 0; }
        .ph-chip[data-status="warn"] { background: var(--warning-50); color: var(--warning-700); }
        .ph-chip[data-status="fail"] { background: var(--danger-100); color: var(--danger-700); }
        .ph-chip[data-status="off"] { background: var(--gray-100); color: var(--gray-600); }
        .ph-row { display: flex; justify-content: space-between; gap: .75rem; padding: .55rem 0; border-top: 1px solid var(--gray-100); align-items: flex-start; font-size: .85rem; }
        .ph-row:first-of-type { border-top: 0; }
        .ph-row > div { display: flex; flex-direction: column; gap: .15rem; min-width: 0; }
        .ph-row small { color: var(--gray-500); font-size: .78rem; overflow-wrap: anywhere; }
        .ph-row code { font-size: .75rem; direction: ltr; unicode-bidi: isolate; overflow-wrap: anywhere; color: var(--gray-600); }
        .ph-row .ph-side { align-items: flex-end; text-align: end; flex-shrink: 0; }
        .ph-how { font-size: .8rem; color: var(--gray-500); direction: auto; }
        .dark .ph-check p { color: var(--gray-300); }
        .dark .ph-check[data-status="fail"] { background: color-mix(in srgb, var(--danger-500) 12%, transparent); }
        .dark .ph-row { border-color: var(--gray-800); }
    </style>

    <div class="pc-stack" x-data="{ timer: null, init() { this.timer = setInterval(() => { if (! document.hidden) $wire.check() }, {{ \Modules\Platform\Filament\Console\Pages\Health::REFRESH_SECONDS * 1000 }}) }, destroy() { clearInterval(this.timer) } }">
        <section class="pc-base ph-head" data-status="{{ $overall }}" role="status">
            <strong data-status="{{ $overall }}"><span class="ph-dot" data-status="{{ $overall }}"></span>{{ __($l.'overall.'.$overall) }}</strong>
            <span style="display:flex;gap:.6rem;align-items:center">
                <small>{{ __($l.'checked', ['time' => $this->checkedAt()]) }}</small>
                <x-filament::button color="gray" size="sm" icon="heroicon-m-arrow-path" wire:click="check">{{ __($l.'refresh') }}</x-filament::button>
            </span>
        </section>

        <div class="ph-checks lms-stagger">
            @foreach ($this->checks as $name => $check)
                <article class="pc-base ph-check" data-status="{{ $check['status'] }}" wire:key="check-{{ $name }}">
                    <header>
                        <h3><span class="ph-dot" data-status="{{ $check['status'] }}"></span>{{ __($l.'checks.'.$name) }}</h3>
                        <span class="ph-chip" data-status="{{ $check['status'] }}">{{ __($l.'status.'.$check['status']) }}</span>
                    </header>
                    <p>{{ $check['value'] }}</p>
                    @if ($check['hint'])<em>{{ $check['hint'] }}</em>@endif
                </article>
            @endforeach
        </div>

        <div class="pc-two">
            <section class="pc-base pc-box">
                <h3>{{ __($l.'errors.title') }}</h3>
                @forelse ($this->errors as $e)
                    <div class="ph-row" wire:key="err-{{ $e['id'] }}">
                        <div>
                            <strong>{{ $e['short'] }}</strong>
                            <small>{{ $e['message'] }}</small>
                            <code>{{ $e['place'] }} · {{ $e['where'] === 'console' ? __($l.'errors.console') : $e['where'] }}</code>
                            @if ($e['academy_name'])<small>{{ $e['academy_name'] }}</small>@endif
                        </div>
                        <div class="ph-side">
                            <strong>{{ __($l.'errors.times', ['count' => $e['count']]) }}</strong>
                            <small>{{ __($l.'errors.last', ['when' => $e['last_human']]) }}</small>
                        </div>
                    </div>
                @empty
                    <p class="pc-empty">{{ __($l.'errors.empty') }}</p>
                @endforelse
            </section>

            <section class="pc-base pc-box">
                <h3>{{ __($l.'backups.title') }}</h3>
                @forelse ($this->backups as $i => $b)
                    <div class="ph-row" wire:key="bk-{{ $i }}-{{ $b['name'] }}">
                        <div>
                            <strong>{{ __($l.'backups.kinds.'.$b['kind']) }}</strong>
                            <small>{{ $b['when'] }}@if ($b['size']) · {{ $b['size'] }}@endif · {{ $b['took'] }}s</small>
                            @if ($b['detail'])<small>{{ \Illuminate\Support\Str::limit($b['detail'], 220) }}</small>@endif
                        </div>
                        <div class="ph-side"><span class="ph-chip" data-status="{{ $b['status'] === 'ok' ? 'ok' : 'fail' }}">{{ __($l.'status.'.($b['status'] === 'ok' ? 'ok' : 'fail')) }}</span></div>
                    </div>
                @empty
                    <p class="pc-empty">{{ __($l.'backups.empty') }}</p>
                @endforelse
                <p class="ph-how">{{ __($l.'backups.how') }}</p>
            </section>
        </div>
    </div>
</x-filament-panels::page>
