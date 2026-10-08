@php
    $l = 'platform::console.overview.';
    $record = $this->record; $u = $this->usage; $managers = $this->managers; $activity = $this->activity; $hosts = $this->hosts;
    $active = $record->status === \Modules\Platform\Enums\AcademyStatus::Active;
@endphp
<x-filament-panels::page>
    @include('platform::console.styles')
    @include('platform::plans.styles')
    <div class="pc-stack">
        @unless ($active)
            <div class="pc-note">{{ __($l.'suspended_note') }}</div>
        @endunless

        <div class="pc-tiles lms-stagger">
            @foreach (['teachers', 'students', 'guardians', 'staff', 'enrollments', 'lessons'] as $key)
                <div class="pc-base pc-tile" @if ($key === 'students') data-main @endif><small>{{ __($l.'tiles.'.$key) }}</small><strong data-count="{{ $u[$key] }}">{{ $u[$key] }}</strong></div>
            @endforeach
            <div class="pc-base pc-tile"><small>{{ __($l.'tiles.hours') }}</small><strong>{{ $u['hours'] }}</strong></div>
            <div class="pc-base pc-tile">
                <small>{{ __($l.'tiles.received') }}</small>
                <span class="pc-lines">@forelse ($u['money'] as $m)<strong>{{ $m }}</strong>@empty<strong>—</strong>@endforelse</span>
            </div>
        </div>

        @if ($plan = $this->plan)
            @include('platform::plans.summary', ['plan' => $plan, 'console' => true])
        @endif

        <div class="pc-two">
            <section class="pc-base pc-box">
                <h3>
                    {{ __($l.'activity.title') }}
                    <a href="{{ \Modules\Platform\Filament\Console\Pages\AuditLog::getUrl(['academy' => $record->id]) }}" wire:navigate>{{ __($l.'activity.all') }}</a>
                </h3>
                @include('shared::audit.list', ['entries' => $activity, 'empty' => __($l.'activity.empty')])
            </section>

            <div class="pc-side">
                <section class="pc-base pc-box">
                    <h3>{{ __($l.'owners.title') }}</h3>
                    @forelse ($managers as $person)
                        <div class="pc-person" wire:key="m-{{ $person['id'] }}">
                            <div>
                                <strong>{{ $person['name'] }}</strong>
                                @if ($person['email'])<small>{{ $person['email'] }}</small>@endif
                                @if ($person['phone'])<small>{{ $person['phone'] }}</small>@endif
                            </div>
                            @php $role = in_array('owner', $person['roles'], true) ? 'owner' : 'admin'; @endphp
                            <span class="pc-role" data-role="{{ $role }}">{{ __($l.'owners.roles.'.$role) }}</span>
                        </div>
                    @empty
                        <p class="lms-audit-empty">{{ __($l.'owners.empty') }}</p>
                    @endforelse
                </section>

                <section class="pc-base pc-box">
                    <h3>{{ __($l.'details.title') }}</h3>
                    <dl class="pc-dl">
                        <dt>{{ __($l.'details.hosts') }}</dt>
                        <dd>@foreach ($hosts as $h)<span>{{ $h['host'] }}@unless ($h['verified']) <small>({{ __($l.'details.unverified') }})</small>@endunless</span>@endforeach</dd>
                        <dt>{{ __($l.'details.currency') }}</dt><dd>{{ $record->base_currency }}</dd>
                        <dt>{{ __($l.'details.timezone') }}</dt><dd><span>{{ $record->default_timezone }}</span></dd>
                        <dt>{{ __($l.'details.locale') }}</dt><dd>{{ __($l.'locales.'.$record->default_locale) }}</dd>
                        @if ($record->country)<dt>{{ __($l.'details.country') }}</dt><dd>{{ $record->country }}</dd>@endif
                    </dl>
                </section>
            </div>
        </div>
    </div>
</x-filament-panels::page>
