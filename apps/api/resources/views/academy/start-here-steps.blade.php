{{-- The steps of one part of "Start here": done or not, what it is, and the way to its screen (greyed for who may not do it). --}}
<ul class="sh-steps">
    @foreach ($steps as $step)
        <li class="sh-step" data-step="{{ $step['key'] }}" @if ($step['done']) data-done @endif @unless ($step['allowed']) data-locked @endunless wire:key="step-{{ $step['key'] }}">
            <span class="sh-mark" aria-hidden="true"><x-filament::icon icon="heroicon-m-check" /></span>
            <span class="sh-title">{{ $step['title'] }}</span>
            <span class="sh-hint">{{ $step['hint'] }}</span>
            <span class="sh-act">
                <span class="sh-state">{{ __('setup.state.'.($step['done'] ? 'done' : 'todo')) }}</span>
                @if ($step['url'] !== null && $step['allowed'])
                    <x-filament::button tag="a" :href="$step['url']" size="xs" :color="$step['done'] ? 'gray' : 'primary'" :outlined="$step['done']">
                        {{ __('setup.actions.'.($step['done'] ? 'open' : 'start')) }}
                    </x-filament::button>
                @elseif (! $step['done'])
                    <span class="sh-state">{{ __('setup.state.locked') }}</span>
                @endif
            </span>
        </li>
    @endforeach
</ul>
