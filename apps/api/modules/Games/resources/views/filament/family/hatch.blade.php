@php
    /*
     * A creature hatched from a round's stars (the approved design, Hatch): the night, a nest,
     * an egg to tap three times, then the creature comes out among feathers and the child names
     * it (its own name or one of two more) and puts it in the album.
     */
    $h = 'games::games.hatch.';
    $c = $creature;
    $hex = fn ($v, $d) => is_string($v) && preg_match('/^#[0-9a-f]{6}$/i', $v) ? $v : $d;
    $feathers = [$hex($c['look']['body'] ?? null, '#C9A06B'), '#FFD43B', $hex($c['look']['wing'] ?? null, '#B197FC'), $hex($c['look']['belly'] ?? null, '#C9A06B'), '#63E6BE'];
    $config = ['key' => $c['key'], 'names' => $c['names'], 'pitch' => $c['look']['pitch'] ?? 1, 'hints' => __($h.'tap')];
@endphp
<div class="gw-hatch gw-kids" role="dialog" aria-modal="true" aria-labelledby="gw-hatch-title" x-data="LmsIslands.hatch(@js($config))" wire:key="hatch-{{ $c['key'] }}">
    <div class="gw-hatch-stage">
        <svg aria-hidden="true" class="gw-abs" style="inset: 0" width="390" height="844" viewBox="0 0 390 844">
            <circle class="gw-twinkle" cx="40" cy="160" r="2.5" fill="#FFFFFF"/>
            <circle class="gw-twinkle gw-d2" cx="350" cy="200" r="2" fill="#FFFFFF"/>
            <circle class="gw-twinkle gw-d3" cx="300" cy="120" r="3" fill="#FFE8A3"/>
            <circle class="gw-twinkle" cx="80" cy="300" r="2" fill="#FFFFFF"/>
            <circle class="gw-twinkle gw-d2" cx="360" cy="380" r="2.5" fill="#FFFFFF"/>
            <circle class="gw-twinkle gw-d3" cx="20" cy="440" r="2" fill="#FFE8A3"/>
            <circle cx="330" cy="70" r="26" fill="#FFE8A3"/><circle cx="342" cy="62" r="24" fill="#2B1A66"/>
        </svg>

        <header class="gw-abs" style="top: 0; left: 0; right: 0; height: 64px; padding: 0 16px; display: flex; align-items: center; z-index: 6">
            <button type="button" class="gw-3d" wire:click="leave" aria-label="{{ __('games::games.map.to_island') }}" style="width: 44px; height: 44px; border-radius: 999px; background: #FFFFFF; border: 3px solid #24124F; box-shadow: 0 3px 0 #120A33; display: flex; align-items: center; justify-content: center; padding: 0">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#24124F" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="{{ app()->getLocale() === 'ar' ? '' : 'transform: scaleX(-1)' }}"><path d="M9 5l7 7-7 7"/></svg>
            </button>
        </header>

        <div aria-live="polite" class="gw-abs" style="top: 76px; left: 20px; right: 20px; text-align: center; z-index: 5">
            <h2 id="gw-hatch-title" style="margin: 0; font-size: 30px; font-weight: 800; line-height: 1.15" x-text="hatched ? @js(__($h.'done')) : @js(__($h.'ready'))">{{ __($h.'ready') }}</h2>
            <p style="margin: 4px 0 0; font-size: 16px; font-weight: 600; color: #D0BFFF" x-text="hatched ? @js(__($h.'done_sub', ['need' => $need])) : @js(__($h.'ready_sub', ['need' => $need]))">{{ __($h.'ready_sub', ['need' => $need]) }}</p>
        </div>

        <template x-if="hatched">
            <div class="gw-rays-in gw-abs" aria-hidden="true" style="left: 35px; top: 190px; width: 320px; height: 320px">
                <svg class="gw-spin" width="320" height="320" viewBox="-160 -160 320 320">
                    @for ($r = 0; $r < 360; $r += 36)<path d="M0 0 L-20 -160 L20 -160 Z" fill="#4A35A0" transform="rotate({{ $r }})"/>@endfor
                </svg>
            </div>
        </template>

        <svg aria-hidden="true" class="gw-abs" style="left: 65px; top: 470px; z-index: 1" width="260" height="90" viewBox="0 0 260 90">
            <ellipse cx="130" cy="50" rx="126" ry="36" fill="#6B4423"/>
            <path d="M10 44 C60 70 200 70 250 44 M20 56 C80 80 180 80 240 56 M30 36 L70 60 M230 36 L190 60 M90 30 L120 62 M170 30 L140 62" stroke="#9C6B3F" stroke-width="6" stroke-linecap="round" fill="none"/>
            <ellipse cx="130" cy="30" rx="96" ry="18" fill="#3E2614"/>
        </svg>

        <template x-if="! hatched">
            <div>
                <button type="button" :class="knocking ? 'gw-knock' : 'gw-idle'" x-on:click="tap()" :aria-label="@js(__($h.'egg', ['left' => '__N__'])).replace('__N__', Math.max(0, 3 - taps))"
                        class="gw-abs" style="left: 115px; top: 300px; width: 160px; height: 200px; background: none; border: 0; padding: 0; cursor: pointer; z-index: 2">
                    <svg width="160" height="200" viewBox="0 0 160 200" aria-hidden="true">
                        <ellipse cx="80" cy="110" rx="62" ry="80" fill="#F3E3C3" stroke="#24124F" stroke-width="5"/>
                        <circle cx="56" cy="70" r="10" fill="#E0C79A"/><circle cx="110" cy="140" r="14" fill="#E0C79A"/><circle cx="104" cy="58" r="6" fill="#E0C79A"/><circle cx="50" cy="150" r="7" fill="#E0C79A"/>
                        <path d="M30 96 L44 86 L52 100 L64 90" fill="none" stroke="#24124F" stroke-width="4" stroke-linejoin="round" stroke-linecap="round" :opacity="taps >= 1 ? 1 : 0"/>
                        <path d="M64 90 L76 104 L90 90 L102 102" fill="none" stroke="#24124F" stroke-width="4" stroke-linejoin="round" stroke-linecap="round" :opacity="taps >= 2 ? 1 : 0"/>
                        <path d="M102 102 L114 88 L130 100 M90 90 L92 74" fill="none" stroke="#24124F" stroke-width="4" stroke-linejoin="round" stroke-linecap="round" :opacity="taps >= 3 ? 1 : 0"/>
                    </svg>
                </button>
                <div class="gw-tap-hint gw-abs" style="top: 580px; left: 0; right: 0; display: flex; justify-content: center; z-index: 3">
                    <span style="padding: 6px 18px; border-radius: 999px; background: #FFD43B; color: #24124F; border: 3px solid #24124F; font-size: 17px; font-weight: 800" x-text="hint()"></span>
                </div>
            </div>
        </template>

        <template x-if="hatched">
            <div>
                <svg aria-hidden="true" class="gw-abs" style="left: 115px; top: 300px; z-index: 3; overflow: visible" width="160" height="200" viewBox="0 0 160 200">
                    <path d="M18 112 L32 116 L46 102 L60 116 L74 102 L88 116 L102 100 L116 116 L130 102 L142 112 C142 162 112 190 80 190 C48 190 18 162 18 112 Z" fill="#F3E3C3" stroke="#24124F" stroke-width="5" stroke-linejoin="round"/>
                    <circle cx="110" cy="150" r="12" fill="#E0C79A"/>
                    <g class="gw-shell-top"><path d="M18 112 C18 52 50 30 80 30 C110 30 142 52 142 112 L130 102 L116 116 L102 100 L88 116 L74 102 L60 116 L46 102 L32 116 Z" fill="#F3E3C3" stroke="#24124F" stroke-width="5" stroke-linejoin="round"/><circle cx="56" cy="70" r="10" fill="#E0C79A"/></g>
                </svg>
                <button type="button" class="gw-emerge gw-abs" x-on:click="LmsIslandsSound?.voice(@js($config['pitch']))" aria-label="{{ $c['name'] }}" style="left: 95px; top: 210px; width: 200px; height: 176px; background: none; border: 0; padding: 0; cursor: pointer; z-index: 4">
                    <span class="gw-hover" style="display: block">@include('shared::games.creature', ['look' => $c['look'], 'size' => 200, 'motion' => true, 'line' => '#24124F'])</span>
                </button>
                <div aria-hidden="true" class="gw-abs" style="inset: 0; z-index: 5; pointer-events: none">
                    @foreach ([[60, 18, 34, 0], [160, 14, 28, .4], [260, 18, 34, .2], [330, 14, 28, .7], [20, 16, 30, .9]] as $i => [$x, $fw, $fh, $delay])
                        <svg class="gw-feather gw-abs" style="left: {{ $x }}px; top: 0; animation-delay: {{ $delay }}s" width="{{ $fw }}" height="{{ $fh }}" viewBox="0 0 18 34"><path d="M9 2 C18 10 16 24 9 32 C2 24 0 10 9 2 Z" fill="{{ $feathers[$i] }}"/><path d="M9 4 V32" stroke="#24124F" stroke-opacity=".35" stroke-width="1.5"/></svg>
                    @endforeach
                </div>

                <section class="gw-hatch-card" aria-labelledby="gw-new-name">
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px">
                        <div style="min-width: 0">
                            <div style="font-size: 13px; font-weight: 700; color: #5C4A99">{{ __($h.'new', ['found' => $c['found'], 'total' => $c['total']]) }}</div>
                            <h3 id="gw-new-name" style="margin: 0; font-size: 24px; font-weight: 800; line-height: 1.2">{{ $c['name'] }}</h3>
                        </div>
                        <span style="flex: none; padding: 2px 10px; border-radius: 999px; background: #FFF4D6; border: 2px solid #24124F; font-size: 13px; font-weight: 800">{{ $c['island'] }}</span>
                    </div>
                    <div style="margin-top: 10px; font-size: 15px; font-weight: 700">{{ __($h.'ask') }}</div>
                    <div role="radiogroup" aria-label="{{ __($h.'names_label') }}" style="margin-top: 6px; display: flex; gap: 8px; flex-wrap: wrap">
                        @foreach ($c['names'] as $i => $name)
                            <button type="button" role="radio" class="gw-name gw-3d" :aria-checked="(name === {{ $i }}).toString()" x-on:click="pick({{ $i }})">{{ $name }}</button>
                        @endforeach
                    </div>
                    <button type="button" class="gw-bigbtn gw-3d" data-kind="go" style="margin-top: 12px; border-color: #24124F; box-shadow: 0 4px 0 #24124F; color: #24124F" x-on:click="keep()" :disabled="saving">{{ __($h.'keep') }}</button>
                </section>
            </div>
        </template>
    </div>
</div>
