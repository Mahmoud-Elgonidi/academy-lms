@php
    use Modules\Scheduling\Filament\GroupView;

    $g = $this->card;
    $members = $this->members;
    $live = array_values(array_filter($members, fn ($m) => $m['live']));
    $past = array_values(array_filter($members, fn ($m) => ! $m['live']));
    $upcoming = $this->upcoming;
    $w = 'scheduling::group_screens.details.';
@endphp

<x-filament-panels::page>
    @include('scheduling::filament.groups.styles')
    <style>
        .grd { display: flex; flex-direction: column; gap: 1rem; }
        .grd-hero { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 1rem 1.5rem; align-items: center; }
        .grd-hero .gr-dots { max-width: 14rem; gap: .3rem; }
        .grd-hero .gr-dots i { width: .9rem; height: .9rem; }
        .grd-hero .gr-seats-n { font-size: 1.1rem; }
        .grd-tags { display: flex; flex-wrap: wrap; gap: .4rem; align-items: center; }
        .grd-hero-seats { display: flex; flex-direction: column; align-items: flex-end; gap: .3rem; }
        .grd-figs { display: flex; flex-wrap: wrap; gap: 1.5rem; }
        .grd-figs div { display: flex; flex-direction: column; }
        .grd-figs small { font-size: .72rem; color: var(--gray-500); }
        .grd-figs strong { font-size: 1rem; font-variant-numeric: tabular-nums; }
        .grd-cols { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr); gap: 1rem; align-items: start; }
        .grd-box { background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-xl); padding: 1rem 1.1rem; display: flex; flex-direction: column; gap: .75rem; min-width: 0; }
        .grd-box h2 { margin: 0; font-size: 1rem; font-weight: 700; display: flex; align-items: center; gap: .5rem; }
        .grd-box h2 small { font-weight: 600; font-size: .78rem; color: var(--gray-500); }
        .grd-members { display: flex; flex-direction: column; }
        .grd-member { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; gap: .75rem; align-items: center; padding: .6rem 0; border-top: 1px solid var(--gray-100); }
        .grd-member:first-child { border-top: 0; }
        .grd-member[data-past] { opacity: .6; }
        .grd-member strong { font-size: .9rem; }
        .grd-member .gr-muted { font-size: .75rem; }
        .grd-came { text-align: center; }
        .grd-came strong { display: block; font-size: 1.05rem; font-variant-numeric: tabular-nums; }
        .grd-came small { font-size: .68rem; color: var(--gray-500); }
        .grd-empty-seat { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: .75rem; align-items: center; padding: .6rem 0; border-top: 1px dashed var(--gray-200); color: var(--gray-500); font-size: .8125rem; }
        .grd-empty-seat span:first-child { width: 2.3rem; height: 2.3rem; border-radius: 50%; border: 1.5px dashed var(--info-300); display: grid; place-items: center; color: var(--info-600); }
        .grd-lessons { display: flex; flex-direction: column; gap: .4rem; margin: 0; padding: 0; list-style: none; }
        .grd-lessons li { display: flex; justify-content: space-between; gap: .5rem; font-size: .84rem; padding: .5rem .65rem; border-radius: var(--radius-lg); background: var(--gray-50); font-variant-numeric: tabular-nums; }
        .grd-ended { display: flex; gap: .6rem; align-items: center; padding: .75rem 1rem; border-radius: var(--radius-xl); background: var(--gray-100); color: var(--gray-700); font-size: .875rem; }
        @media (max-width: 900px) { .grd-cols { grid-template-columns: minmax(0, 1fr); } }
        @media (max-width: 639px) {
            .grd-hero { grid-template-columns: minmax(0, 1fr); }
            .grd-hero-seats { align-items: flex-start; }
        }
        .dark .grd-box { background: var(--gray-900); border-color: var(--gray-800); }
        .dark .grd-member { border-top-color: var(--gray-800); }
        .dark .grd-empty-seat { border-top-color: var(--gray-800); }
        .dark .grd-lessons li { background: var(--gray-800); }
        .dark .grd-ended { background: var(--gray-800); color: var(--gray-300); }
    </style>

    <div class="grd lms-stagger" data-live-channel="{{ $this->liveChannel() }}">
        {{-- The group at a glance. --}}
        <section class="gr-card grd-hero" data-status="{{ $g['status'] }}" aria-label="{{ __($w.'summary') }}">
            <div style="display: flex; flex-direction: column; gap: .65rem; min-width: 0">
                <div class="grd-tags">
                    @include('scheduling::filament.groups.badge')
                    <span class="gr-pill" data-tone="{{ GroupView::tone($g['status']) }}">{{ GroupView::status($g['status']) }}</span>
                    @if ($g['age'])<span class="gr-muted">{{ $g['age'] }}</span>@endif
                    @if ($g['level'])<span class="gr-muted">· {{ $g['level'] }}</span>@endif
                </div>
                <div class="gr-times" style="font-size: 1rem"><x-filament::icon icon="heroicon-m-clock" /><span>{{ GroupView::timesLine($g['times']) }}</span></div>
                <div class="grd-figs">
                    <div><small>{{ __($w.'figs.shape') }}</small><strong>{{ GroupView::shape($g['minutes'], $g['per_week']) }}</strong></div>
                    <div><small>{{ __($w.'figs.held') }}</small><strong data-count="{{ $g['held'] }}">{{ $g['held'] }}</strong></div>
                    <div><small>{{ __($w.'figs.next') }}</small><strong>{{ $g['next']['label'] ?? '—' }}</strong></div>
                    @if ($g['started_on'])
                        <div><small>{{ __($w.'figs.started') }}</small><strong>{{ $g['started_on'] }}</strong></div>
                    @endif
                </div>
            </div>
            <div class="grd-hero-seats">
                @include('scheduling::filament.groups.seats', ['taken' => $g['taken'], 'capacity' => $g['capacity']])
                <span class="gr-muted">{{ $g['free'] > 0 ? trans_choice('scheduling::group_screens.card.free', $g['free'], ['n' => $g['free']]) : __('scheduling::group_screens.card.full') }}</span>
            </div>
        </section>

        @if ($g['status'] === 'ended')
            <div class="grd-ended" role="status"><x-filament::icon icon="heroicon-m-stop-circle" style="width: 1.2rem; height: 1.2rem" />{{ __($w.'ended', ['date' => $g['ended_on']]) }}</div>
        @elseif ($g['status'] === 'forming')
            <div class="grd-ended" role="status" style="background: var(--warning-50); color: var(--warning-800)">
                <x-filament::icon icon="heroicon-m-clock" style="width: 1.2rem; height: 1.2rem" />
                {{ $g['min_to_start'] && $g['min_to_start'] > $g['taken']
                    ? trans_choice($w.'forming_waits', $g['min_to_start'] - $g['taken'], ['n' => $g['min_to_start'] - $g['taken']])
                    : __($w.'forming_first') }}
            </div>
        @endif

        <div class="grd-cols">
            {{-- Who is in it. --}}
            <section class="grd-box" aria-labelledby="grd-members">
                <h2 id="grd-members">{{ __($w.'members.title') }} <small>{{ GroupView::seatsLabel($g['taken'], $g['capacity']) }}</small></h2>
                <p class="gr-muted">{{ __($w.'members.privacy') }}</p>
                <div class="grd-members lms-stagger">
                    @forelse ($live as $m)
                        <div class="grd-member" wire:key="m-{{ $m['id'] }}">
                            <span class="gr-avatar" aria-hidden="true">{{ $m['initial'] }}</span>
                            <div style="min-width: 0">
                                <strong>{{ $m['name'] }}</strong>
                                <p class="gr-muted">{{ __($w.'members.joined', ['date' => $m['joined']]) }}@if ($m['left']) · {{ __($w.'members.leaves', ['date' => $m['left']]) }}@endif</p>
                            </div>
                            <div class="grd-came"><strong>{{ $m['attended'] }}</strong><small>{{ __($w.'members.attended') }}</small></div>
                        </div>
                    @empty
                        <p class="gr-muted">{{ __($w.'members.none') }}</p>
                    @endforelse
                    @if ($g['status'] !== 'ended')
                        @for ($i = 1; $i <= min(3, $g['free']); $i++)
                            <div class="grd-empty-seat" wire:key="free-{{ $i }}"><span><x-filament::icon icon="heroicon-m-plus" style="width: 1rem; height: 1rem" /></span><span>{{ __($w.'members.free_seat') }}</span></div>
                        @endfor
                    @endif
                    @foreach ($past as $m)
                        <div class="grd-member" data-past wire:key="p-{{ $m['id'] }}">
                            <span class="gr-avatar" aria-hidden="true">{{ $m['initial'] }}</span>
                            <div style="min-width: 0">
                                <strong>{{ $m['name'] }}</strong>
                                <p class="gr-muted">{{ __($w.'members.was', ['from' => $m['joined'], 'to' => $m['left']]) }}</p>
                            </div>
                            <div class="grd-came"><strong>{{ $m['attended'] }}</strong><small>{{ __($w.'members.attended') }}</small></div>
                        </div>
                    @endforeach
                </div>
            </section>

            <div style="display: flex; flex-direction: column; gap: 1rem; min-width: 0">
                {{-- Seats. --}}
                <section class="grd-box" aria-labelledby="grd-seats">
                    <h2 id="grd-seats">{{ __($w.'seats.title') }}</h2>
                    <form wire:submit="saveSeats" style="display: flex; flex-direction: column; gap: .75rem">
                        {{ $this->seatsForm }}
                        @if ($this->canManage())
                            <div><x-filament::button type="submit" size="sm" icon="heroicon-m-check">{{ __($w.'seats.save') }}</x-filament::button></div>
                        @endif
                    </form>
                </section>

                {{-- Next lessons. --}}
                <section class="grd-box" aria-labelledby="grd-next">
                    <h2 id="grd-next">{{ __($w.'next.title') }}</h2>
                    @if ($upcoming === [])
                        <p class="gr-muted">{{ $g['status'] === 'forming' ? __($w.'next.forming') : __($w.'next.none') }}</p>
                    @else
                        <ul class="grd-lessons">
                            @foreach ($upcoming as $lesson)
                                <li wire:key="l-{{ $lesson['id'] }}"><span>{{ $lesson['at']['label'] }}</span><span class="gr-muted">{{ trans_choice($w.'next.seated', $lesson['seated'], ['n' => $lesson['seated']]) }}</span></li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </div>
        </div>
    </div>
</x-filament-panels::page>

@script
<script>
    const el = $wire.$el.querySelector('.grd');
    if (el && window.LmsLive) {
        window.LmsLive.watch({ el, channel: el.dataset.liveChannel, event: '.changed', refresh: () => $wire.$refresh(), every: 120000 });
    }
</script>
@endscript
