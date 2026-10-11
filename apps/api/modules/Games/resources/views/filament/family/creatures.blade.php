@php
    /*
     * The album (the approved design, Album): all the world's creatures, hatched ones in colour
     * (their name or the one the child gave, where they live), the rest as a shadow with "?";
     * tabs by rarity and chips by island; a creature opens in a sheet with its voice and story,
     * to make it the buddy or name it. A guardian sees the same without changing anything.
     */
    $l = 'games::games.';
    $a = $l.'album.';
    $all = $this->creatures;
    $found = collect($all)->where('found', true)->count();
    $total = count($all);
    $islands = collect($all)->unique('island')->map(fn ($c) => ['key' => $c['island'], 'name' => $c['island_name']])->values();
    $hex = fn ($c, $d) => is_string($c) && preg_match('/^#[0-9a-f]{6}$/i', $c) ? $c : $d;
    $ring = ['common' => '#24124F', 'rare' => '#1C7ED6', 'legendary' => '#F08C00'];
    $rarityBg = ['common' => '#F1F3F5', 'rare' => '#D0EBFF', 'legendary' => '#FFE8A3'];
    $need = \Modules\Games\Domain\Round::STARS_PER_EGG;
    $suggest = fn (array $c) => \Modules\Games\Support\HatchNames::of($c['key'], $c['name']);
    $guardian = $this->isGuardian();
    $learner = app(\Modules\People\Contracts\FamilyView::class)->current((string) auth()->id());
    $config = [
        'open' => $this->new,
        'island' => $this->island,
        'pitches' => collect($all)->mapWithKeys(fn ($c) => [$c['key'] => $c['look']['pitch'] ?? 1])->all(),
        'found' => collect($all)->mapWithKeys(fn ($c) => [$c['key'] => $c['found']])->all(),
        'buddyDone' => __($a.'buddy_done', ['name' => '__NAME__']),
    ];
