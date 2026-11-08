@php $l = 'platform::routes.'; $rows = $this->rows; @endphp
@php
    $chain = function (array $steps, bool $first = true) use ($l) {
        $out = '';
        foreach ($steps as $i => $s) {
            if ($i > 0) { $out .= '<span class="ai-arrow" aria-hidden="true">'.e(__($l.'table.then')).'</span>'; }
            $tier = $s['tier'] ? ' · '.e(__($l.'form.tiers.'.$s['tier'])) : '';
            $off = $s['off'] ? ' ('.e(__('platform::engines.card.disabled')).')' : '';
            $out .= '<span class="ai-step"'.($first && $i === 0 ? ' data-first' : '').'>'.e($s['name']).$tier.$off.'</span>';
        }
        return new \Illuminate\Support\HtmlString('<span class="ai-chain">'.$out.'</span>');
    };
    $route = function (?array $r) use ($chain, $l) {
        if ($r === null) { return new \Illuminate\Support\HtmlString('<span class="ai-none">'.e(__($l.'table.settings')).'</span>'); }
        $html = (string) $chain($r['chain']);
        if ($r['split']) {
            $html .= '<span class="ai-split">'.e(__($l.'table.split', ['pct' => $r['split']])).'</span>'.(string) $chain($r['split_chain'], false);
        }
        return new \Illuminate\Support\HtmlString($html);
    };
@endphp
<x-filament-panels::page>
    @include('platform::console.styles')
    @include('platform::console.ai-styles')

    @if ($this->academyName)
        <div class="ai-banner">
            <span>{{ __($l.'for_academy', ['name' => $this->academyName]) }}</span>
            <a href="{{ \Modules\Platform\Filament\Console\Pages\AiRoutesPage::getUrl() }}" wire:navigate>{{ __($l.'all_routes') }}</a>
        </div>
    @endif

    <section class="pc-base pc-box">
        <p class="ai-health">{{ __($l.'order') }}</p>
        <div class="ai-wrap">
            <table class="ai-table">
                <thead>
                    <tr>
                        <th>{{ __($l.'table.capability') }}</th>
                        @if ($this->academyName)
                            <th>{{ __($l.'table.this_academy') }}</th>
                        @endif
                        <th>{{ __($l.'table.default') }}</th>
                        @unless ($this->academyName)
                            <th>{{ __($l.'table.plans') }}</th>
                            <th>{{ __($l.'table.academies') }}</th>
                        @endunless
                        <th><span class="sr-only">{{ __($l.'edit') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr wire:key="route-{{ $row['key'] }}-{{ $row['kind'] }}">
                            <td>
                                <strong>{{ $row['label'] }}<span class="ai-kind">{{ __($l.'kinds.'.$row['kind']) }}</span></strong>
                                <small><bdi>{{ $row['key'] }}</bdi> · {{ __($l.'table.tier', ['tier' => __($l.'form.tiers.'.$row['tier'])]) }} · {{ trans_choice($l.'table.credits', $row['credits'], ['count' => $row['credits']]) }}</small>
                            </td>
                            @if ($this->academyName)
                                <td>{{ $route($row['this_academy']) }}</td>
                            @endif
                            <td>{{ $route($row['default']) }}</td>
                            @unless ($this->academyName)
                                <td>
                                    <div class="ai-scope">
                                        @forelse ($row['plans'] as $p)
                                            <div><b>{{ $p['name'] }}</b>{{ $route($p) }}</div>
                                        @empty
                                            <span class="ai-none">—</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td>
                                    <div class="ai-scope">
                                        @forelse ($row['academies'] as $a)
                                            <div><b>{{ $a['name'] }}</b>{{ $route($a) }}</div>
                                        @empty
                                            <span class="ai-none">—</span>
                                        @endforelse
                                    </div>
                                </td>
                            @endunless
                            <td>{{ ($this->editAction)(['capability' => $row['key'], 'kind' => $row['kind']]) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @if ($this->comparison !== [])
        <section class="pc-base pc-box ai-compare" id="comparison">
            <h3>{{ __($l.'compare.result', ['what' => $this->compared]) }}</h3>
            <p class="ai-health">{{ __($l.'compare.nothing_kept') }}</p>
            @foreach ($this->comparison as $i => $row)
                <div wire:key="compare-{{ $i }}">
                    <strong>{{ $row['case'] }}</strong>
                    <div class="ai-compare-row">
                        @foreach (['a', 'b'] as $side)
                            @php $r = $row[$side]; @endphp
                            <article class="pc-base ai-side">
                                <header>
                                    <span>{{ __($l.'compare.'.$side) }}@if ($r['engine']) · <bdi>{{ $r['engine'] }}</bdi>@endif</span>
                                    @if ($r['model'])<bdi>{{ $r['model'] }}</bdi>@endif
                                </header>
                                @if ($r['failed'])
                                    <p class="ai-fail">{{ $r['failed'] }}</p>
                                @else
                                    <pre dir="auto">{{ $r['text'] }}</pre>
                                @endif
                                <div class="ai-figs">
                                    <span>{{ __($l.'compare.ms', ['ms' => number_format($r['ms'])]) }}</span>
                                    @if ($r['usage'])<span>{{ $r['usage'] }}</span>@endif
                                    <span>{{ $r['cost'] ? __($l.'compare.cost', ['cost' => $r['cost']]) : __($l.'compare.no_cost') }}</span>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </section>
    @endif

    <section class="pc-base pc-box ai-cases">
        <h3>{{ __($l.'cases.title') }}</h3>
        <p class="ai-health">{{ __($l.'cases.about') }}</p>
        <ul>
            @forelse ($this->cases as $c)
                <li wire:key="case-{{ $c['id'] }}">
                    <span><strong>{{ $c['title'] }}</strong> · {{ $c['capability'] }} <span class="ai-kind">{{ __($l.'kinds.'.$c['kind']) }}</span></span>
                    {{ ($this->removeCaseAction)(['case' => $c['id']]) }}
                </li>
            @empty
                <li><small>{{ __($l.'cases.none') }}</small></li>
            @endforelse
        </ul>
    </section>
</x-filament-panels::page>
