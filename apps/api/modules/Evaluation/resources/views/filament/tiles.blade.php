{{-- KPI tiles by group. $cards from KpiView::cards(). --}}
<div class="kp-groups">
    @foreach (collect($cards)->groupBy('group') as $group => $tiles)
        <section class="kp-group">
            <h3>{{ __('evaluation::kpis.groups.'.$group) }}</h3>
            <div class="kp-tiles lms-stagger">
                @foreach ($tiles as $c)
                    <div class="kp-base kp-tile" data-state="{{ $c['state'] }}">
                        <span class="kp-label">{{ $c['label'] }}</span>
                        <strong>{{ $c['value'] }}</strong>
                        <small>{{ $c['hint'] }}@if ($c['target']) · {{ $c['target'] }}@endif</small>
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach
</div>
