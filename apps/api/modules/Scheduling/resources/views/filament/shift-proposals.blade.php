{{-- Teachers' shifts: proposals in tabs by status (what waits on the admins first) as cards, and every teacher's schedule. --}}
@php
    use Filament\Support\Icons\Heroicon;

    $l = 'scheduling::review.';
    $arrow = __('scheduling::shifts.arrow');
    $emptyIcons = [
        'review' => Heroicon::OutlinedCheckCircle,
        'teacher' => Heroicon::OutlinedChatBubbleLeftRight,
        'approved' => Heroicon::OutlinedCheckBadge,
        'closed' => Heroicon::OutlinedArchiveBox,
        'teachers' => Heroicon::OutlinedUserGroup,
    ];
@endphp

<x-filament-panels::page>
    @include('scheduling::filament.shift-styles')

    <x-filament::tabs class="sp-tabs">
        @foreach ($this->tabs as $t)
            <x-filament::tabs.item :active="$tab === $t['key']" wire:click="$set('tab', '{{ $t['key'] }}')" data-tab="{{ $t['key'] }}"
                :badge="$t['count'] ?: null" :badge-color="$t['key'] === 'review' ? 'warning' : 'gray'">
                {{ $t['key'] === 'teachers' ? __($l.'tabs.teachers') : __($l.'tab_groups.'.$t['key']) }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    @if ($tab !== 'teachers')
        @php $rows = $this->groups[$tab] ?? []; @endphp
        <section class="sp" data-group="{{ $tab }}" wire:key="sp-tab-{{ $tab }}">
            <p class="sp-hint">{{ __($l.'group_hints.'.$tab) }}</p>
            @if ($rows === [])
                <div class="sp-empty">
                    <x-filament::icon :icon="$emptyIcons[$tab]" class="sp-empty-icon" />
                    <p>{{ __($l.'empty.'.$tab) }}</p>
                </div>
            @else
                <ul class="sp-grid lms-stagger">
                    @foreach ($rows as $row)
                        @php $open = in_array($tab, ['review', 'teacher'], true); @endphp
                        <li wire:key="sp-{{ $row['id'] }}">
                            <button type="button" class="sp-card lms-lift" data-overdue="{{ $row['overdue'] ? 'true' : 'false' }}"
                                wire:click="mountAction('open', { proposal: '{{ $row['id'] }}' })">
                                <span class="sp-card-head">
                                    <span class="sp-avatar" aria-hidden="true">{{ $row['initial'] }}</span>
                                    <span class="sp-who">
                                        <strong>{{ $row['teacher'] }}</strong>
                                        <small>{{ __($l.'zone', ['zone' => $row['zone']]) }} · {{ __($l.'sent', ['ago' => $row['sent']]) }}</small>
                                    </span>
                                    <span class="sp-tags">
                                        @if ($row['overdue'])<span class="sp-late">{{ __($l.'overdue') }}</span>@endif
                                        <span class="sp-pill" data-status="{{ $row['status'] }}">{{ $row['status_label'] }}</span>
                                    </span>
                                </span>

                                <span class="sp-hours" aria-label="{{ $row['summary']['hours'] }}">
                                    <span class="sp-h-before">{{ $row['hours_before'] }}</span>
                                    <span class="sp-h-arrow" aria-hidden="true">{{ $arrow }}</span>
                                    <span class="sp-h-after">{{ $row['hours_after'] }}</span>
                                    <small>{{ __($l.'hours_a_week') }}</small>
                                    @if ($row['delta'] !== 0)
                                        <span class="sp-delta" dir="ltr" data-sign="{{ $row['delta'] > 0 ? 'up' : 'down' }}">{{ $row['delta'] > 0 ? '+' : '−' }}{{ $row['delta_hours'] }}</span>
                                    @else
                                        <span class="sp-delta" data-sign="same">{{ __($l.'same_hours') }}</span>
                                    @endif
                                </span>

                                @include('scheduling::filament.shift-strip', ['days' => $row['days7']])

                                <span class="sp-card-foot">
                                    <span class="sp-from">
                                        <x-filament::icon :icon="Heroicon::OutlinedCalendar" class="sp-ico" />
                                        {{ __($l.'starts_on', ['date' => $row['from']]) }}
                                    </span>
                                    @if ($row['conflicts'] > 0)
                                        <span class="sp-conf" data-open="{{ $row['unresolved'] > 0 ? 'true' : 'false' }}">
                                            <x-filament::icon :icon="$row['unresolved'] > 0 ? Heroicon::OutlinedExclamationTriangle : Heroicon::OutlinedCheckCircle" class="sp-ico" />
                                            {{ trans_choice($l.'conflicts_count', $row['conflicts'], ['count' => $row['conflicts']]) }}
                                            · {{ $row['unresolved'] > 0 ? __($l.'unresolved_short', ['count' => $row['unresolved']]) : __($l.'all_resolved') }}
                                        </span>
                                    @elseif ($open)
                                        <span class="sp-conf" data-open="false">
                                            <x-filament::icon :icon="Heroicon::OutlinedCheckCircle" class="sp-ico" />
                                            {{ __($l.'no_conflicts') }}
                                        </span>
                                    @endif
                                    <span class="sp-cta">{{ __($l.'review_cta') }} <span aria-hidden="true">{{ $arrow }}</span></span>
                                </span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @else
        <section class="sp" data-group="teachers" wire:key="sp-tab-teachers">
            <p class="sp-hint">{{ __($l.'teachers_hint') }}</p>
            @if ($this->teachers === [])
                <div class="sp-empty">
                    <x-filament::icon :icon="$emptyIcons['teachers']" class="sp-empty-icon" />
                    <p>{{ __($l.'no_teachers') }}</p>
                </div>
            @else
                <ul class="sp-grid lms-stagger">
                    @foreach ($this->teachers as $t)
                        <li wire:key="st-{{ $t['id'] }}">
                            <button type="button" class="sp-card lms-lift" wire:click="mountAction('teacher', { teacher: '{{ $t['id'] }}' })">
                                <span class="sp-card-head">
                                    <span class="sp-avatar" aria-hidden="true">{{ $t['initial'] }}</span>
                                    <span class="sp-who">
                                        <strong>{{ $t['name'] }}</strong>
                                        <small>{{ __($l.'zone', ['zone' => $t['zone']]) }}</small>
                                    </span>
                                    @if ($t['open'])
                                        <span class="sp-tags"><span class="sp-pill" data-status="{{ $t['open']['status'] }}">{{ $t['open']['status_label'] }}</span></span>
                                    @endif
                                </span>

                                <span class="sp-hours">
                                    @if ($t['current_hours'] !== null)
                                        <span class="sp-h-after">{{ $t['current_hours'] }}</span>
                                        <small>{{ __($l.'hours_a_week') }}</small>
                                    @else
                                        <span class="sp-h-none">{{ __($l.'no_schedule') }}</span>
                                    @endif
                                </span>

                                @include('scheduling::filament.shift-strip', ['days' => $t['days7']])

                                <span class="sp-card-foot">
                                    @if ($t['upcoming'])
                                        <span class="sp-from" data-kind="upcoming">
                                            <x-filament::icon :icon="Heroicon::OutlinedCalendar" class="sp-ico" />
                                            {{ __($l.'upcoming_from', ['date' => $t['upcoming']['from'], 'hours' => $t['upcoming']['hours']]) }}
                                        </span>
                                    @endif
                                    <span class="sp-cta">{{ __($l.'view_schedule') }} <span aria-hidden="true">{{ $arrow }}</span></span>
                                </span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif
</x-filament-panels::page>
