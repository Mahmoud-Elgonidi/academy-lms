{{-- Teachers' shifts: proposals grouped by status (what waits on the admins first), and every teacher's schedule. --}}
@php $l = 'scheduling::review.'; @endphp

<x-filament-panels::page>
    @include('scheduling::filament.shift-styles')

    <x-filament::tabs>
        @foreach (['proposals', 'teachers'] as $t)
            <x-filament::tabs.item :active="$tab === $t" wire:click="$set('tab', '{{ $t }}')"
                :badge="$t === 'proposals' ? (count($this->groups['review']) ?: null) : null">
                {{ __($l.'tabs.'.$t) }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    @if ($tab === 'proposals')
        @php $groups = $this->groups; @endphp
        <div class="sp">
            @foreach (\Modules\Scheduling\Filament\ShiftProposalView::GROUPS as $group)
                @continue(in_array($group, ['approved', 'closed'], true) && $groups[$group] === [])
                <section class="sp-group" data-group="{{ $group }}" wire:key="sp-group-{{ $group }}">
                    <header class="sp-group-head">
                        <h2 class="lms-heading">{{ __($l.'groups.'.$group) }} <span class="sp-count">{{ count($groups[$group]) }}</span></h2>
                        <p>{{ __($l.'group_hints.'.$group) }}</p>
                    </header>
                    @if ($groups[$group] === [])
                        <div class="sp-empty">{{ __($l.'empty.'.$group) }}</div>
                    @else
                        <ul class="sp-list lms-stagger">
                            @foreach ($groups[$group] as $row)
                                <li wire:key="sp-{{ $row['id'] }}">
                                    <button type="button" class="sp-item lms-lift" data-overdue="{{ $row['overdue'] ? 'true' : 'false' }}"
                                        wire:click="mountAction('open', { proposal: '{{ $row['id'] }}' })">
                                        <span class="sp-who">
                                            <strong>{{ $row['teacher'] }}</strong>
                                            <small>{{ __($l.'sent', ['ago' => $row['sent']]) }}@if ($row['overdue']) · <b class="sp-late">{{ __($l.'overdue') }}</b>@endif</small>
                                        </span>
                                        <span class="sp-when">
                                            <small>{{ __($l.'starts') }}</small>
                                            {{ $row['from'] }}
                                        </span>
                                        <span class="sp-change">
                                            <span class="sp-hours">{{ $row['summary']['hours'] }}</span>
                                            <small>
                                                @forelse ($row['summary']['days'] as $d)
                                                    <span class="sp-days" data-kind="{{ $d['kind'] }}">{{ $d['text'] }}</span>
                                                @empty
                                                    <span class="sp-days">{{ __($l.'summary.same_days') }}</span>
                                                @endforelse
                                            </small>
                                        </span>
                                        <span class="sp-conf">
                                            @if ($row['conflicts'] > 0)
                                                <span class="sp-conf-n" data-open="{{ $row['unresolved'] > 0 ? 'true' : 'false' }}">{{ trans_choice($l.'conflicts_count', $row['conflicts'], ['count' => $row['conflicts']]) }}</span>
                                                <small>{{ $row['unresolved'] > 0 ? __($l.'unresolved_count', ['count' => $row['unresolved']]) : __($l.'all_resolved') }}</small>
                                            @elseif (in_array($group, ['review', 'teacher'], true))
                                                <span class="sp-ok">{{ __($l.'no_conflicts') }}</span>
                                            @endif
                                        </span>
                                        <span class="sp-pill" data-status="{{ $row['status'] }}">{{ $row['status_label'] }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endforeach
        </div>
    @else
        <section class="sp-group">
            <header class="sp-group-head"><p>{{ __($l.'teachers_hint') }}</p></header>
            @if ($this->teachers === [])
                <div class="sp-empty">{{ __($l.'no_teachers') }}</div>
            @else
                <ul class="sp-list lms-stagger">
                    @foreach ($this->teachers as $t)
                        <li wire:key="st-{{ $t['id'] }}">
                            <button type="button" class="sp-item sp-item-teacher lms-lift" wire:click="mountAction('teacher', { teacher: '{{ $t['id'] }}' })">
                                <span class="sp-who"><strong>{{ $t['name'] }}</strong></span>
                                <span class="sp-when">
                                    <small>{{ __($l.'now') }}</small>
                                    {{ $t['current_hours'] !== null ? __($l.'hours_week', ['hours' => $t['current_hours']]) : __($l.'no_schedule') }}
                                </span>
                                <span class="sp-change">
                                    @if ($t['upcoming'])
                                        <span class="sp-days" data-kind="added">{{ __($l.'upcoming_from', ['date' => $t['upcoming']['from'], 'hours' => $t['upcoming']['hours']]) }}</span>
                                    @endif
                                </span>
                                <span class="sp-conf">
                                    @if ($t['open'])<span class="sp-pill" data-status="{{ $t['open']['status'] }}">{{ $t['open']['status_label'] }}</span>@endif
                                </span>
                                <span class="sp-go">{{ __($l.'view_schedule') }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif
</x-filament-panels::page>
