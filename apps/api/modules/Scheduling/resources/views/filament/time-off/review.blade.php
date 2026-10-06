@php $l = 'scheduling::timeoff.review.'; $d = $this->details; $lessons = $this->lessons; @endphp
<x-filament-panels::page>
    @include('scheduling::filament.time-off.styles')
    <style>
        .tr-head { display: flex; flex-wrap: wrap; gap: .5rem 1.5rem; align-items: flex-start; justify-content: space-between; padding: 1rem 1.1rem; border-radius: var(--radius-xl, .75rem); background: white; border: 1px solid var(--gray-200); }
        .tr-head > div { display: flex; flex-direction: column; gap: .2rem; }
        .tr-period { font-size: 1.05rem; font-weight: 700; }
        .tr-row { display: grid; grid-template-columns: 11rem minmax(0, 1fr) minmax(0, 1.6fr); gap: .5rem 1rem; align-items: center; padding: .8rem 1rem; background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl, .75rem); }
        .tr-row[data-state="needs_cover"] { border-inline-start: 4px solid var(--danger-500); }
        .tr-row[data-state="covered"] { border-inline-start: 4px solid var(--primary-500); }
        .tr-row[data-state="cancelled"], .tr-row[data-state="done"] { opacity: .7; }
        .tr-row > div { display: flex; flex-direction: column; gap: .15rem; min-width: 0; }
        .tr-act { flex-direction: row !important; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: .5rem !important; }
        .tr-act .fi-input-wrp { flex: 1 1 14rem; min-width: 12rem; }
        @media (max-width: 860px) { .tr-row { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); } .tr-act { grid-column: 1 / -1; justify-content: flex-start; } }
        .dark .tr-head, .dark .tr-row { background: var(--gray-900); border-color: var(--gray-800); }
    </style>

    <div class="tr-head">
        <div>
            <span class="tr-period">{{ $d['period'] }}</span>
            <small class="to-muted">{{ __($l.'teacher_zone', ['zone' => $d['zone']]) }}</small>
            <small class="to-muted">{{ $d['reason'] }}@if ($d['byAcademy']) · {{ __($l.'requested_by_academy') }}@endif</small>
            @if ($d['note'])<div class="to-note">{{ $d['note'] }}</div>@endif
            @if ($d['decision'])<div class="to-note"><strong>{{ __('scheduling::timeoff.mine.decision_note') }}:</strong> {{ $d['decision'] }}</div>@endif
        </div>
        <span class="to-pill" data-s="{{ $d['status'] }}">{{ $d['statusLabel'] }}</span>
    </div>

    <h2 class="to-h2 lms-heading">{{ __($l.'lessons') }} <small>{{ count($lessons) }}</small></h2>
    @if ($d['status'] === 'pending' && $lessons !== [])
        <div class="to-banner"><x-filament::icon icon="heroicon-o-information-circle" />{{ __($l.'wait_approval') }}</div>
    @endif

    <div class="to-list lms-stagger">
        @forelse ($lessons as $r)
            <div class="tr-row" data-state="{{ $r['state'] }}" wire:key="tr-{{ $r['id'] }}-{{ $r['state'] }}">
                <div>
                    <strong>{{ $r['day'] }}</strong>
                    <span class="fl-time" style="font-variant-numeric: tabular-nums; color: var(--gray-600)">{{ $r['time'] }}</span>
                </div>
                <div>
                    <strong>{{ $r['student'] }}</strong>
                    <small class="to-muted">{{ $r['subject'] }}</small>
                </div>
                <div class="tr-act">
                    @if ($r['state'] === 'needs_cover' && $r['future'] && $d['approved'])
                        @if ($r['candidates'])
                            <x-filament::input.wrapper>
                                <x-filament::input.select wire:model="pick.{{ $r['id'] }}">
                                    <option value="">{{ __($l.'pick') }}</option>
                                    @foreach ($r['candidates'] as $c)
                                        <option value="{{ $c['id'] }}">{{ $c['name'] }} · {{ __($l.'local', ['time' => $c['local']]) }}@if ($c['taught'] > 0) · {{ __($l.'taught', ['count' => $c['taught']]) }}@endif</option>
                                    @endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                            <x-filament::button size="sm" wire:click="assign('{{ $r['id'] }}')" wire:loading.attr="disabled">{{ __($l.'assign') }}</x-filament::button>
                        @else
                            <small class="to-left">{{ __($l.'no_candidates') }}</small>
                        @endif
                        <x-filament::button size="sm" color="gray" wire:click="makeup('{{ $r['id'] }}')" wire:confirm="{{ __($l.'makeup_confirm') }}">{{ __($l.'makeup') }}</x-filament::button>
                    @else
                        <span class="to-pill" data-s="{{ $r['state'] }}">{{ $r['stateLabel'] }}</span>
                        @if ($r['why'])<small class="to-muted">{{ $r['why'] }}</small>@endif
                    @endif
                </div>
            </div>
        @empty
            <div class="to-empty">{{ __($l.'no_lessons') }}</div>
        @endforelse
    </div>
</x-filament-panels::page>
