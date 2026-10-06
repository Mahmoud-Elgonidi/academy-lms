<x-filament-panels::page>
    @include('evaluation::filament.styles')
    @include('evaluation::filament.tiles', ['cards' => $this->cards])
    <div class="kp-two">
        @include('evaluation::filament.events', ['events' => $this->events, 'manage' => true])
        @include('evaluation::filament.ratings', ['ratings' => $this->ratings])
    </div>
</x-filament-panels::page>
