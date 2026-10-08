{{-- Sign in with Google or Apple, under the panel's own sign-in form. --}}
@php /** @var list<\Modules\Identity\Social\Provider> $providers */ @endphp
<div class="lms-social">
    <div class="lms-social-or" role="presentation"><span>{{ __('identity::social.sign_in.or') }}</span></div>
    <div class="lms-social-buttons">
        @foreach ($providers as $provider)
            <x-filament::button
                tag="a"
                :href="route('identity.social.start', ['provider' => $provider->value, 'panel' => $panel])"
                :spa-mode="false"
                color="gray"
                outlined
                class="w-full"
                data-provider="{{ $provider->value }}"
            >
                {{ __('identity::social.sign_in.with', ['provider' => $provider->label()]) }}
            </x-filament::button>
        @endforeach
    </div>
</div>
<style>
    .lms-social { display: grid; gap: 0.75rem; }
    .lms-social-or { display: flex; align-items: center; gap: 0.75rem; color: var(--gray-500); font-size: 0.8125rem; }
    .lms-social-or::before, .lms-social-or::after { content: ''; flex: 1; border-top: 1px solid var(--gray-200); }
    .dark .lms-social-or::before, .dark .lms-social-or::after { border-color: var(--gray-700); }
    .lms-social-buttons { display: grid; gap: 0.5rem; }
</style>
