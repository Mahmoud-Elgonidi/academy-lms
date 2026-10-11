@php
    $l = 'games::games.';
    $w = $this->world;
    $round = $this->round;
    $result = $this->result;
    $look = $this->look();
@endphp
<x-filament-panels::page>
    @include('shared::games.art-styles')
    @include('games::islands.styles')
    @include('games::filament.family.hub-styles')
    @include('shared::games.sound')
    @include('games::islands.script')

    @if ($w === null)
        <p class="gw-empty">{{ __($l.'no_learner') }}</p>
    @elseif ($look === 'kids')
        @include('games::filament.family.kids', ['learnerName' => $this->learnerName(), 'openIsland' => $this->island ?? ($round['island'] ?? null)])
        @if ($result && $result['creature'])
            @include('games::filament.family.hatch', ['creature' => $result['creature'], 'need' => $result['egg']['need']])
        @elseif ($result)
            @include('games::filament.family.done', ['learnerId' => $this->learnerId()])
        @endif
    @elseif ($this->isGuardian())
        @include('games::filament.family.guardian')
    @else
        @include('games::filament.family.hub')
    @endif

    @if ($round)
        @include('games::filament.family.round', ['look' => $look])
    @endif
</x-filament-panels::page>

@script
<script>
    // A round: mount the sandboxed player, keep every try the game reports, and hand them in when it finishes.
    let answers = [];
    let finishing = false;
    const mount = () => {
        const root = $wire.$el.querySelector('[data-round-player]');
        if (root && window.LmsGame && ! window.LmsGame.of(root)) {
            answers = [];
            finishing = false;
            root.dataset.muted = window.LmsIslandsSound?.muted() ? '1' : '0';
            window.LmsGame.mount(root);
        }
    };
    $wire.$el.addEventListener('lms-game:answer', (e) => { answers.push({ item: e.detail.item, correct: e.detail.correct }); });
    $wire.$el.addEventListener('lms-game:finish', () => {
        if (finishing) return;
        finishing = true;
        $wire.finish(answers);
    });
    new MutationObserver(mount).observe($wire.$el, { childList: true, subtree: true });
    mount();

    // A round played elsewhere (the app), a creature hatched, a limit changed: the world follows the learner's channel.
    const live = $wire.$el.querySelector('[data-live-channel]');
    if (live && window.LmsLive) {
        window.LmsLive.watch({ el: live, channel: live.dataset.liveChannel, event: '.practice.changed', refresh: () => $wire.$refresh(), every: 120000 });
    }
</script>
@endscript
