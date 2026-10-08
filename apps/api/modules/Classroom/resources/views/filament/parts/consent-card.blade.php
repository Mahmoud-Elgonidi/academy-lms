{{-- One person's consent to recordings: agree (after ticking the box), or take it back; or who decides when the viewer cannot. --}}
@php $l = 'classroom::recordings.consent.'; @endphp
<article class="rc-card" data-state="{{ $consent['state'] }}" wire:key="rc-consent-{{ $consent['person']['id'] }}">
    <div class="rc-head">
        <h3>{{ $consent['text']['title'] }}@unless ($consent['self']) · {{ $consent['person']['name'] }}@endunless</h3>
        <span class="rc-pill" data-tone="{{ $consent['state'] === 'agreed' ? 'good' : 'warn' }}">{{ $consent['state'] === 'agreed' ? __($l.'state.agreed') : __($l.'state.none') }}</span>
    </div>
    @if ($consent['state'] === 'agreed')
        <p class="rc-muted">{{ $consent['by'] }}</p>
        @if (! $records)<p class="rc-muted">{{ __($l.'off_note') }}</p>@endif
        @if ($consent['can_change'])
            <div>
                <x-filament::button color="danger" outlined size="sm" icon="heroicon-o-no-symbol"
                    wire:click="withdraw('{{ $consent['person']['id'] }}')" wire:confirm="{{ __($l.'withdraw_confirm') }}">{{ __($l.'withdraw') }}</x-filament::button>
            </div>
        @endif
    @elseif ($consent['can_change'])
        <p class="rc-intro">{{ $consent['text']['intro'] }}</p>
        <ul class="rc-points">
            @foreach ($consent['text']['sections'] as $section)
                <li><strong>{{ $section['title'] }}</strong><span>{{ $section['body'] }}</span></li>
            @endforeach
        </ul>
        <label class="rc-agree">
            <input type="checkbox" wire:model="agreed.{{ $consent['person']['id'] }}">
            <span>{{ $consent['text']['agree'] }}</span>
        </label>
        @error('agreed.'.$consent['person']['id'])<p class="rc-error">{{ $message }}</p>@enderror
        <div><x-filament::button wire:click="agree('{{ $consent['person']['id'] }}')" icon="heroicon-o-check">{{ __($l.'agree_button') }}</x-filament::button></div>
    @else
        <p class="rc-muted">{{ $consent['decides'] }}</p>
    @endif
</article>
