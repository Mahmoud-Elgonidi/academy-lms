{{-- A ticket's lines. $lines from TicketView::lines(); $mineLabel / $theirsLabel name the two sides when no author is known. --}}
<div class="sp-thread">
    @foreach ($lines as $line)
        @if ($line['kind'] === 'event')
            <div class="sp-event" wire:key="line-{{ $line['id'] }}">
                <span>{{ $line['event'] }} · <time title="{{ $line['when'] }}">{{ $line['ago'] }}</time></span>
                @if ($line['note'])<em>{{ $line['note'] }}</em>@endif
            </div>
        @else
            <div class="sp-line" data-kind="{{ $line['kind'] }}" @if ($line['mine']) data-mine @endif wire:key="line-{{ $line['id'] }}">
                <div class="sp-bubble">@if ($line['body']){{ $line['body'] }}@endif
                    @if ($line['file'])
                        @if ($line['file']['image'])
                            <a href="{{ $line['file']['url'] }}" target="_blank" rel="noopener"><img class="sp-shot" src="{{ $line['file']['url'] }}" alt="{{ $line['file']['name'] }}" loading="lazy"></a>
                        @else
                            <a class="sp-file" href="{{ $line['file']['url'] }}" target="_blank" rel="noopener"><x-filament::icon icon="heroicon-m-paper-clip" />{{ $line['file']['name'] }}</a>
                        @endif
                    @endif
                </div>
                <small>
                    @if ($line['kind'] === 'note')<span class="sp-tag-note">{{ __('support::tickets.page.internal') }}</span> · @endif
                    {{ $line['author'] ?? ($line['mine'] ? $mineLabel : $theirsLabel) }}
                    @if ($line['writer']) · {{ __('support::tickets.page.written_by', ['name' => $line['writer']]) }}@endif
                    · <time title="{{ $line['when'] }}">{{ $line['ago'] }}</time>
                </small>
            </div>
        @endif
    @endforeach
</div>
