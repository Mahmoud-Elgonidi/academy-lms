{{-- My settings: each provider, whether this account is linked to it, and the way to link or unlink. --}}
@php /** @var list<array{provider: string, label: string, linked: bool, email: ?string, last_used: ?string}> $rows */ @endphp
<ul class="lms-social-linked">
    @foreach ($rows as $row)
        <li data-provider="{{ $row['provider'] }}">
            <div class="lms-social-who">
                <strong>{{ $row['label'] }}</strong>
                <span>
                    @if ($row['linked'])
                        {{ $row['email'] ? __('identity::social.settings.linked_as', ['email' => $row['email']]) : __('identity::social.settings.linked') }}
                        @if ($row['last_used'])
                            · {{ __('identity::social.settings.last_used', ['date' => $row['last_used']]) }}
                        @endif
                    @else
                        {{ __('identity::social.settings.not_linked') }}
                    @endif
                </span>
            </div>
            @if ($row['linked'])
                {{ ($this->unlinkSocialAction)(['provider' => $row['provider']]) }}
            @else
                <x-filament::button
                    tag="a"
                    size="sm"
                    color="gray"
                    outlined
                    :href="route('identity.social.start', ['provider' => $row['provider'], 'panel' => $panel, 'link' => 1])"
                    :spa-mode="false"
                >
                    {{ __('identity::social.settings.link', ['provider' => $row['label']]) }}
                </x-filament::button>
            @endif
        </li>
    @endforeach
</ul>
<style>
    .lms-social-linked { display: grid; gap: 0.75rem; }
    .lms-social-linked li { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap; }
    .lms-social-who { display: grid; min-width: 0; }
    .lms-social-who span { color: var(--gray-500); font-size: 0.875rem; overflow-wrap: anywhere; }
</style>
