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

        {{-- The usage ledger of each meter: this month, the bought balance, overage, the hard limit, the latest lines. --}}
        @if ($metering = $this->metering)
            @include('platform::plans.usage-styles')
            <section class="pc-base pc-box">
                <h3><span>{{ __('platform::metering.console.title') }}</span></h3>
                <div class="mu-cards">
                    @foreach ($metering as $m)
                        <div class="mu-card pc-base" data-state="{{ $m['state'] }}" wire:key="console-meter-{{ $m['key'] }}">
                            <header><h3>{{ $m['label'] }}</h3>@if ($m['hard'])<span class="pp-tag" data-tone="warn">{{ __('platform::metering.console.hard_on') }}</span>@endif</header>
                            <div class="mu-row"><span>{{ __('platform::metering.card.this_month') }}</span><span>{{ $m['allowance'] }}</span></div>
                            <div class="mu-row"><span>{{ __('platform::metering.card.bought') }}</span><span>{{ $m['bought'] }}</span></div>
                            <div class="mu-row"><span>{{ __('platform::metering.card.available') }}</span><span>{{ $m['available'] }}</span></div>
                            @if ($m['debt'])<p class="mu-debt">{{ $m['debt'] }}</p>@endif
                            <table class="mu-table">
                                <tbody>
                                    @forelse ($m['lines'] as $line)
                                        <tr><td><small>{{ $line['when'] }}</small></td><td>{{ $line['what'] }}</td><td class="mu-num" data-sign="{{ $line['sign'] }}" dir="ltr">{{ $line['amount'] }}</td><td class="mu-num" dir="ltr">{{ $line['after'] }}</td></tr>
                                    @empty
                                        <tr><td colspan="4"><small>{{ __('platform::metering.statement.empty') }}</small></td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endforeach
                </div>
            </section>
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
