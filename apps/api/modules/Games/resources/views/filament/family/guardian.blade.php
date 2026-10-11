@php
    /*
     * A guardian's view of their children's games (the approved design, Guardian): which child,
     * today's play time against the limit with the minutes a day and the night lock, what the
     * child practised this week, a creature hatched today to cheer, the safety promise, and the
     * quests to play together. Pay and KPIs never appear here.
     */
    $g = 'games::games.guardian.';
    $l = 'games::games.';
    $glance = $this->glance;
    $limits = $this->limits;
    $a = $glance['allowance'];
    $used = (int) $a['used'];
    $limit = $a['limit'];
    $pct = $limit ? min(1, $used / max(1, $limit)) : 0;
    $circ = 2 * M_PI * 34;
    $children = $this->children();
    $current = $this->learnerId();
    $choices = collect($limits['choices'])->filter(fn ($m) => $limits['academy'] === null || $m <= $limits['academy'])->values();
@endphp
<div class="gw-guard" data-live-channel="{{ $this->channel() }}" wire:key="gw-guard">
    <header class="gw-guard-head">
        <h1>{{ __($g.'title') }}</h1>
        @if (count($children) > 1)
            <div role="tablist" aria-label="{{ __($g.'children') }}">
                @foreach ($children as $child)
                    <button type="button" role="tab" aria-selected="{{ $child['id'] === $current ? 'true' : 'false' }}" wire:click="chooseChild('{{ $child['id'] }}')" wire:key="c-{{ $child['id'] }}">{{ $child['name'] }}</button>
                @endforeach
            </div>
        @endif
    </header>

    <section class="gw-guard-card" aria-labelledby="gw-time">
        <div style="display: flex; align-items: center; gap: 14px">
            <svg width="84" height="84" viewBox="0 0 84 84" role="img" aria-label="{{ __($g.'ring', ['used' => $used, 'limit' => $limit ?? '∞']) }}">
                <circle cx="42" cy="42" r="34" fill="none" stroke="#F0EEEB" stroke-width="10"/>
                <circle class="gw-ring" cx="42" cy="42" r="34" fill="none" stroke="var(--primary-600)" stroke-width="10" stroke-linecap="round" stroke-dasharray="{{ round($circ * $pct, 1) }} {{ round($circ, 1) }}" transform="rotate(-90 42 42)"/>
                <text x="42" y="40" text-anchor="middle" font-size="20" font-weight="700" fill="currentColor">{{ $used }}</text>
                <text x="42" y="56" text-anchor="middle" font-size="10" fill="#57534E">{{ __($g.'minutes') }}</text>
            </svg>
            <div style="flex: 1; min-width: 0">
                <h2 id="gw-time">{{ __($g.'time') }}</h2>
                <p>{{ $limit ? __($g.'time_text', ['used' => $used, 'limit' => $limit, 'left' => $a['left']]) : __($g.'time_open', ['used' => $used]) }}</p>
                @unless ($a['may'])<p class="gw-guard-warn">{{ __($l.'refused.'.$a['reason']) }}</p>@endunless
            </div>
        </div>
        <div class="gw-guard-label">{{ __($g.'max') }}</div>
        <div role="radiogroup" aria-label="{{ __($g.'max_label') }}" class="gw-guard-chips" style="grid-template-columns: repeat({{ $choices->count() + 1 }}, minmax(0, 1fr))">
            @foreach ($choices as $m)
                <button type="button" role="radio" aria-checked="{{ $limits['daily_minutes'] === $m ? 'true' : 'false' }}" wire:click="setMinutes({{ $m }})">{{ __($g.'choice', ['n' => $m]) }}</button>
            @endforeach
            <button type="button" role="radio" aria-checked="{{ $limits['daily_minutes'] === null ? 'true' : 'false' }}" wire:click="setMinutes(null)">{{ __($g.'academy') }}</button>
        </div>
        <label class="gw-guard-switch">
            <span>{{ __($g.'night') }}</span>
            <input type="checkbox" @checked($limits['night_lock']) wire:click="setNight($event.target.checked)">
        </label>
        <label class="gw-guard-switch">
            <span>{{ __($g.'enabled') }}</span>
            <input type="checkbox" @checked($limits['enabled']) wire:click="setEnabled($event.target.checked)">
        </label>
    </section>

    <section class="gw-guard-card" aria-labelledby="gw-learned">
        <h2 id="gw-learned" style="margin-bottom: 8px">{{ __($g.'learned') }}</h2>
        <div class="gw-guard-lines">
            @if ($glance['practised'])
                <div><span class="gw-ico" style="background: #E6F4F1"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#134E4A" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5 9-10"/></svg></span>
                    <span>{{ __($g.'practised', ['title' => $glance['practised']['title'], 'rounds' => trans_choice($g.'rounds', $glance['practised']['rounds'], ['count' => $glance['practised']['rounds']]), 'games' => trans_choice($g.'games', $glance['practised']['games'], ['count' => $glance['practised']['games']])]) }}</span></div>
            @endif
            @if ($glance['hardest'])
                <div><span class="gw-ico" style="background: #FEF3C7"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#92400E" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16v.5"/></svg></span>
                    <span>{{ __($g.'hardest', ['text' => \Illuminate\Support\Str::limit($glance['hardest']['text'], 60)]) }}</span></div>
            @endif
            @if ($glance['best'])
                <div><span class="gw-ico" style="background: #EDE9FE"><svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.5l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.4 6.1 20.5l1.2-6.5L2.5 9.4l6.6-.9z" fill="#7C3AED"/></svg></span>
                    <span>{{ __($g.'best', $glance['best']) }}</span></div>
            @endif
            @if (! $glance['practised'] && ! $glance['hardest'] && ! $glance['best'])
                <p class="gw-guard-muted">{{ __($g.'quiet') }}</p>
            @endif
        </div>
    </section>

    @if ($glance['hatched'])
        @php $c = $glance['hatched']; @endphp
        <section class="gw-guard-card gw-guard-hatch" aria-labelledby="gw-hatched" x-data="{ hearts: false }">
            <span class="gw-guard-pet">
                <span class="gw-hover" style="display: block">@include('shared::games.creature', ['look' => $c['look'], 'size' => 62, 'motion' => true, 'line' => '#24124F'])</span>
                <template x-if="hearts"><span aria-hidden="true" class="gw-hearts"><i>💗</i><i>💖</i><i>💗</i></span></template>
            </span>
            <div style="flex: 1; min-width: 0">
                <h2 id="gw-hatched" style="font-size: 15px">{{ __($g.'hatched', ['name' => $c['name']]) }}</h2>
                <div class="gw-guard-muted">{{ __($g.'hatched_sub.'.($c['source'] === 'mastery' ? 'mastery' : 'stars'), ['found' => $c['found']]) }}</div>
            </div>
            <button type="button" class="gw-guard-btn" x-on:click="hearts = true; LmsIslandsSound?.play('win')" wire:click="cheer">{{ __($g.'cheer') }}</button>
        </section>
    @endif

    <div class="gw-guard-safe">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#065F46" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="flex: none; margin-top: 2px"><path d="M12 3l7 3v5c0 5-3 8-7 10-4-2-7-5-7-10V6z"/><path d="M9 12l2 2 4-4"/></svg>
        <span>{{ __($g.'safety') }}</span>
    </div>

    @if ($w['quests'] !== [])
        <section class="gw-guard-card">
            <h2 style="margin-bottom: 8px">{{ __($g.'together') }}</h2>
            <div class="gw-guard-lines">
                @foreach ($w['quests'] as $q)
                    <div wire:key="gq-{{ $q['key'] }}">
                        <span class="gw-ico" style="background: var(--primary-50)">🎮</span>
                        <span style="flex: 1; min-width: 0"><b>{{ $q['title'] ?: __($l.'reasons.'.$q['reason']) }}</b><br><small class="gw-guard-muted">{{ __($l.'reasons.'.$q['reason']) }} · {{ $q['game']['title'] }}</small></span>
                        <button type="button" class="gw-guard-btn" style="height: 38px" wire:click="play('{{ $q['game']['id'] }}', '{{ $q['key'] }}')" @disabled(! $a['may'])>{{ __($l.'play') }}</button>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($result)
        <section class="gw-guard-card" role="status" wire:key="gres-{{ $result['play'] }}">
            <h2>{{ __($l.'result.'.min(3, $result['stars'])) }} {{ str_repeat('★', $result['stars']) }}</h2>
            <p class="gw-guard-muted">{{ __($l.'result.score', ['correct' => $result['correct'], 'total' => $result['total']]) }}</p>
        </section>
    @endif
</div>
