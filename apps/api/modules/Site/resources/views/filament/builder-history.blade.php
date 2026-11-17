{{-- The publishing history: each kept version, and "restore to the draft". viewData: rows, live. --}}
@php $b = 'site::builder.history.'; @endphp
@if ($rows === [])
    <div class="lms-empty"><x-filament::icon icon="heroicon-o-clock" /><span>{{ __($b.'empty') }}</span></div>
@else
    <ol style="list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: .6rem">
        @foreach ($rows as $row)
            <li style="display: flex; align-items: center; gap: .75rem; padding: .75rem .9rem; border: 1px solid var(--gray-200); border-radius: .75rem">
                <div style="flex: 1; min-width: 0; display: flex; flex-direction: column; gap: .15rem">
                    <strong style="font-size: .9rem">{{ __($b.'row', ['revision' => $row['revision'], 'blocks' => $row['blocks']]) }}</strong>
                    <span style="font-size: .78rem; color: var(--gray-500)">{{ $row['when'] }}@if ($row['by']) · {{ __($b.'by', ['name' => $row['by']]) }}@endif</span>
                </div>
                @if ($live === $row['revision'])
                    <x-filament::badge color="success">{{ __($b.'current') }}</x-filament::badge>
                @endif
                <x-filament::button size="sm" color="gray" icon="heroicon-m-arrow-uturn-left" wire:click="restorePublish('{{ $row['id'] }}')" wire:confirm="{{ __($b.'restore') }}?">
                    {{ __($b.'restore') }}
                </x-filament::button>
            </li>
        @endforeach
    </ol>
@endif