@endphp
<x-filament-panels::page>
    @include('shared::games.art-styles')
    @include('games::islands.styles')
    @include('shared::games.sound')
    <style>
        .gw-album { --gw-ink: #24124F; --gw-sun: #FFD43B; position: relative; max-width: 560px; margin-inline: auto; padding: 0 16px 24px; border-radius: 28px; background: #FFF4D6; color: #24124F; font-family: var(--lms-font-heading, system-ui, sans-serif); }
        @media (max-width: 639.98px) { .gw-album { margin-inline: calc(50% - 50vw); margin-top: -1rem; width: 100vw; border-radius: 0; min-height: calc(100dvh - 4rem); box-sizing: border-box; } }
        .gw-album button { font: inherit; color: inherit; }
        .gw-album [x-cloak] { display: none !important; }
        .gw-album-head { height: 68px; display: flex; align-items: center; justify-content: space-between; gap: 10px; }
        .gw-album-head > div { display: flex; align-items: center; gap: 10px; min-width: 0; }
        .gw-album h1 { margin: 0; font-size: 24px; font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .gw-circle { flex: none; width: 44px; height: 44px; border-radius: 999px; background: #FFFFFF; border: 3px solid #24124F; box-shadow: 0 3px 0 #24124F; display: flex; align-items: center; justify-content: center; box-sizing: border-box; }
        .gw-count { flex: none; height: 36px; padding: 0 12px; display: flex; align-items: center; border-radius: 999px; background: #6236E0; color: #FFFFFF; font-size: 16px; font-weight: 800; }
        .gw-tabs { margin-top: 10px; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 6px; padding: 4px; border-radius: 999px; background: #FFFFFF; border: 2px solid #24124F; }
        .gw-tabs button { height: 38px; border-radius: 999px; font-size: 15px; font-weight: 800; cursor: pointer; border: 0; background: transparent; }
        .gw-tabs button[aria-selected="true"] { background: #6236E0; color: #FFFFFF; }
        .gw-chips { margin-top: 8px; display: flex; gap: 6px; overflow-x: auto; padding-bottom: 4px; scrollbar-width: none; }
        .gw-chips button { flex: none; height: 32px; padding: 0 12px; border-radius: 999px; border: 2px solid #24124F; background: #FFFFFF; font-size: 13.5px; font-weight: 800; cursor: pointer; }
        .gw-chips button[aria-pressed="true"] { background: #FFD43B; }
        .gw-grid { margin-top: 12px; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
        @media (min-width: 640px) { .gw-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
        .gw-tile { position: relative; width: 100%; height: 134px; padding: 6px 4px; border-radius: 20px; display: flex; flex-direction: column; align-items: center; justify-content: space-between; box-sizing: border-box; cursor: pointer; transition: transform .1s; text-align: center; }
        .gw-tile:active { transform: scale(.96); }
        .gw-tile[data-found="no"] { background: #F8F5FF; border: 2px dashed #B197FC; }
        .gw-tile strong { font-size: 14.5px; font-weight: 800; line-height: 1.1; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .gw-tile small { font-size: 11.5px; font-weight: 700; line-height: 1.15; color: #5C4A99; }
        .gw-tile .gw-q { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 30px; font-weight: 800; color: #6236E0; }
        .gw-buddy-mark { position: absolute; top: 4px; inset-inline-end: 6px; font-size: 14px; }
        .gw-album-sheet-bg { position: fixed; inset: 0; z-index: 45; background: rgba(36, 18, 79, .5); }
        .gw-album-sheet { position: fixed; z-index: 46; left: 0; right: 0; bottom: 0; max-width: 560px; margin-inline: auto; padding: 14px 18px calc(24px + env(safe-area-inset-bottom)); border-radius: 30px 30px 0 0; background: #FFFFFF; border-top: 4px solid #24124F; box-sizing: border-box; color: #24124F; font-family: var(--lms-font-heading, system-ui, sans-serif); max-height: 92dvh; overflow-y: auto; }
        .gw-album-sheet h2 { margin: 0; font-size: 26px; font-weight: 800; }
        .gw-album-sheet p { margin: 4px 0 12px; font-size: 16px; font-weight: 600; line-height: 1.45; }
        .gw-album-toast { position: fixed; left: 24px; right: 24px; bottom: calc(96px + env(safe-area-inset-bottom)); z-index: 47; max-width: 480px; margin-inline: auto; padding: 10px 14px; border-radius: 18px; background: #24124F; color: #FFFFFF; font-size: 15px; font-weight: 700; text-align: center; }
        @media (prefers-reduced-motion: no-preference) {
            .gw-album-sheet { animation: gw-sheetin .35s cubic-bezier(.2, .9, .3, 1.05) backwards; }
            .gw-album-sheet-bg { animation: gw-fade .25s ease-out backwards; }
            .gw-shine { animation: gw-twinkle 1.8s ease-in-out infinite; }
            .gw-sing { animation: gw-popin .5s cubic-bezier(.3, 1.6, .55, 1); }
            .gw-grid > * { animation: gw-popin .35s cubic-bezier(.3, 1.6, .55, 1) calc(var(--i, 0) * 30ms) backwards; }
        }
        @keyframes gw-fade { from { opacity: 0; } }
    </style>

    <div class="gw-album" x-data="{
            tab: 'all', island: @js($config['island']), open: null, toast: '', singing: false, tt: null,
            found: @js($config['found']), pitches: @js($config['pitches']),
            init() { if (@js($config['open'])) this.$nextTick(() => this.show(@js($config['open']), true)); },
            shows(rarity, island) { return (this.tab === 'all' || this.tab === rarity) && (! this.island || this.island === island); },
            show(key, quiet) {
                this.open = key;
                if (this.found[key]) setTimeout(() => LmsIslandsSound?.voice(this.pitches[key]), quiet ? 400 : 0);
                else if (! quiet) LmsIslandsSound?.play('locked');
                this.$nextTick(() => [...document.querySelectorAll('.gw-album-sheet [data-first]')].find((e) => e.offsetParent)?.focus({ preventScroll: true }));
            },
            sing(key) { LmsIslandsSound?.voice(this.pitches[key]); this.singing = false; setTimeout(() => this.singing = true, 20); setTimeout(() => this.singing = false, 520); },
            say(text) { this.toast = text; clearTimeout(this.tt); this.tt = setTimeout(() => this.toast = '', 2600); },
            async buddy(key, name) { LmsIslandsSound?.play('win'); await $wire.chooseBuddy(key); this.open = null; this.say(@js($config['buddyDone']).replace('__NAME__', name)); },
            async rename(key, name) { LmsIslandsSound?.play('pop'); await $wire.rename(key, name); this.say(@js(__($a.'named', ['name' => '__NAME__'])).replace('__NAME__', name)); },
        }" x-on:keydown.escape.window="open = null">
        <header class="gw-album-head">
            <div>
                <a class="gw-circle gw-3d" href="{{ \Modules\Games\Filament\Family\FamilyGames::getUrl($this->island ? ['island' => $this->island] : []) }}" wire:navigate aria-label="{{ __($a.'back') }}">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#24124F" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="{{ app()->getLocale() === 'ar' ? '' : 'transform: scaleX(-1)' }}"><path d="M9 5l7 7-7 7"/></svg>
                </a>
                <h1>{{ __($a.'title') }}</h1>
            </div>
            <span class="gw-count">{{ __($a.'count', ['found' => $found, 'total' => $total]) }}</span>
        </header>
        <div style="height: 12px; border-radius: 999px; background: #F1E3C0; border: 2px solid #24124F; overflow: hidden" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $total }}" aria-valuenow="{{ $found }}">
            <div class="lms-bar-fill" style="width: {{ $total ? round(100 * $found / $total) : 0 }}%; height: 100%; background: #6236E0"></div>
        </div>
        <div style="margin-top: 4px; font-size: 13px; font-weight: 700; color: #5C4A99">{{ $guardian ? __($a.'guardian', ['name' => explode(' ', (string) $learner?->name)[0]]) : __($a.'hint', ['need' => $need]) }}</div>

        <div class="gw-tabs" role="tablist" aria-label="{{ __($a.'tabs') }}">
            @foreach (['all' => __($a.'all'), 'common' => __('shared::games.rarity.common'), 'rare' => __('shared::games.rarity.rare'), 'legendary' => __('shared::games.rarity.legendary')] as $key => $label)
                <button type="button" role="tab" :aria-selected="(tab === @js($key)).toString()" x-on:click="tab = @js($key); LmsIslandsSound?.play('pop')">{{ $label }}</button>
            @endforeach
        </div>
        <div class="gw-chips" role="group" aria-label="{{ __($a.'islands') }}">
            <button type="button" :aria-pressed="(! island).toString()" x-on:click="island = null">{{ __($a.'all_islands') }}</button>
            @foreach ($islands as $i)
                <button type="button" :aria-pressed="(island === @js($i['key'])).toString()" x-on:click="island = @js($i['key'])">{{ $i['name'] }}</button>
            @endforeach
        </div>

        <div class="gw-grid">
            @foreach ($all as $n => $c)
                @php $r = $ring[$c['rarity']] ?? '#24124F'; @endphp
                <button type="button" class="gw-tile" data-found="{{ $c['found'] ? 'yes' : 'no' }}" x-show="shows(@js($c['rarity']), @js($c['island']))" x-on:click="show(@js($c['key']))" wire:key="t-{{ $c['key'] }}"
                        aria-label="{{ $c['found'] ? __($a.'aria_found', ['name' => $c['nickname'] ?: $c['name'], 'island' => $c['island_name']]) : __($a.'aria_locked', ['island' => $c['island_name']]) }}"
                        @if ($c['found']) style="--i: {{ $n }}; background: {{ $hex($c['look']['belly'] ?? null, '#FFFFFF') }}; border: 3px solid {{ $r }}; box-shadow: 0 4px 0 {{ $r }}" @else style="--i: {{ $n }}" @endif>
                    @if ($c['rarity'] !== 'common')
                        <svg class="gw-shine gw-abs" aria-hidden="true" style="top: 6px; left: 6px" width="16" height="16" viewBox="0 0 14 14"><path d="M7 0 L8.6 5.4 L14 7 L8.6 8.6 L7 14 L5.4 8.6 L0 7 L5.4 5.4 Z" fill="{{ $r }}"/></svg>
                    @endif
                    @if ($c['buddy'])<span class="gw-buddy-mark" aria-hidden="true">💛</span>@endif
                    <span style="position: relative; display: block; width: 84px; height: 74px">
                        @include('shared::games.creature', ['look' => $c['look'], 'size' => 84, 'shadow' => ! $c['found'], 'line' => '#24124F'])
                        @unless ($c['found'])<span class="gw-q">؟</span>@endunless
                    </span>
                    <strong>{{ $c['found'] ? ($c['nickname'] ?: $c['name']) : __($a.'not_yet') }}</strong>
                    <small>{{ $c['island_name'] }}</small>
                </button>
            @endforeach
        </div>

        <template x-if="toast"><div class="gw-album-toast gw-pop-in" role="status" x-text="toast"></div></template>

        @foreach ($all as $c)
            @php
                $r = $c['rarity'];
                $how = __($a.'how_'.$r, ['island' => $c['island_name'], 'need' => $need]);
            @endphp
            <div x-show="open === @js($c['key'])" x-cloak wire:key="s-{{ $c['key'] }}">
                <button type="button" class="gw-album-sheet-bg" x-on:click="open = null" aria-label="{{ __($a.'close') }}"></button>
                <section class="gw-album-sheet" role="dialog" aria-modal="true" aria-labelledby="sheet-{{ $c['key'] }}">
                    <div style="width: 48px; height: 5px; border-radius: 999px; background: #D0C7EE; margin: 0 auto 6px"></div>
                    <div style="height: 170px; border-radius: 24px; display: flex; align-items: center; justify-content: center; background: {{ $c['found'] ? $hex($c['look']['belly'] ?? null, '#F3F0FF') : '#F8F5FF' }}">
                        @if ($c['found'])
                            <button type="button" data-first :class="singing ? 'gw-sing' : ''" x-on:click="sing(@js($c['key']))" aria-label="{{ __($a.'voice') }}" style="background: none; border: 0; padding: 0; cursor: pointer">
                                <span class="gw-hover" style="display: block">@include('shared::games.creature', ['look' => $c['look'], 'size' => 170, 'motion' => true, 'line' => '#24124F'])</span>
                            </button>
                        @else
                            <span data-first tabindex="-1" style="font-size: 72px; font-weight: 800; color: #6236E0">؟</span>
                        @endif
                    </div>
                    <div style="margin-top: 10px; display: flex; align-items: center; justify-content: space-between; gap: 8px">
                        <h2 id="sheet-{{ $c['key'] }}">{{ $c['found'] ? ($c['nickname'] ?: $c['name']) : __($a.'not_yet') }}</h2>
                        <span style="flex: none; padding: 2px 12px; border-radius: 999px; font-size: 14px; font-weight: 800; border: 2px solid #24124F; background: {{ $rarityBg[$r] ?? '#F1F3F5' }}">{{ __('shared::games.rarity.'.$r) }}</span>
                    </div>
                    @if ($c['found'])
                        <p>{{ $c['story'] }}@if ($c['nickname']) <span style="color: #5C4A99">({{ $c['name'] }})</span>@endif<br><small style="color: #5C4A99; font-weight: 700">{{ $c['island_name'] }} · {{ __($a.'from.'.($c['source'] ?? 'stars')) }}</small></p>
                        @unless ($guardian)
                            <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-bottom: 12px">
                                <span style="font-size: 14px; font-weight: 800">{{ __($a.'name_it') }}</span>
                                @foreach ($suggest($c) as $name)
                                    <button type="button" class="gw-name gw-3d" style="height: 36px; font-size: 15px; padding: 0 12px" aria-checked="{{ ($c['nickname'] ?: $c['name']) === $name ? 'true' : 'false' }}" role="radio" x-on:click="rename(@js($c['key']), @js($name))">{{ $name }}</button>
                                @endforeach
                            </div>
                        @endunless
                        <div style="display: flex; gap: 10px">
                            <button type="button" class="gw-bigbtn gw-3d" style="flex: 1; border-color: #24124F; box-shadow: 0 4px 0 #24124F; font-size: 17px" x-on:click="sing(@js($c['key']))">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#24124F" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>
                                {{ __($a.'voice') }}
                            </button>
                            @unless ($guardian)
                                <button type="button" class="gw-bigbtn gw-3d" data-kind="go" style="flex: 1; border-color: #24124F; box-shadow: 0 4px 0 #24124F; font-size: 17px; color: #24124F" @if ($c['buddy']) disabled @endif x-on:click="buddy(@js($c['key']), @js($c['nickname'] ?: $c['name']))">{{ $c['buddy'] ? __($a.'is_buddy') : __($a.'buddy') }}</button>
                            @endunless
                        </div>
                    @else
                        <p>{{ __($a.'locked_story') }}</p>
                        <div style="padding: 12px 14px; border-radius: 18px; background: #F3F0FF; border: 2px dashed #9775FA; font-size: 16px; font-weight: 700">{{ __($a.'how', ['how' => $how]) }}</div>
                    @endif
                </section>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
