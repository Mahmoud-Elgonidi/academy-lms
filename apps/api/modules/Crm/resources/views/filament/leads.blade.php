@php
    $l = 'crm::leads.';
    $board = $this->board;
    $funnel = $this->funnel;
    $recent = $this->recent;
@endphp
<x-filament-panels::page>
    @include('crm::filament.styles')

    <div>
        <p class="crm-funnel-title">{{ __($l.'funnel.title') }}</p>
        <div class="crm-funnel lms-stagger">
            @foreach (['leads', 'booked', 'attended', 'won'] as $key)
                <div class="crm-card-base crm-stat" data-key="{{ $key }}"><small>{{ __($l.'funnel.'.$key) }}</small><strong data-count="{{ $funnel[$key] }}">{{ $funnel[$key] }}</strong></div>
            @endforeach
            <div class="crm-card-base crm-stat" data-key="rate"><small>{{ __($l.'funnel.rate') }}</small><strong>{{ $funnel['rate'] === null ? '—' : $funnel['rate'].'%' }}</strong></div>
        </div>
    </div>

    <div class="crm-filters">
        <div class="crm-seg" role="group">
            <button type="button" wire:click="$set('owner', 'mine')" aria-pressed="{{ $owner === 'mine' ? 'true' : 'false' }}">{{ __($l.'mine') }}</button>
            <button type="button" wire:click="$set('owner', 'all')" aria-pressed="{{ $owner === 'all' ? 'true' : 'false' }}">{{ __($l.'all') }}</button>
        </div>
        <input type="search" class="crm-input" wire:model.live.debounce.400ms="search" placeholder="{{ __($l.'search') }}" aria-label="{{ __($l.'search') }}">
        <select class="crm-input" style="flex: 0 1 12rem" wire:model.live="source" aria-label="{{ __($l.'fields.source') }}">
            <option value="">{{ __($l.'any_source') }}</option>
            @foreach ($this->sources() as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="crm-board">
        @foreach ($board as $stage => $column)
            <section class="crm-col" data-stage="{{ $stage }}" wire:key="col-{{ $stage }}">
                <header><span>{{ $column['label'] }}</span><small>{{ count($column['cards']) + $column['more'] }}</small></header>
                <div class="crm-cards lms-stagger">
                    @forelse ($column['cards'] as $card)
                        <a class="crm-card-base crm-card lms-lift" href="{{ $card['url'] }}" wire:navigate wire:key="lead-{{ $card['id'] }}">
                            <strong>@if ($card['fresh'])<span class="crm-dot" aria-hidden="true"></span>@endif{{ $card['name'] }}</strong>
                            @if ($card['for'])<small>{{ $card['for'] }}</small>@endif
                            <span class="crm-note">{{ $card['note'] }}</span>
                            <span class="crm-tags">
                                @if ($card['subject'])<span class="crm-tag">{{ $card['subject'] }}</span>@endif
                                <span class="crm-tag">{{ $card['source'] }}</span>
                                <span class="crm-tag" data-kind="owner">{{ $card['owner'] ?? __($l.'card.unowned') }}</span>
                            </span>
                        </a>
                    @empty
                        <div class="crm-empty">{{ __($l.'empty_stage') }}</div>
                    @endforelse
                    @if ($column['more'] > 0)<div class="crm-more">{{ __($l.'more', ['count' => $column['more']]) }}</div>@endif
                </div>
            </section>
        @endforeach
    </div>

    <div class="crm-recent">
        @foreach (['won', 'lost'] as $kind)
            <section class="crm-card-base">
                <h3>{{ __($l.'recent.'.$kind) }}</h3>
                @forelse ($recent[$kind] as $row)
                    <a href="{{ $row['url'] }}" wire:navigate><span>{{ $row['name'] }}</span><small>{{ $row['detail'] }}</small></a>
                @empty
                    <small class="crm-empty" style="display: block">{{ __($l.'recent.none') }}</small>
                @endforelse
            </section>
        @endforeach
    </div>
</x-filament-panels::page>
