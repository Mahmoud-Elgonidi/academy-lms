@php $l = 'crm::trials.'; $groups = $this->groups; @endphp
<x-filament-panels::page>
    @include('crm::filament.styles')

    @foreach (['to_assess', 'upcoming', 'done'] as $group)
        <section>
            <h2 class="lms-heading" style="font-weight: 700; margin: .75rem 0 .5rem">{{ __($l.'groups.'.$group) }} <small style="color: var(--gray-500); font-weight: 500">{{ count($groups[$group]) }}</small></h2>
            <div class="crm-cards lms-stagger">
                @forelse ($groups[$group] as $t)
                    <article class="crm-card-base crm-trial" wire:key="tr-{{ $t['id'] }}">
                        <header>
                            <strong>{{ $t['name'] }}@if ($t['age']) <small>· {{ $t['age'] }}</small>@endif</strong>
                            @if ($group !== 'upcoming')
                                {{ ($this->assessAction)(['trial' => $t['id'], 'name' => $t['name'], 'again' => $group === 'done']) }}
                            @endif
                        </header>
                        <small>{{ collect([$t['subject'], $t['when']])->filter()->implode(' · ') }}</small>
                        @if ($t['notes'])<small>{{ __($l.'notes_from_sales', ['notes' => $t['notes']]) }}</small>@endif
                        @if ($group === 'done')
                            <div class="crm-assess">
                                <div><small>{{ __($l.'fields.level') }}</small><strong>{{ $t['level'] }}</strong></div>
                                @if ($t['plan'])<div><small>{{ __($l.'fields.plan_id') }}</small><strong>{{ $t['plan'] }}</strong></div>@endif
                                @if ($t['perWeek'])<div><small>{{ __($l.'fields.per_week') }}</small><strong>{{ $t['perWeek'] }}</strong></div>@endif
                                @if ($t['assessment'])<p>{{ $t['assessment'] }}</p>@endif
                            </div>
                        @endif
                    </article>
                @empty
                    <div class="crm-empty crm-card-base">{{ __($l.'empty.'.$group) }}</div>
                @endforelse
            </div>
        </section>
    @endforeach
</x-filament-panels::page>
