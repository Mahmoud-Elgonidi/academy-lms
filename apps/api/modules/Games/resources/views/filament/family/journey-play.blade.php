@php
    /*
     * The games on the journey (JourneyPlay): a round full screen in its sandboxed frame, then its result (the
     * children's over the island, the others' as a card), and a creature that hatches from its egg to be named.
     */
    $l = 'games::games.';
    $round = $this->round;
    $result = $this->result;
    $look = $this->look();
@endphp
<div class="gw-journey-play" wire:key="gw-journey-play">
    @include('shared::games.world-styles')
    @include('games::filament.family.play-styles')
    @include('shared::games.sound')
    @include('shared::games.world-script')

    @if ($this->hatched)
        @include('games::filament.family.hatch', ['creature' => $this->hatched, 'need' => \Modules\Games\Domain\Round::STARS_PER_EGG])
    @elseif ($result && $result['creature'] && ! $this->isGuardian())
        @include('games::filament.family.hatch', ['creature' => $result['creature'], 'need' => $result['egg']['need']])
    @elseif ($result && $look === 'kids')
        @include('games::filament.family.done', ['learnerId' => $this->learnerId()])
    @elseif ($result)
        @include('games::filament.family.result')
    @endif

    @if ($round)
        @include('games::filament.family.round', ['look' => $look])
    @endif
</div>

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
</script>
@endscript
