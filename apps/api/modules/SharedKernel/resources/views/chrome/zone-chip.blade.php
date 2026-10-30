{{--
    The zone chip (ZoneChip): a pill with the city of the person's own zone and its
    time; opened, a card with the academy's zone and the device's when they keep
    another clock, and a way to change the person's own. Styles in chrome/styles;
    the ticking in chrome/zone. A student's kids look leaves it out (People's stage).
    Hidden by the person (ZoneChip::hide), only its empty root stays, so it can come back
    in place when they show it again (ZoneChip::RECONSIDER).
--}}
<div class="lms-zone" wire:key="lms-zone" data-device="{{ $this->device }}"
    @if ($hidden) hidden @else data-locale="{{ $intlLocale }}" data-hour-cycle="{{ $hourCycle }}" data-mismatch="{{ $deviceRow ? 'true' : 'false' }}" @endif
    x-data="{ open: false }" x-init="LmsZone.mount($el, $wire)"
    x-on:click.outside="open = false" x-on:keydown.escape.window="open = false">
@unless ($hidden)
    <div class="lms-zone-card" x-show="open" x-cloak role="dialog" aria-label="{{ __('shared::chrome.zone.title') }}">
        @if (! $changing)
            <dl class="lms-zone-rows">
                <div class="lms-zone-row" data-kind="mine">
                    <dt>{{ __('shared::chrome.zone.yours') }}</dt>
                    <dd><strong>{{ $mine['label'] }}</strong><span class="lms-zone-at" data-lms-clock="{{ $mine['zone'] }}">{{ $mine['time'] }}</span></dd>
                </div>
                @if ($academyRow)
                    <div class="lms-zone-row" data-kind="academy">
                        <dt>{{ __('shared::chrome.zone.academy') }}</dt>
                        <dd><strong>{{ $academyRow['label'] }}</strong><span class="lms-zone-at" data-lms-clock="{{ $academyRow['zone'] }}">{{ $academyRow['time'] }}</span></dd>
                    </div>
                @endif
                @if ($deviceRow)
                    <div class="lms-zone-row" data-kind="device">
                        <dt>{{ __('shared::chrome.zone.device') }}</dt>
                        <dd><strong>{{ $deviceRow['label'] }}</strong><span class="lms-zone-at" data-lms-clock="{{ $deviceRow['zone'] }}">{{ $deviceRow['time'] }}</span></dd>
                    </div>
                @endif
            </dl>
            @if ($deviceRow)
                <p class="lms-zone-note">{{ __('shared::chrome.zone.device_differs') }}</p>
            @else
                <p class="lms-zone-note">{{ __('shared::chrome.zone.hint') }}</p>
            @endif
            @if ($canChange)
                <div class="lms-zone-actions">
                    @if ($deviceRow)
                        <button type="button" class="lms-zone-btn" data-primary wire:click="useDevice" wire:loading.attr="disabled">{{ __('shared::chrome.zone.use_device') }}</button>
                    @endif
                    <button type="button" class="lms-zone-btn" @if (! $deviceRow) data-primary @endif wire:click="$set('changing', true)" wire:loading.attr="disabled">{{ __('shared::chrome.zone.change') }}</button>
                </div>
                <button type="button" class="lms-zone-hide" x-on:click="LmsZone.hide($root, $wire).then(() => open = false)" wire:loading.attr="disabled">
                    {{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::OutlinedEyeSlash) }}
                    <span>{{ __('shared::chrome.zone.hide') }}</span>
                </button>
            @endif
        @else
            <div class="lms-zone-pick">
                <div class="lms-zone-pick-head">
                    <button type="button" class="lms-zone-back" wire:click="$set('changing', false)" aria-label="{{ __('shared::chrome.zone.back') }}">
                        {{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::OutlinedArrowRight, attributes: new \Illuminate\View\ComponentAttributeBag(['class' => 'lms-zone-flip'])) }}
                    </button>
                    <input type="search" class="lms-zone-search" placeholder="{{ __('shared::chrome.zone.search') }}" aria-label="{{ __('shared::chrome.zone.search') }}"
                        x-init="$nextTick(() => $el.focus())" x-on:input="LmsZone.filter($refs.list, $event.target.value)">
                </div>
                <div class="lms-zone-list" x-ref="list">
                    @foreach (collect($zones)->groupBy(fn ($z) => $z->region) as $region => $items)
                        <section data-region>
                            <h3>{{ $region }}</h3>
                            @foreach ($items as $z)
                                <button type="button" class="lms-zone-option" wire:click="choose('{{ $z->id }}')" wire:key="zone-{{ $z->id }}"
                                    data-search="{{ mb_strtolower($z->city.' '.$z->id.' '.$z->offset) }}" @if ($z->id === $mine['zone']) aria-current="true" @endif>
                                    <span>{{ $z->city }}</span>
                                    <small dir="ltr">{{ $z->offset }}</small>
                                </button>
                            @endforeach
                        </section>
                    @endforeach
                    <p class="lms-zone-note" data-none hidden>{{ __('shared::chrome.zone.none') }}</p>
                </div>
            </div>
        @endif
    </div>

    <button type="button" class="lms-zone-pill" x-on:click="open = ! open" x-bind:aria-expanded="open ? 'true' : 'false'" aria-expanded="false"
        aria-label="{{ __('shared::chrome.zone.open', ['zone' => $mine['label']]) }}">
        {{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::OutlinedClock) }}
        <span class="lms-zone-city">{{ $mine['city'] }}</span>
        <span class="lms-zone-time" data-lms-clock="{{ $mine['zone'] }}">{{ $mine['time'] }}</span>
        @if ($deviceRow)
            <span class="lms-zone-dot" aria-hidden="true"></span>
        @endif
    </button>
@endunless
</div>
