@php
    /*
     * The week's chest: every lesson of the week attended and every homework due in it handed in. The student
     * opens it (its stars, and a child's next sticker for the album); a guardian sees how far the week is.
     */
    $m = 'learning::map.';
    $chest = $j['chest'];
    $kids = $kids ?? false;
    $stickerEmoji = ['lion' => '🦁', 'rocket' => '🚀', 'sun' => '☀️', 'whale' => '🐋', 'cat' => '🐱', 'tree' => '🌳', 'kite' => '🪁', 'castle' => '🏰', 'camel' => '🐫', 'owl' => '🦉', 'turtle' => '🐢', 'balloon' => '🎈',
        'moon' => '🌙', 'fish' => '🐟', 'bee' => '🐝', 'rainbow' => '🌈', 'plane' => '✈️', 'crown' => '👑', 'cupcake' => '🧁', 'dino' => '🦕', 'flower' => '🌸', 'robot' => '🤖', 'boat' => '⛵', 'trophy' => '🏆'];
    $stickers = $kids ? array_column(array_filter($j['stickers']['items'] ?? [], fn (array $s) => $s['earned']), 'key') : [];   // the kids' album (JourneyMap)
    $egg = $games['egg'] ?? null;
@endphp
@if ($chest)
    @php $pct = (int) round(100 * $chest['progress'] / max(1, $chest['target'])); $ready = $chest['state'] === 'done'; @endphp
    <section class="{{ $kids ? 'jk-card' : 'jm-card' }}" aria-labelledby="jm-chest" wire:key="chest-{{ $chest['period'] }}-{{ $chest['state'] }}">
        <div class="jm-chest" @if ($ready) data-ready @endif data-celebrate="journey-chest-{{ $this->learner()?->id }}" data-celebrate-value="{{ $chest['state'] === 'opened' ? 1 : 0 }}">
            <span aria-hidden="true">{{ $chest['state'] === 'opened' ? '🎉' : '🎁' }}</span>
            <div>
                <h2 id="jm-chest" style="margin: 0; font-size: 1.05rem; font-weight: 800">{{ __($m.'chest.title') }}</h2>
                <span class="{{ $kids ? 'jk-bar' : 'jm-bar' }}" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $chest['target'] }}" aria-valuenow="{{ $chest['progress'] }}" aria-label="{{ __($m.'chest.progress', ['progress' => $chest['progress'], 'target' => $chest['target']]) }}"><i class="lms-bar-fill" style="width: {{ $pct }}%"></i></span>
                <small style="font-weight: 700">
                    @switch($chest['state'])
                        @case('opened') {{ $this->isSelf() ? __($m.'chest.done') : __($m.'chest.guardian_done', ['name' => $this->firstName()]) }} @break
                        @case('done') {{ $this->isSelf() ? __($m.'chest.ready') : __($m.'chest.guardian_ready', ['name' => $this->firstName()]) }} @break
                        @case('missed') {{ __($m.'chest.missed') }} @break
                        @default {{ __($m.'chest.progress', ['progress' => $chest['progress'], 'target' => $chest['target']]) }} · {{ __($m.'chest.hint') }}
                    @endswitch
                </small>
            </div>
        </div>
        @if ($ready && $this->isSelf())
            <button type="button" class="{{ $kids ? 'jk-go' : 'jm-btn' }}" wire:click="openChest('{{ $chest['period'] }}')" wire:loading.attr="disabled">🎁 {{ __($m.'chest.open') }} <span dir="ltr" style="display: inline-flex; align-items: center; gap: 2px">+{{ $chest['stars'] }} <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.5l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.4 6.1 20.5l1.2-6.5L2.5 9.4l6.6-.9z" fill="#FFD43B" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg></span></button>
        @endif
        @if ($stickers !== [])
            <div>
                <small style="font-weight: 800">{{ __($m.'chest.stickers') }} · {{ count($stickers) }}</small>
                <div class="jm-stickers">
                    @foreach ($stickers as $s)<span title="{{ __('learning::challenges.stickers.'.$s) }}" aria-label="{{ __('learning::challenges.stickers.'.$s) }}">{{ $stickerEmoji[$s] ?? '⭐' }}</span>@endforeach
                </div>
            </div>
        @endif
    </section>
@endif
@if ($egg && ! $kids)
    <section class="jm-card" wire:key="egg-{{ $egg['have'] }}-{{ $egg['ready'] ? 1 : 0 }}">
        <div class="jm-egg">
            @if ($egg['ready'] && $this->isSelf())
                <button type="button" class="jm-egg-btn" x-on:click="$dispatch('journey-hatch', { island: @js($hereArt ?? null) })" aria-label="{{ __($m.'egg.ready') }} {{ __($m.'egg.tap') }}">
                    <svg width="40" height="48" viewBox="0 0 18 22" aria-hidden="true"><ellipse cx="9" cy="12" rx="8" ry="10" fill="#F3E3C3" stroke="#13294B" stroke-width="1.4"/><path d="M3 12 L6 9 L9 13 L12 9 L15 12" fill="none" stroke="#13294B" stroke-width="1.2"/></svg>
                </button>
            @else
                <svg width="34" height="42" viewBox="0 0 18 22" aria-hidden="true" style="flex: none"><ellipse cx="9" cy="12" rx="8" ry="10" fill="#F3E3C3" stroke="#13294B" stroke-width="1.4"/><path d="M3 12 L6 9 L9 13 L12 9 L15 12" fill="none" stroke="#13294B" stroke-width="1.2"/></svg>
            @endif
            <div style="flex: 1; min-width: 0">
                <strong>{{ $egg['ready'] && $this->isSelf() ? __($m.'egg.ready') : __($m.'egg.progress', ['have' => $egg['have'], 'need' => $egg['need']]) }}</strong>
                <span class="jm-bar" style="display: block; margin-top: .3rem"><i class="lms-bar-fill" style="width: {{ (int) round(100 * $egg['have'] / max(1, $egg['need'])) }}%; background: var(--warning-400)"></i></span>
            </div>
            @if ($egg['ready'] && $this->isSelf())
                <button type="button" class="jm-btn" x-on:click="$dispatch('journey-hatch', { island: @js($hereArt ?? null) })">{{ __($m.'egg.tap') }}</button>
            @endif
        </div>
    </section>
@endif
