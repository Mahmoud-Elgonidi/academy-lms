@php
    $l = 'identity::impersonation.console.';
    $academies = $this->academies; $chosen = $this->chosen; $recent = $this->recent;
@endphp
<x-filament-panels::page>
    <style>
        .ss-grid { display: grid; grid-template-columns: minmax(0, 1fr); gap: 1rem; align-items: start; }
        @media (min-width: 1100px) { .ss-grid { grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr); } }
        .ss-box { background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl, .75rem); padding: 1rem; display: flex; flex-direction: column; gap: .85rem; }
        .ss-box h3 { font-weight: 700; font-size: .95rem; }
        .ss-label { display: flex; flex-direction: column; gap: .35rem; font-size: .82rem; font-weight: 600; color: var(--gray-600); }
        .ss-hint { font-size: .78rem; color: var(--gray-500); font-weight: 400; }
        .ss-error { font-size: .8rem; color: var(--danger-600); font-weight: 500; }
        .ss-people { display: flex; flex-direction: column; border: 1px solid var(--gray-100); border-radius: .7rem; overflow: hidden; }
        .ss-person { display: flex; justify-content: space-between; align-items: center; gap: .75rem; padding: .6rem .75rem; border-top: 1px solid var(--gray-100); background: none; text-align: start; width: 100%; font: inherit; color: inherit; cursor: pointer; }
        .ss-person:first-child { border-top: 0; }
        .ss-person:hover { background: var(--gray-50); }
        .ss-person div { display: flex; flex-direction: column; align-items: flex-start; gap: .1rem; min-width: 0; }
        .ss-person strong { font-size: .9rem; font-weight: 600; }
        .ss-person small { color: var(--gray-500); font-size: .78rem; direction: ltr; unicode-bidi: isolate; overflow-wrap: anywhere; max-width: 100%; }
        .ss-roles { display: flex; flex-wrap: wrap; gap: .25rem; justify-content: flex-end; flex-shrink: 0; }
        .ss-roles span { font-size: .7rem; padding: .05rem .45rem; border-radius: 999px; background: var(--gray-100); color: var(--gray-700); }
        .ss-chosen { display: flex; justify-content: space-between; align-items: center; gap: .75rem; padding: .7rem .85rem; border-radius: .8rem; background: var(--primary-50); border: 1px solid var(--primary-200); }
        .ss-chosen div { display: flex; flex-direction: column; gap: .1rem; min-width: 0; }
        .ss-chosen small { color: var(--primary-700); font-size: .76rem; font-weight: 600; }
        .ss-chosen strong { font-size: .95rem; }
        .ss-chips { display: flex; flex-wrap: wrap; gap: .35rem; }
        .ss-chips button { font-size: .82rem; font-weight: 600; padding: .4rem .85rem; border-radius: 999px; border: 1px solid var(--gray-200); background: white; color: var(--gray-700); }
        .ss-chips button[aria-pressed="true"] { background: var(--primary-600); border-color: var(--primary-600); color: white; }
        .ss-ready { display: flex; flex-direction: column; gap: .6rem; padding: 1rem; border-radius: var(--radius-xl, .75rem); background: var(--warning-50); border: 1px solid var(--warning-200); color: var(--warning-900, var(--warning-800)); }
        .ss-ready strong { font-size: 1rem; }
        .ss-ready p { margin: 0; font-size: .86rem; }
        .ss-ready time { font-variant-numeric: tabular-nums; font-weight: 700; }
        .ss-recent { display: flex; flex-direction: column; }
        .ss-row { display: flex; flex-direction: column; gap: .2rem; padding: .65rem 0; border-top: 1px solid var(--gray-100); }
        .ss-row:first-child { border-top: 0; }
        .ss-row header { display: flex; justify-content: space-between; gap: .5rem; align-items: center; }
        .ss-row strong { font-size: .9rem; font-weight: 600; }
        .ss-row small { color: var(--gray-500); font-size: .78rem; }
        .ss-row p { margin: 0; font-size: .84rem; color: var(--gray-700); overflow-wrap: anywhere; }
        .ss-state { font-size: .7rem; font-weight: 700; padding: .1rem .55rem; border-radius: 999px; background: var(--gray-100); color: var(--gray-700); white-space: nowrap; }
        .ss-state[data-state="live"] { background: var(--warning-100); color: var(--warning-800); }
        .ss-state[data-state="waiting"] { background: var(--primary-50); color: var(--primary-700); }
        .ss-empty { color: var(--gray-500); font-size: .88rem; }
        .dark .ss-box, .dark .ss-chips button { background: var(--gray-900); border-color: var(--gray-800); }
        .dark .ss-chips button { color: var(--gray-300); }
        .dark .ss-chips button[aria-pressed="true"] { background: var(--primary-600); border-color: var(--primary-600); color: white; }
        .dark .ss-people, .dark .ss-person, .dark .ss-row { border-color: var(--gray-800); }
        .dark .ss-person:hover { background: rgb(255 255 255 / .03); }
        .dark .ss-roles span, .dark .ss-state { background: rgb(255 255 255 / .07); color: var(--gray-300); }
        .dark .ss-chosen { background: color-mix(in oklab, var(--primary-500) 14%, transparent); border-color: color-mix(in oklab, var(--primary-500) 30%, transparent); }
        .dark .ss-chosen small { color: var(--primary-300); }
        .dark .ss-ready { background: color-mix(in oklab, var(--warning-500) 12%, transparent); border-color: color-mix(in oklab, var(--warning-500) 30%, transparent); color: var(--warning-200); }
        .dark .ss-state[data-state="live"] { background: color-mix(in oklab, var(--warning-500) 18%, transparent); color: var(--warning-300); }
        .dark .ss-row p { color: var(--gray-300); }
    </style>

    <div class="ss-grid">
        <section class="ss-box">
            <label class="ss-label">
                {{ __($l.'academy') }}
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="academy">
                        <option value="">{{ __($l.'pick_academy') }}</option>
                        @foreach ($academies as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>

            @if ($academy !== '' && $chosen === null)
                <label class="ss-label">
                    {{ __($l.'people') }}
                    <x-filament::input.wrapper prefix-icon="heroicon-o-magnifying-glass">
                        <x-filament::input type="search" wire:model.live.debounce.300ms="search" :placeholder="__($l.'search')" />
                    </x-filament::input.wrapper>
                </label>
                @php $people = $this->people; @endphp
                @if ($people === [])
                    <p class="ss-empty">{{ __($l.'no_people') }}</p>
                @else
                    <div class="ss-people">
                        @foreach ($people as $p)
                            <button type="button" class="ss-person" wire:click="choose('{{ $p['id'] }}')" wire:key="p-{{ $p['id'] }}">
                                <div><strong>{{ $p['name'] }}</strong>@if ($p['contact'])<small>{{ $p['contact'] }}</small>@endif</div>
                                <span class="ss-roles">@foreach ($p['roles'] as $r)<span>{{ __('identity::users.roles.'.$r) }}</span>@endforeach</span>
                            </button>
                        @endforeach
                    </div>
                @endif
            @endif

            @if ($chosen !== null)
                <div class="ss-chosen">
                    <div>
                        <small>{{ __($l.'chosen') }}</small>
                        <strong>{{ $chosen['name'] }}</strong>
                        <span class="ss-roles" style="justify-content: flex-start">@foreach ($chosen['roles'] as $r)<span>{{ __('identity::users.roles.'.$r) }}</span>@endforeach</span>
                    </div>
                    <x-filament::button size="sm" color="gray" wire:click="clearPerson" wire:key="change">{{ __($l.'change') }}</x-filament::button>
                </div>

                @if ($ready)
                    <div class="ss-ready" wire:key="ready-{{ $ready['until'] }}"
                         x-data="{ left: Math.max(0, Math.round(({{ $ready['until'] }} * 1000 - Date.now()) / 1000)) }"
                         x-init="const t = setInterval(() => { left = Math.max(0, left - 1); if (! left) clearInterval(t) }, 1000)">
                        <strong>{{ __($l.'ready.title') }}</strong>
                        <p>{{ __($l.'ready.body', ['name' => $ready['name']]) }}</p>
                        <div x-show="left > 0">
                            <x-filament::button tag="a" :href="$ready['url']" target="_blank" rel="noopener" icon="heroicon-o-arrow-top-right-on-square" color="warning">
                                {{ __($l.'ready.open') }} · <time x-text="Math.floor(left / 60) + ':' + String(left % 60).padStart(2, '0')"></time>
                            </x-filament::button>
                        </div>
                        <p x-show="left === 0" x-cloak>{{ __($l.'ready.gone') }}</p>
                    </div>
                @else
                    <label class="ss-label">
                        {{ __($l.'reason') }}
                        <x-filament::input.wrapper :valid="! $errors->has('reason')">
                            <textarea wire:model="reason" rows="3" maxlength="500" class="fi-input block w-full border-none bg-transparent px-3 py-1.5 text-sm" placeholder="{{ __($l.'reason_hint') }}"></textarea>
                        </x-filament::input.wrapper>
                        @error('reason')<span class="ss-error">{{ $message }}</span>@enderror
                    </label>
                    <div class="ss-label">
                        {{ __($l.'minutes') }}
                        <div class="ss-chips" role="group">
                            @foreach (\Modules\Identity\Actions\Impersonation\StartImpersonation::MINUTES as $m)
                                <button type="button" wire:click="$set('minutes', {{ $m }})" wire:key="m-{{ $m }}" aria-pressed="{{ $minutes === $m ? 'true' : 'false' }}">{{ __($l.'minutes_option', ['count' => $m]) }}</button>
                            @endforeach
                        </div>
                        @error('minutes')<span class="ss-error">{{ $message }}</span>@enderror
                    </div>
                    @error('user')<span class="ss-error">{{ $message }}</span>@enderror
                    @error('academy')<span class="ss-error">{{ $message }}</span>@enderror
                    <div><x-filament::button wire:click="start" icon="heroicon-o-key" wire:key="start">{{ __($l.'start') }}</x-filament::button></div>
                @endif
            @endif
        </section>

        @if ($academy !== '')
            <section class="ss-box">
                <h3>{{ __($l.'recent') }}</h3>
                <div class="ss-recent lms-stagger">
                    @forelse ($recent as $r)
                        <div class="ss-row" wire:key="r-{{ $r['id'] }}">
                            <header>
                                <strong>{{ $r['user'] }}</strong>
                                <span class="ss-state" data-state="{{ $r['state'] }}">{{ $r['state'] === 'ended' && $r['how'] ? __($l.'ended_how.'.$r['how']) : __($l.'states.'.$r['state']) }}</span>
                            </header>
                            <small>{{ __($l.'by', ['admin' => $r['admin'], 'minutes' => $r['minutes']]) }} · {{ $r['when'] }}</small>
                            <p>{{ $r['reason'] }}</p>
                            @if (in_array($r['state'], ['live', 'waiting'], true))
                                <div><x-filament::button size="xs" color="danger" wire:click="end('{{ $r['id'] }}')" wire:key="end-{{ $r['id'] }}">{{ __($l.'end') }}</x-filament::button></div>
                            @endif
                        </div>
                    @empty
                        <p class="ss-empty">{{ __($l.'no_recent') }}</p>
                    @endforelse
                </div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
