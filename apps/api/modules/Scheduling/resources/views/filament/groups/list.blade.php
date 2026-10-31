@php
    use Modules\Scheduling\Filament\GroupView;
    use Modules\Scheduling\Filament\Pages\FindTeacher;
    use Modules\Scheduling\Filament\Pages\GroupDetails;

    $board = $this->board;
    $counts = $board['counts'];
    $groups = $board['groups'];
    $w = 'scheduling::group_screens.list.';
    $none = $counts['running'] + $counts['forming'] + $counts['ended'] === 0;
    $canCreate = \Illuminate\Support\Facades\Gate::allows(\Modules\SharedKernel\Authorization\Ability::FindTeachers->value);
@endphp

<x-filament-panels::page>
    @include('scheduling::filament.groups.styles')
    <style>
        .grl { display: flex; flex-direction: column; gap: 1.25rem; }
        .grl-stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .75rem; }
        .grl-stat { display: flex; align-items: center; gap: .8rem; padding: .9rem 1rem; border-radius: var(--radius-xl); background: white; border: 1px solid var(--gray-200); }
        .grl-stat-ico { width: 2.5rem; height: 2.5rem; border-radius: .75rem; display: grid; place-items: center; flex-shrink: 0; }
        .grl-stat-ico svg { width: 1.3rem; height: 1.3rem; }
        .grl-stat[data-k="running"] .grl-stat-ico { background: var(--success-50); color: var(--success-700); }
        .grl-stat[data-k="forming"] .grl-stat-ico { background: var(--warning-50); color: var(--warning-700); }
        .grl-stat[data-k="free"] .grl-stat-ico { background: var(--info-50); color: var(--info-700); }
        .grl-stat strong { display: block; font-size: 1.5rem; line-height: 1.1; font-weight: 800; font-variant-numeric: tabular-nums; }
        .grl-stat small { font-size: .78rem; color: var(--gray-500); }
        .grl-filters { display: flex; flex-wrap: wrap; align-items: flex-end; gap: .6rem; }
        .grl-filters label { display: flex; flex-direction: column; gap: .3rem; min-width: 11rem; flex: 1 1 11rem; max-width: 16rem; }
        .grl-filters label > span { font-size: .75rem; font-weight: 600; color: var(--gray-600); }
        .grl-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(19rem, 1fr)); gap: .9rem; }
        .grl-empty { display: flex; flex-direction: column; align-items: center; text-align: center; gap: .6rem; padding: 2.5rem 1.25rem; border-radius: var(--radius-xl);
            background: white; border: 1px dashed var(--gray-300); }
        .grl-empty-ico { width: 3.5rem; height: 3.5rem; border-radius: 1rem; display: grid; place-items: center; background: var(--info-50); color: var(--info-700); }
        .grl-empty h2 { margin: 0; font-size: 1.1rem; font-weight: 700; }
        .grl-points { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .75rem; max-width: 46rem; margin: .5rem 0; text-align: start; }
        .grl-points div { padding: .75rem .85rem; border-radius: var(--radius-lg); background: var(--gray-50); font-size: .8125rem; color: var(--gray-600); }
        .grl-points strong { display: block; color: var(--gray-900); margin-bottom: .15rem; }
        @media (max-width: 639px) {
            .grl-stats { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .5rem; }
            .grl-stat { flex-direction: column; align-items: flex-start; gap: .4rem; padding: .7rem; }
            .grl-stat-ico { width: 2rem; height: 2rem; }
            .grl-stat strong { font-size: 1.25rem; }
            .grl-filters label { max-width: none; }
            .grl-grid { grid-template-columns: minmax(0, 1fr); }
            .grl-points { grid-template-columns: minmax(0, 1fr); }
        }
        .dark .grl-stat, .dark .grl-empty { background: var(--gray-900); border-color: var(--gray-800); }
        .dark .grl-stat[data-k="running"] .grl-stat-ico { background: color-mix(in oklab, var(--success-500) 15%, transparent); color: var(--success-300); }
        .dark .grl-stat[data-k="forming"] .grl-stat-ico { background: color-mix(in oklab, var(--warning-500) 15%, transparent); color: var(--warning-300); }
        .dark .grl-stat[data-k="free"] .grl-stat-ico, .dark .grl-empty-ico { background: color-mix(in oklab, var(--info-500) 16%, transparent); color: var(--info-300); }
        .dark .grl-points div { background: var(--gray-800); color: var(--gray-300); }
        .dark .grl-points strong { color: white; }
        .dark .grl-filters label > span { color: var(--gray-300); }
    </style>

    <div class="grl" data-live-channel="{{ $this->liveChannel() }}">
        @if ($none)
            {{-- No group yet: what a group is, and the way to make one. --}}
            <section class="grl-empty" aria-labelledby="grl-empty-title">
                <span class="grl-empty-ico"><x-filament::icon icon="heroicon-o-user-group" style="width: 1.8rem; height: 1.8rem" /></span>
                <h2 id="grl-empty-title">{{ __($w.'empty.title') }}</h2>
                <p class="gr-muted" style="max-width: 38rem">{{ __($w.'empty.hint') }}</p>
                <div class="grl-points lms-stagger">
                    @foreach (['teacher', 'plan', 'seats'] as $point)
                        <div><strong>{{ __($w.'empty.points.'.$point.'.title') }}</strong>{{ __($w.'empty.points.'.$point.'.line') }}</div>
                    @endforeach
                </div>
                @if ($canCreate)
                    <x-filament::button tag="a" :href="FindTeacher::getUrl(['mode' => 'group'])" icon="heroicon-m-plus-circle">{{ __($w.'create') }}</x-filament::button>
                @endif
            </section>
        @else
            <div class="grl-stats lms-stagger" role="list">
                @foreach (['running' => 'heroicon-o-play-circle', 'forming' => 'heroicon-o-clock', 'free' => 'heroicon-o-user-plus'] as $k => $icon)
                    <div class="grl-stat" data-k="{{ $k }}" role="listitem" wire:key="stat-{{ $k }}">
                        <span class="grl-stat-ico"><x-filament::icon :icon="$icon" /></span>
                        <div><strong data-count="{{ $counts[$k] }}">{{ $counts[$k] }}</strong><small>{{ __($w.'stats.'.$k) }}</small></div>
                    </div>
                @endforeach
            </div>

            <div class="grl-filters" role="search" aria-label="{{ __($w.'filters.label') }}">
                <label>
                    <span>{{ __($w.'filters.subject') }}</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="subject">
                            <option value="">{{ __($w.'filters.all_subjects') }}</option>
                            @foreach ($this->subjects() as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>
                <label>
                    <span>{{ __($w.'filters.teacher') }}</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="teacher">
                            <option value="">{{ __($w.'filters.all_teachers') }}</option>
                            @foreach ($this->teachers() as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>
                <label>
                    <span>{{ __($w.'filters.status') }}</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="status">
                            <option value="">{{ __($w.'filters.live') }}</option>
                            @foreach (['running', 'forming', 'ended'] as $s)
                                <option value="{{ $s }}">{{ GroupView::status($s) }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>
                @if ($this->filtered())
                    <x-filament::button color="gray" outlined size="sm" wire:click="clearFilters" icon="heroicon-m-x-mark">{{ __($w.'filters.clear') }}</x-filament::button>
                @endif
            </div>

            @if ($groups === [])
                <div class="grl-empty" role="status">
                    <x-filament::icon icon="heroicon-o-funnel" style="width: 1.75rem; height: 1.75rem; color: var(--gray-400)" />
                    <strong>{{ __($w.'none.title') }}</strong>
                    <p class="gr-muted">{{ __($w.'none.hint') }}</p>
                    <x-filament::button color="gray" outlined size="sm" wire:click="clearFilters">{{ __($w.'filters.clear') }}</x-filament::button>
                </div>
            @else
                <div class="grl-grid lms-stagger">
                    @foreach ($groups as $g)
                        <a class="gr-card lms-lift" href="{{ GroupDetails::getUrl(['group' => $g['id']]) }}" wire:navigate wire:key="g-{{ $g['id'] }}" data-status="{{ $g['status'] }}"
                            aria-label="{{ $g['subject'] }} · {{ $g['teacher'] }} · {{ GroupView::status($g['status']) }}">
                            <div class="gr-card-head">
                                <div style="display: flex; gap: .65rem; min-width: 0">
                                    <span class="gr-avatar" aria-hidden="true">{{ mb_substr(preg_replace('/^(أ\.|د\.)\s*/u', '', $g['teacher']), 0, 1) }}</span>
                                    <div style="min-width: 0">
                                        <h3 class="gr-card-title">{{ $g['name'] ?: $g['subject'] }}</h3>
                                        <p class="gr-card-sub">{{ collect([$g['name'] ? $g['subject'] : null, $g['teacher'], $g['level']])->filter()->implode(' · ') }}</p>
                                    </div>
                                </div>
                                <span class="gr-pill" data-tone="{{ GroupView::tone($g['status']) }}">{{ GroupView::status($g['status']) }}</span>
                            </div>
                            <div class="gr-times"><x-filament::icon icon="heroicon-m-clock" /><span>{{ GroupView::timesLine($g['times']) }}</span></div>
                            <div class="gr-meta">
                                <span>{{ GroupView::shape($g['minutes'], $g['per_week']) }}</span>
                                @if ($g['age'])
                                    <span><x-filament::icon icon="heroicon-m-cake" />{{ $g['age'] }}</span>
                                @endif
                                @if ($g['status'] === 'running')
                                    <span>{{ trans_choice('scheduling::group_screens.card.held', $g['held'], ['n' => $g['held']]) }}</span>
                                @endif
                                @if ($g['next'])
                                    <span><x-filament::icon icon="heroicon-m-calendar" />{{ __('scheduling::group_screens.card.next') }} <strong>{{ $g['next']['label'] }}</strong></span>
                                @elseif ($g['status'] === 'forming')
                                    <span>{{ $g['min_to_start'] && $g['min_to_start'] > $g['taken']
                                        ? trans_choice('scheduling::group_screens.card.waits', $g['min_to_start'] - $g['taken'], ['n' => $g['min_to_start'] - $g['taken']])
                                        : __('scheduling::group_screens.card.starts_first') }}</span>
                                @endif
                            </div>
                            <div class="gr-foot">
                                @include('scheduling::filament.groups.seats', ['taken' => $g['taken'], 'capacity' => $g['capacity']])
                                <span class="gr-muted">
                                    @if ($g['status'] === 'ended')
                                        {{ __('scheduling::group_screens.card.ended_on', ['date' => $g['ended_on']]) }}
                                    @elseif ($g['free'] > 0)
                                        {{ trans_choice('scheduling::group_screens.card.free', $g['free'], ['n' => $g['free']]) }}
                                    @else
                                        {{ __('scheduling::group_screens.card.full') }}
                                    @endif
                                </span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        @endif
    </div>
</x-filament-panels::page>

@script
<script>
    // Lessons held and the next lesson move with the academy's lesson signal (LmsLive, shared/live.blade.php).
    const el = $wire.$el.querySelector('.grl');
    if (el && window.LmsLive) {
        window.LmsLive.watch({ el, channel: el.dataset.liveChannel, event: '.changed', refresh: () => $wire.$refresh(), every: 120000 });
    }
</script>
@endscript
